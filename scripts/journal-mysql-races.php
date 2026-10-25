<?php
// Local-only transaction tests with independent PHP/MySQL connections and synthetic data.
// No HTTP/camera claim. Never migrates, seeds, or edits the working database.
require __DIR__.'/../vendor/autoload.php';

use App\Http\Controllers\{IndustryJournalController, InternshipRosterController, JournalController};
use App\Models\{IndustryDevice, IndustryMentor, Journal, JournalIndustryValidation, Placement, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Auth, DB};
use Symfony\Component\Process\Process;

$mode=$argv[1]??'';
$worker=in_array($mode,['worker','seed','scenarios'],true);
$directory=$worker?realpath(getenv('SIMMAG_RACE_DIR')?:''):null;
$database=$worker?(getenv('SIMMAG_RACE_DB')?:''):null;
if ($worker && (!$directory || dirname($directory)!==realpath(sys_get_temp_dir())
    || !preg_match('/^simmag_journal_probe_([a-f0-9]{16})$/',$database,$match)
    || basename($directory)!=='simmag-journal-race-'.$match[1] || !is_file($directory.'/owner'))) {
    throw new RuntimeException('Invalid isolated worker context.');
}
$app=require __DIR__.'/../bootstrap/app.php';
if ($worker) $app->useStoragePath($directory.'/storage');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function check(bool $condition,string $message): void { if (!$condition) throw new RuntimeException($message); }
function awaitFiles(array $files,int $seconds=15): void {
    $end=microtime(true)+$seconds;
    while (microtime(true)<$end) {
        if (array_all_exist($files)) return;
        usleep(20000);
    }
    throw new RuntimeException('Worker synchronization timed out.');
}
function array_all_exist(array $files): bool {
    clearstatcache();
    foreach ($files as $file) if (!is_file($file)) return false;
    return true;
}
function sourceFingerprint(Illuminate\Database\Connection $connection): array {
    $fingerprint=[];
    $tables=$connection->table('information_schema.TABLES')->where('TABLE_SCHEMA',$connection->getDatabaseName())
        ->where('TABLE_TYPE','BASE TABLE')->orderBy('TABLE_NAME')->pluck('TABLE_NAME');
    foreach ($tables as $table) {
        $rows=[];
        foreach ($connection->table($table)->cursor() as $row) $rows[]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));
        sort($rows);
        $fingerprint[$table]=['rows'=>count($rows),'sha256'=>hash('sha256',implode('', $rows))];
    }
    return $fingerprint;
}
function requestFor(array $fixture,string $action,string $slot): Request {
    $params=$action==='edit'?['placement_id'=>$fixture['placement'],'activity_date'=>$fixture['date'],
        'activity'=>'Perubahan dari tab '.$slot,'outcome'=>'Hasil uji sintetis','intent'=>'submitted','revision'=>1,'industry_score'=>91.25]:[];
    $request=Request::create('/industry/scan/'.$fixture['token'],'POST',$params,['industry_device'=>$fixture['secret']]);
    $request->setLaravelSession(app('session')->driver());
    if ($action==='edit') {
        $user=User::findOrFail($fixture['student_user']); Auth::setUser($user);
        $request->setUserResolver(fn()=>$user);
    }
    app()->instance('request',$request);
    return $request;
}

if ($worker) {
    check(app()->environment('testing') && DB::connection()->getDriverName()==='mysql' && DB::connection()->getDatabaseName()===$database,'Unsafe worker database.');
    check(DB::selectOne('SELECT DATABASE() AS current_db')->current_db===$database,'Unsafe worker connection.');
    if ($mode==='seed') {
        check(DB::table('information_schema.TABLES')->where('TABLE_SCHEMA',$database)->count()===0,'Seed destination must be empty.');
        Artisan::call('migrate',['--force'=>true]);
        Artisan::call('db:seed',['--force'=>true]);
        echo json_encode(['seeded'=>true]); exit;
    }
}

