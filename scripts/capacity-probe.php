<?php

// Local-only capacity probe on a fresh, isolated MySQL database.
// It never migrates, seeds, or writes to the configured working database.
require __DIR__.'/../vendor/autoload.php';

use App\Http\Controllers\{AssessmentController, CertificateController, DashboardController, JournalController, MonitoringController, TimelineController};
use App\Models\{InternshipRoster, Placement, User};
use App\Support\{JournalWorkbook, MainMenuAudit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Auth, DB};
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\Process;

function probeCheck(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}

function probeFingerprint(Illuminate\Database\Connection $connection): array
{
    $result = [];
    $tables = $connection->table('information_schema.TABLES')
        ->where('TABLE_SCHEMA', $connection->getDatabaseName())->where('TABLE_TYPE', 'BASE TABLE')
        ->orderBy('TABLE_NAME')->pluck('TABLE_NAME');
    foreach ($tables as $table) {
        $hash = hash_init('sha256');
        $count = 0;
        foreach ($connection->table($table)->orderBy($connection->getSchemaBuilder()->getColumnListing($table)[0])->cursor() as $row) {
            hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
            $count++;
        }
        $result[$table] = ['rows' => $count, 'sha256' => hash_final($hash)];
    }
    return $result;
}

function insertChunks(string $table, array $rows, int $size = 500): void
{
    foreach (array_chunk($rows, $size) as $chunk) DB::table($table)->insert($chunk);
}

