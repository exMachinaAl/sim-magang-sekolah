<?php
// Local-only transaction tests with independent PHP/MySQL connections and synthetic data.
// No HTTP/camera claim. Never migrates, seeds, or edits the working database.
require __DIR__.'/../vendor/autoload.php';

use App\Http\Controllers\CertificateController;
use App\Models\{Certificate, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Auth, DB};
use Symfony\Component\Process\Process;

$mode=$argv[1]??'';
$worker=in_array($mode,['worker','seed','scenarios'],true);
$directory=$worker?realpath(getenv('SIMMAG_RACE_DIR')?:''):null;
$database=$worker?(getenv('SIMMAG_RACE_DB')?:''):null;
if ($worker && (!$directory || dirname($directory)!==realpath(sys_get_temp_dir())
    || !preg_match('/^simmag_cert_probe_([a-f0-9]{16})$/',$database,$match)
    || basename($directory)!=='simmag-cert-race-'.$match[1] || !is_file($directory.'/owner'))) {
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
        $user=User::findOrFail($fixture['actor']); Auth::setUser($user);
        $request=Request::create('/certificate-probe','POST',$fixture['params']);
        $request->setUserResolver(fn()=>$user);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request',$request);
        $controller=app(CertificateController::class);
        $action=$fixture['action'];
        $response=$action==='create'?$controller->create($request)
            :$controller->{$action}($request,Certificate::findOrFail($fixture['certificate']));
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
$suffix=bin2hex(random_bytes(8)); $database='simmag_cert_probe_'.$suffix;
check($database!==$sourceName,'Refusing working database.');
$directory=sys_get_temp_dir().'/simmag-cert-race-'.$suffix;
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
        check($database!==$sourceName && preg_match('/^simmag_cert_probe_[a-f0-9]{16}$/',$database),'Unsafe cleanup target.');
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

require __DIR__.'/certificate-race-scenarios.php';