if ($mode==='worker') {
    $slot=$argv[2]??'';
    check((bool)preg_match('/^[a-z0-9_]+$/',$slot),'Invalid worker slot.');
    $fixture=json_decode(file_get_contents($directory.'/'.$slot.'.json'),true,512,JSON_THROW_ON_ERROR);
    config(['session.driver'=>'array','cache.default'=>'array']);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
    // Mark the actual attempt BEFORE the SQL blocks, proving requests overlap on a row lock.
    DB::connection()->beforeExecuting(function($sql) use ($directory,$slot,$fixture) {
        if (str_contains(strtolower($sql),'for update') && str_contains($sql,'`'.$fixture['barrier_table'].'`')) touch($directory.'/'.$slot.'.attempt');
    });
    touch($directory.'/'.$slot.'.ready');
    awaitFiles([$directory.'/'.$slot.'.go']);
    try {
        $request=requestFor($fixture,$fixture['action'],$slot);
        $response=match($fixture['action']) {
            'scan'=>app(IndustryJournalController::class)->scan($request,$fixture['token']),
            'enroll'=>app(IndustryJournalController::class)->enroll($request,$fixture['token']),
            'edit'=>app(JournalController::class)->store($request),
        };
        $result=['status'=>$response->getStatusCode()];
    } catch (Illuminate\Validation\ValidationException $e) { $result=['status'=>422,'fields'=>array_keys($e->errors())]; }
    catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { $result=['status'=>$e->getStatusCode()]; }
    catch (Throwable $e) { $result=['status'=>500,'error_class'=>get_class($e)]; }
    echo json_encode($result,JSON_THROW_ON_ERROR);
    exit;
}