function seedCapacityData(): array
{
    $started = hrtime(true);
    $now = now('Asia/Jakarta');
    $stamp = ['created_at' => $now, 'updated_at' => $now];
    $majors = InternshipRoster::officialMajors();
    $password = password_hash('CapacityOnly123!', PASSWORD_BCRYPT, ['cost' => 4]);

    $users = [[
        'id' => 1, 'name' => 'Koordinator Kapasitas', 'nip' => 'CAPACITY-COORDINATOR',
        'email' => 'coordinator@capacity.invalid', 'password' => $password, 'role' => 'koordinator',
        'is_active' => true, 'remember_token' => null,
    ] + $stamp];
    for ($i = 0; $i < 20; $i++) {
        $users[] = ['id' => 2 + $i, 'name' => 'Guru Kapasitas '.($i + 1), 'nip' => 'CAP-G-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            'email' => 'teacher'.($i + 1).'@capacity.invalid', 'password' => $password, 'role' => 'guru',
            'is_active' => true, 'remember_token' => null] + $stamp;
    }
    for ($i = 0; $i < 320; $i++) {
        $users[] = ['id' => 22 + $i, 'name' => 'Siswa Kapasitas '.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'nip' => null,
            'email' => 'student'.($i + 1).'@capacity.invalid', 'password' => $password, 'role' => 'siswa',
            'is_active' => true, 'remember_token' => null] + $stamp;
    }
    insertChunks('users', $users);

    $companies = [];
    for ($i = 1; $i <= 16; $i++) $companies[] = ['id' => $i, 'name' => 'Perusahaan Kapasitas '.$i,
        'address' => 'Data sintetis pengujian', 'contact' => null] + $stamp;
    insertChunks('companies', $companies);
    DB::table('internship_periods')->insert(['id' => 1, 'name' => '2026/2027', 'academic_year' => '2026/2027',
        'starts_at' => null, 'ends_at' => null, 'is_active' => true] + $stamp);

    $students = [];
    $rosters = [];
    for ($i = 0; $i < 320; $i++) {
        $id = $i + 1;
        $nis = 'CAP'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $major = $majors[$i % count($majors)];
        $placed = $id <= 256;
        $status = $placed ? 'internship' : ($id <= 288 ? 'pending' : 'not_internship');
        $students[] = ['id' => $id, 'user_id' => 22 + $i, 'nis' => $nis, 'nisn' => '99'.str_pad((string) $id, 8, '0', STR_PAD_LEFT),
            'class_name' => 'XII '.(($i % 8) + 1), 'major' => $major, 'birth_date' => '2008-01-01'] + $stamp;
        $rosters[] = ['id' => $id, 'academic_year' => '2026/2027', 'nis' => $nis,
            'nisn' => '99'.str_pad((string) $id, 8, '0', STR_PAD_LEFT), 'name' => 'Siswa Kapasitas '.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'email' => 'student'.$id.'@capacity.invalid', 'class_name' => 'XII '.(($i % 8) + 1), 'major' => $major,
            'internship_status' => $status, 'internship_starts_at' => $placed ? '2026-07-01' : null,
            'internship_ends_at' => $placed ? '2027-01-31' : null, 'schedule_source' => $placed ? 'school_batch' : null,
            'schedule_batch_id' => null, 'internship_period_id' => 1, 'student_id' => $id,
            'updated_by' => 1, 'notes' => 'DATA SINTETIS PROBE KAPASITAS'] + $stamp;
    }
    insertChunks('students', $students);
    insertChunks('internship_rosters', $rosters);

    $placements = [];
    for ($id = 1; $id <= 256; $id++) {
        $placements[] = ['id' => $id, 'student_id' => $id, 'company_id' => (($id - 1) % 16) + 1,
            'internship_period_id' => 1, 'internship_roster_id' => $id,
            'teacher_id' => $id % 8 === 0 ? null : 2 + (($id - 1) % 20),
            'industry_mentor_id' => null, 'status' => 'active'] + $stamp;
    }
    insertChunks('placements', $placements);

    $journals = [];
    $journalId = 1;
    for ($placement = 1; $placement <= 256; $placement++) {
        for ($day = 0; $day < 20; $day++) {
            $journals[] = ['id' => $journalId++, 'placement_id' => $placement,
                'activity_date' => date('Y-m-d', strtotime('2026-07-01 +'.$day.' days')),
                'activity' => 'Kegiatan sintetis kapasitas hari '.($day + 1), 'outcome' => 'Selesai', 'obstacles' => null,
                'status' => $day % 5 === 0 ? 'approved' : 'submitted', 'review_note' => null,
                'reviewed_by' => $day % 5 === 0 ? 1 : null, 'reviewed_at' => $day % 5 === 0 ? $now : null,
                'work_area' => 'Area sintetis', 'starts_time' => '08:00', 'ends_time' => '16:00',
                'equipment_notes' => 'Peralatan sintetis', 'industry_score' => 80 + ($day % 16),
                'revision' => 1, 'reviewed_revision' => $day % 5 === 0 ? 1 : null, 'validated_revision' => null] + $stamp;
        }
        if (count($journals) >= 500) {
            insertChunks('journals', $journals);
            $journals = [];
        }
    }
    if ($journals) insertChunks('journals', $journals);

    $assessments = [];
    $certificates = [];
    $approvals = [];
    for ($id = 1; $id <= 128; $id++) {
        $assessments[] = ['id' => $id, 'placement_id' => $id, 'assessor_id' => 1, 'status' => 'submitted',
            'notes' => null, 'final_score' => 88, 'approved_by' => null, 'approved_at' => null,
            'assessment_template_id' => null, 'template_snapshot' => null, 'field_values' => null,
            'industry_assessor' => 'Penilai Sintetis', 'assessed_on' => '2026-07-20', 'calculated_score' => 88,
            'final_score_reason' => null, 'revision' => 1] + $stamp;
        $certificates[] = ['id' => $id, 'placement_id' => $id, 'number' => 'CAPACITY/'.str_pad((string) $id, 5, '0', STR_PAD_LEFT),
            'version' => 1, 'status' => 'awaiting_approval', 'pickup_at' => null, 'pickup_location' => null,
            'pickup_requirements' => null, 'issued_at' => null, 'print_template_id' => null, 'print_options' => null] + $stamp;
        $approvals[] = ['id' => $id, 'certificate_id' => $id, 'party' => 'coordinator', 'approver_id' => null,
            'status' => 'pending', 'note' => null, 'decided_at' => null] + $stamp;
    }
    insertChunks('assessments', $assessments);
    insertChunks('certificates', $certificates);
    insertChunks('certificate_approvals', $approvals);

    return [
        'students' => 320, 'rosters' => 320, 'placements' => 256, 'journals' => 5120,
        'assessments' => 128, 'certificates' => 128,
        'seed_ms' => round((hrtime(true) - $started) / 1e6, 2),
    ];
}

function probeRequest(User $user, string $path, array $parameters = []): Request
{
    $request = Request::create($path, 'GET', $parameters);
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request', $request);
    Auth::setUser($user);
    return $request;
}

