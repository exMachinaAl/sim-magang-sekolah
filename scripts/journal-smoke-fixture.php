<?php
// Dedicated smoke-test bootstrap. Never serves the working database or storage.
require __DIR__.'/../vendor/autoload.php';

$directory=realpath(getenv('SIMMAG_SMOKE_DIR') ?: '');
$temp=realpath(sys_get_temp_dir());
if (!$directory || !$temp || dirname($directory)!==$temp || !str_starts_with(basename($directory),'simmag-journal-smoke-')) {
    throw new RuntimeException('Use an isolated simmag-journal-smoke-* directory immediately inside the OS temporary directory.');
}
$seed=PHP_SAPI==='cli' && ($argv[1] ?? '')==='seed';
$database=$directory.'/test.sqlite';
if ($seed && file_exists($database)) throw new RuntimeException('Refusing to reset an existing database. Start with a NEW temporary directory.');
if (!$seed && !is_file($database)) throw new RuntimeException('Seed the isolated fixture first.');
foreach ([
    'APP_ENV'=>'local','APP_DEBUG'=>'false','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$database,'DB_URL'=>'',
    'SESSION_DRIVER'=>'file','SESSION_COOKIE'=>'simmag_smoke_session','SESSION_DOMAIN'=>'','SESSION_SECURE_COOKIE'=>'false',
    'CACHE_STORE'=>'file','QUEUE_CONNECTION'=>'sync','MAIL_MAILER'=>'log','BCRYPT_ROUNDS'=>'4',
    'APP_CONFIG_CACHE'=>$directory.'/config.php','APP_ROUTES_CACHE'=>$directory.'/routes.php',
] as $key=>$value) { putenv($key.'='.$value); $_ENV[$key]=$_SERVER[$key]=$value; }
if ($seed) {
    foreach (['app/private','app/public','framework/cache/data','framework/sessions','framework/views','logs'] as $path) mkdir($directory.'/storage/'.$path,0700,true);
    touch($database);
}
$app=require __DIR__.'/../bootstrap/app.php';
$app->useStoragePath($directory.'/storage');
if ($seed) {
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $connection=Illuminate\Support\Facades\DB::connection();
    if ($connection->getDriverName()!=='sqlite' || realpath($connection->getDatabaseName())!==realpath($database)) throw new RuntimeException('Unsafe database configuration.');
    Illuminate\Support\Facades\Artisan::call('migrate:fresh',['--force'=>true]);
    Illuminate\Support\Facades\Artisan::call('db:seed',['--force'=>true]);
    Illuminate\Support\Facades\Artisan::call('db:seed',['--class'=>Database\Seeders\IndustryJournalDemoSeeder::class,'--force'=>true]);
    $mentor=App\Models\IndustryMentor::where('name','Dodi (Demo)')->firstOrFail();
    $placement=$mentor->placements()->whereHas('roster',fn($q)=>$q->where('internship_starts_at','<',now()->toDateString())->where('internship_ends_at','>',now()->toDateString()))->firstOrFail();
    $journal=$placement->journals()->firstOrFail();
    $certificate=App\Models\Certificate::where('status','awaiting_print')->firstOrFail();
    $unplaced=App\Models\InternshipRoster::whereDoesntHave('placement')->firstOrFail();
    $candidate=App\Models\Placement::whereHas('assessment',fn($q)=>$q->where('status','approved'))->whereDoesntHave('certificate')->firstOrFail();
    if (getenv('SIMMAG_CERTIFICATE_DEMO')==='1') Illuminate\Support\Facades\Artisan::call('db:seed',['--class'=>Database\Seeders\CertificatePreviewDemoSeeder::class,'--force'=>true]);
    if (getenv('SIMMAG_CERTIFICATE_DEMO')==='1') Illuminate\Support\Facades\Artisan::call('db:seed',['--class'=>Database\Seeders\CertificateDemoWordSeeder::class,'--force'=>true]);
    echo json_encode(['print_candidate'=>$candidate->id,'print_assessment_template'=>$candidate->assessment->assessment_template_id,'print_nis'=>$candidate->student->nis,'unplaced_roster'=>$unplaced->id,'unplaced_nis'=>$unplaced->nis,'unplaced_name'=>$unplaced->name,'certificate'=>$certificate->id,'certificate_nis'=>$certificate->placement->student->nis,'mentor'=>$mentor->id,'placement'=>$placement->id,'journal'=>$journal->id,
        'nis'=>$placement->student->nis,'activity_date'=>$journal->activity_date->toDateString(),'students'=>App\Models\Student::count()],JSON_THROW_ON_ERROR);
    exit;
}
// Let PHP's development server serve public assets, never files outside public/.
$asset=realpath(__DIR__.'/../public/'.ltrim(rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)),'/'));
$public=realpath(__DIR__.'/../public').DIRECTORY_SEPARATOR;
if ($asset && str_starts_with($asset,$public) && is_file($asset) && strtolower(pathinfo($asset,PATHINFO_EXTENSION))!=='php') return false;
$app->handleRequest(Illuminate\Http\Request::capture());