if ($mode!=='scenarios') {
$source=DB::connection(); $config=$source->getConfig(); $sourceName=$source->getDatabaseName();
check(app()->environment('local') && $source->getDriverName()==='mysql'
    && in_array($config['host'],['localhost','127.0.0.1','::1'],true),'Run only on local MySQL.');
$suffix=bin2hex(random_bytes(8)); $database='simmag_journal_probe_'.$suffix;
check($database!==$sourceName,'Refusing working database.');
$directory=sys_get_temp_dir().'/simmag-journal-race-'.$suffix;
check(!file_exists($directory) && mkdir($directory,0700),'Cannot create fresh test directory.');
foreach (['app/private','framework/views','framework/cache','framework/sessions','logs'] as $path) mkdir($directory.'/storage/'.$path,0700,true);
file_put_contents($directory.'/owner',$suffix);
$created=false; $children=[]; $checks=[]; $report=['status'=>'failed','database'=>$database,'temporary_database_removed'=>false];
$before=sourceFingerprint($source);
$env=['SIMMAG_RACE_DIR'=>$directory,'SIMMAG_RACE_DB'=>$database,'APP_ENV'=>'testing','DB_CONNECTION'=>'mysql','DB_DATABASE'=>$database,
    'DB_URL'=>'','DB_HOST'=>$config['host'],'DB_PORT'=>$config['port']??3306,'DB_USERNAME'=>$config['username'],'DB_PASSWORD'=>$config['password']??'',
    'DB_SOCKET'=>$config['unix_socket']??'','APP_CONFIG_CACHE'=>$directory.'/config.php','APP_ROUTES_CACHE'=>$directory.'/routes.php',
    'SESSION_DRIVER'=>'array','CACHE_STORE'=>'array','BCRYPT_ROUNDS'=>'4'];
try {
    $source->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created=true;
    // A fresh application must boot with the isolated DB, including Schema/migration bindings.
    $seed=new Process([PHP_BINARY,__FILE__,'seed'],base_path(),$env,null,60);
    $children[]=$seed;
    $seed->run(); check($seed->isSuccessful(),'Isolated migration/seeding failed.');
    $runner=new Process([PHP_BINARY,__FILE__,'scenarios'],base_path(),$env,null,150);
    $children[]=$runner;
    $runner->run(fn($type,$buffer)=>print($buffer));
    check($runner->isSuccessful(),'Concurrent scenario process failed.');
    $checks=json_decode(file_get_contents($directory.'/checks.json'),true,512,JSON_THROW_ON_ERROR);
    $report['status']='passed';
} catch (Throwable $e) {
    $report['error']=get_class($e)===RuntimeException::class?$e->getMessage():get_class($e);
    echo 'FAIL: '.$report['error'].PHP_EOL;
} finally {
    foreach ($children as $process) if ($process->isRunning()) $process->stop(1);
    if ($created) {
        check($database!==$sourceName && preg_match('/^simmag_journal_probe_[a-f0-9]{16}$/',$database),'Unsafe cleanup target.');
        try { $source->statement('DROP DATABASE `'.$database.'`'); $report['temporary_database_removed']=true; }
        catch (Throwable $e) { $report['status']='failed'; $report['cleanup_error']='Temporary database not removed.'; }
    }
    try {
        $report['working_database_rows_unchanged']=sourceFingerprint($source)===$before;
        if (!$report['working_database_rows_unchanged']) {
            $report['status']='failed'; $report['source_error']='Working data changed during the run; inspect external activity.';
        }
    } catch (Throwable $e) { $report['status']='failed'; $report['source_error']='Could not verify working data.'; }
    $report['checks']=$checks; $report['finished_at']=now('Asia/Jakarta')->toIso8601String();
    file_put_contents($directory.'/result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo 'Report: '.$directory.'/result.json'.PHP_EOL;
}
exit($report['status']==='passed'?0:1);
}

$children=[]; $checks=[];
config(['session.driver'=>'array','cache.default'=>'array','filesystems.disks.local.root'=>$directory.'/storage/app/private']);
check((new Journal)->getConnection()->getDatabaseName()===$database,'Unexpected model destination.');
try {
    $placement=Placement::whereHas('roster',fn($q)=>$q->where('internship_starts_at','<',now()->toDateString())->where('internship_ends_at','>',now()->toDateString()))->firstOrFail();
    $coordinator=User::where('role','koordinator')->firstOrFail(); Auth::setUser($coordinator);
    $mentor=IndustryMentor::create(['name'=>'Dodi Race Demo','company_id'=>$placement->company_id,'created_by'=>$coordinator->id]);
    $mentor->placements()->attach($placement->id);
    $secret=str_repeat('d',64);
    $device=IndustryDevice::create(['industry_mentor_id'=>$mentor->id,'token_hash'=>hash('sha256',$secret),'label'=>'Test device','expires_at'=>now()->addDay()]);
    $run=function(array $fixtures) use (&$children,$directory): array {
        $processes=[];
        foreach ($fixtures as $slot=>$fixture) {
            file_put_contents($directory.'/'.$slot.'.json',json_encode($fixture,JSON_THROW_ON_ERROR));
            $process=new Process([PHP_BINARY,__FILE__,'worker',$slot],base_path(),null,null,25);
            $process->start(); $children[]=$process; $processes[$slot]=$process;
        }
        awaitFiles(array_map(fn($slot)=>$directory.'/'.$slot.'.ready',array_keys($fixtures)));
        foreach (array_keys($fixtures) as $slot) touch($directory.'/'.$slot.'.go');
        awaitFiles(array_map(fn($slot)=>$directory.'/'.$slot.'.attempt',array_keys($fixtures)));
        // The parent transaction held the contested row until every child attempted its lock.
        DB::commit();
        $statuses=[];
        foreach ($processes as $slot=>$process) {
            $process->wait(); check($process->isSuccessful(),'Worker process failed: '.$slot);
            $response=json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR);
            check(isset($response['status']),'Invalid worker response.');
            $statuses[]=$response['status'];
        }
        sort($statuses); return $statuses;
    };
    $fixtureFor=function(int $days) use ($placement,$secret): array {
        $date=now('Asia/Jakarta')->subDays($days)->toDateString();
        $journal=Journal::create(['placement_id'=>$placement->id,'activity_date'=>$date,'activity'=>'Jurnal race demo',
            'outcome'=>'Data fiktif','status'=>'submitted','industry_score'=>88.25,'revision'=>1]);
        $token=Illuminate\Support\Str::random(64);
        DB::table('journal_scan_tokens')->insert(['journal_id'=>$journal->id,'revision'=>1,'token_hash'=>hash('sha256',$token),
            'expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);
        return ['journal'=>$journal->id,'placement'=>$placement->id,'student_user'=>$placement->student->user_id,
            'date'=>$date,'secret'=>$secret,'token'=>$token,'action'=>'scan','barrier_table'=>'journals'];
    };
    $record=function(string $name) use (&$checks) { $checks[]=$name; echo 'PASS: '.$name.PHP_EOL; };

    $f=$fixtureFor(2);
    DB::beginTransaction(); Journal::whereKey($f['journal'])->lockForUpdate()->firstOrFail();
    check($run(['scan_a'=>$f,'scan_b'=>$f])===[302,302],'Duplicate scans did not complete idempotently.');
    check(JournalIndustryValidation::where('journal_id',$f['journal'])->count()===1,'Duplicate validation.');
    check(DB::table('audit_logs')->where('action','journal.industry_validated')->where('subject_id',$f['journal'])->count()===1,'Duplicate validation audit.');
    $record('Two overlapping scans: one validation and one audit');

    $f=$fixtureFor(3); $f['action']='edit'; $f['barrier_table']='internship_rosters';
    DB::beginTransaction(); DB::table('internship_rosters')->where('id',$placement->internship_roster_id)->lockForUpdate()->first();
    check($run(['edit_a'=>$f,'edit_b'=>$f])===[302,422],'Stale editor was not rejected.');
    check(Journal::findOrFail($f['journal'])->revision===2,'Concurrent edits overwrote the version.');
    check(DB::table('journal_events')->where('journal_id',$f['journal'])->count()===1,'Duplicate edit event.');
    $record('Two overlapping student edits: one accepted, stale tab rejected');

    $f=$fixtureFor(4);
    DB::beginTransaction(); Journal::whereKey($f['journal'])->lockForUpdate()->firstOrFail();
    app(JournalController::class)->store(requestFor($f,'edit','source_edit'));
    check($run(['scan_old_revision'=>$f])===[409],'Scan accepted journal edited while waiting for a lock.');
    check(JournalIndustryValidation::where('journal_id',$f['journal'])->count()===0,'Stale revision validated.');
    $record('Scan waiting for student edit: old QR rejected, no validation');

    $f=$fixtureFor(5); $f['barrier_table']='industry_mentors';
    DB::beginTransaction(); IndustryMentor::whereKey($mentor->id)->lockForUpdate()->firstOrFail();
    Auth::setUser($coordinator);
    app(IndustryJournalController::class)->revoke(Request::create('/revoke','PUT'),$device);
    check($run(['scan_revoked'=>$f])===[403],'Scan accepted a device revoked while it was waiting.');
    check(JournalIndustryValidation::where('journal_id',$f['journal'])->count()===0,'Revoked device created validation.');
    $record('Scan waiting for revocation: rejected, no validation');

    $token=Illuminate\Support\Str::random(64);
    DB::table('industry_device_invites')->insert(['industry_mentor_id'=>$mentor->id,'created_by'=>$coordinator->id,
        'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);
    $f['token']=$token; $f['action']='enroll';
    $count=IndustryDevice::count();
    DB::beginTransaction(); IndustryMentor::whereKey($mentor->id)->lockForUpdate()->firstOrFail();
    check($run(['enroll_a'=>$f,'enroll_b'=>$f])===[302,410],'Enrollment QR was not consumed exactly once.');
    check(IndustryDevice::count()===$count+1,'Enrollment created duplicate devices.');
    $record('Two overlapping enrollments: one device, reused QR rejected');

    $f=$fixtureFor(6); $f['action']='edit'; $f['barrier_table']='internship_rosters';
    DB::beginTransaction();
    $roster=$placement->roster()->lockForUpdate()->firstOrFail();
    Auth::setUser($coordinator);
    $request=Request::create('/roster/'.$roster->id,'PUT',[
        'internship_status'=>'internship','internship_starts_at'=>now('Asia/Jakarta')->subDay()->toDateString(),
        'internship_ends_at'=>$roster->internship_ends_at->toDateString(),
    ]);
    $request->setUserResolver(fn()=>$coordinator);
    app()->instance('request',$request);
    app(InternshipRosterController::class)->update($request,$roster);
    check($run(['edit_changed_schedule'=>$f])===[422],'Student edit ignored a schedule corrected while waiting.');
    check(Journal::findOrFail($f['journal'])->revision===1,'Rejected edit changed the journal.');
    check(DB::table('journal_events')->where('journal_id',$f['journal'])->count()===0,'Rejected edit created an event.');
    $record('Edit waiting for schedule correction: rejected, journal unchanged');
    file_put_contents($directory.'/checks.json',json_encode($checks,JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    $failed=true;
    echo 'FAIL: '.(get_class($e)===RuntimeException::class?$e->getMessage():get_class($e)).PHP_EOL;
} finally {
    foreach ($children as $process) if ($process->isRunning()) $process->stop(1);
    while (DB::transactionLevel()>0) DB::rollBack();
}
exit(isset($failed)?1:0);