$mode = $argv[1] ?? '';
$worker = $mode === 'worker';
$directory = $worker ? realpath(getenv('SIMMAG_CAPACITY_DIR') ?: '') : null;
$database = $worker ? (getenv('SIMMAG_CAPACITY_DB') ?: '') : null;
if ($worker && (! $directory || dirname($directory) !== realpath(sys_get_temp_dir())
    || ! preg_match('/^simmag_capacity_probe_([a-f0-9]{16})$/', $database, $match)
    || basename($directory) !== 'simmag-capacity-'.$match[1] || ! is_file($directory.'/owner'))) {
    throw new RuntimeException('Invalid isolated capacity worker context.');
}

$app = require __DIR__.'/../bootstrap/app.php';
if ($worker) $app->useStoragePath($directory.'/storage');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if ($worker) {
    probeCheck(app()->environment('testing') && DB::connection()->getDriverName() === 'mysql'
        && DB::connection()->getDatabaseName() === $database, 'Unsafe capacity database.');
    probeCheck(DB::selectOne('SELECT DATABASE() AS current_db')->current_db === $database, 'Capacity connection mismatch.');
    Artisan::call('migrate', ['--force' => true]);
    $dataset = seedCapacityData();
    config(['session.driver' => 'array', 'cache.default' => 'array']);
    Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag());

    $active = null;
    $queries = [];
    DB::listen(function ($query) use (&$active, &$queries): void {
        if ($active !== null) {
            $queries[$active]['count']++;
            $queries[$active]['sql_ms'] += (float) $query->time;
        }
    });
    $results = [];
    $run = function (string $name, Closure $operation, int $maxQueries = 35, float $maxMs = 3000) use (&$active, &$queries, &$results): void {
        $queries[$name] = ['count' => 0, 'sql_ms' => 0.0];
        $active = $name;
        $start = hrtime(true);
        $memory = memory_get_usage(true);
        $value = $operation();
        $renderedView = $value instanceof Illuminate\View\View;
        if ($renderedView) $value = $value->render();
        $wall = round((hrtime(true) - $start) / 1e6, 2);
        $active = null;
        $results[$name] = [
            'wall_ms' => $wall,
            'queries' => $queries[$name]['count'],
            'sql_ms' => round($queries[$name]['sql_ms'], 2),
            'memory_delta_mb' => round(max(0, memory_get_usage(true) - $memory) / 1048576, 2),
            'threshold_ms' => $maxMs, 'threshold_queries' => $maxQueries,
        ];
        probeCheck($wall <= $maxMs, $name.' exceeds wall-time threshold: '.$wall.' ms.');
        probeCheck($queries[$name]['count'] <= $maxQueries, $name.' exceeds query threshold: '.$queries[$name]['count'].'.');
        if ($renderedView) probeCheck(strlen($value) > 100, $name.' returned incomplete output.');
    };

    $coordinator = User::findOrFail(1);
    $run('dashboard', fn () => app(DashboardController::class)(probeRequest($coordinator, '/dashboard')));
    $run('timeline_directory', fn () => app(TimelineController::class)->index(probeRequest($coordinator, '/timeline', ['period_id' => 1])));
    $run('timeline_search', fn () => app(TimelineController::class)->index(probeRequest($coordinator, '/timeline', ['period_id' => 1, 'q' => 'CAP000256'])));
    $run('journals_directory', fn () => app(JournalController::class)->index(probeRequest($coordinator, '/jurnal')));
    $run('journals_search', fn () => app(JournalController::class)->index(probeRequest($coordinator, '/jurnal', ['q' => 'CAP000256'])));
    $run('assessments', fn () => app(AssessmentController::class)->index(probeRequest($coordinator, '/penilaian')));
    $run('certificates', fn () => app(CertificateController::class)->index(probeRequest($coordinator, '/sertifikat')));
    $run('monitoring', fn () => app(MonitoringController::class)->index(probeRequest($coordinator, '/monitoring')));
    $run('main_menu_audit', fn () => json_encode(app(MainMenuAudit::class)->inspect('2026/2027'), JSON_THROW_ON_ERROR));
    $run('journal_workbook_20_rows', function () {
        $book = app(JournalWorkbook::class)->build(Placement::findOrFail(1));
        $path = storage_path('app/private/capacity-journal.xlsx');
        (new Xlsx($book))->save($path);
        $size = filesize($path);
        $sheets = $book->getSheetCount();
        $book->disconnectWorksheets();
        unlink($path);
        probeCheck($size > 5000 && $sheets >= 2, 'Capacity workbook is incomplete.');
        return json_encode(['bytes' => $size, 'sheets' => $sheets], JSON_THROW_ON_ERROR);
    }, 12, 3000);

    $workerReport = [
        'status' => 'passed', 'dataset' => $dataset, 'probes' => $results,
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    ];
    $workerJson = json_encode($workerReport, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    file_put_contents($directory.'/worker-result.json', $workerJson);
    echo $workerJson;
    exit;
}

$source = DB::connection();
$config = $source->getConfig();
$sourceName = $source->getDatabaseName();
probeCheck(app()->environment('local') && $source->getDriverName() === 'mysql'
    && in_array($config['host'], ['localhost', '127.0.0.1', '::1'], true), 'Run only on local MySQL.');
$suffix = bin2hex(random_bytes(8));
$database = 'simmag_capacity_probe_'.$suffix;
$directory = sys_get_temp_dir().'/simmag-capacity-'.$suffix;
probeCheck($database !== $sourceName && ! file_exists($directory) && mkdir($directory, 0700), 'Cannot create isolated capacity context.');
foreach (['app/private', 'framework/views', 'framework/cache', 'framework/sessions', 'logs'] as $path) mkdir($directory.'/storage/'.$path, 0700, true);
file_put_contents($directory.'/owner', $suffix);

$before = probeFingerprint($source);
$created = false;
$report = ['status' => 'failed', 'temporary_database_removed' => false, 'working_database_unchanged' => false];
$env = [
    'SIMMAG_CAPACITY_DIR' => $directory, 'SIMMAG_CAPACITY_DB' => $database,
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
    'DB_URL' => '', 'DB_HOST' => $config['host'], 'DB_PORT' => $config['port'] ?? 3306,
    'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'] ?? '', 'DB_SOCKET' => $config['unix_socket'] ?? '',
    'APP_CONFIG_CACHE' => $directory.'/config.php', 'APP_ROUTES_CACHE' => $directory.'/routes.php',
    'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'BCRYPT_ROUNDS' => '4',
];
try {
    probeCheck((bool) preg_match('/^simmag_capacity_probe_[a-f0-9]{16}$/', $database), 'Unsafe capacity database name.');
    $source->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $process = new Process([PHP_BINARY, __FILE__, 'worker'], base_path(), $env, null, 240);
    $process->run();
    probeCheck($process->isSuccessful(), 'Capacity worker failed: '.trim($process->getErrorOutput()));
    $workerFile = $directory.'/worker-result.json';
    probeCheck(is_file($workerFile), 'Capacity worker returned no JSON report.');
    $worker = json_decode(file_get_contents($workerFile), true, 512, JSON_THROW_ON_ERROR);
    probeCheck(($worker['status'] ?? null) === 'passed', 'Capacity worker did not pass.');
    $report += $worker;
    $report['status'] = 'passed';
} catch (Throwable $exception) {
    $report['error'] = $exception instanceof RuntimeException ? $exception->getMessage() : get_class($exception);
} finally {
    if ($created) {
        probeCheck($database !== $sourceName && preg_match('/^simmag_capacity_probe_[a-f0-9]{16}$/', $database), 'Unsafe cleanup target.');
        try {
            $source->statement('DROP DATABASE `'.$database.'`');
            $report['temporary_database_removed'] = true;
        } catch (Throwable) {
            $report['cleanup_error'] = 'Temporary database was not removed.';
            $report['status'] = 'failed';
        }
    }
    $report['working_database_unchanged'] = probeFingerprint($source) === $before;
    if (! $report['working_database_unchanged']) $report['status'] = 'failed';
    $report['finished_at'] = now('Asia/Jakarta')->toIso8601String();
    file_put_contents($directory.'/result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}

echo ($report['status'] === 'passed' ? 'PASS' : 'FAIL').': capacity probe'.PHP_EOL;
echo 'Report: '.$directory.'/result.json'.PHP_EOL;
exit($report['status'] === 'passed' ? 0 : 1);

