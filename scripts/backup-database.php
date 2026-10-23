<?php
// Local safety snapshot before a user-requested database reset. Credentials are never printed.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('local') || config('database.default') !== 'mysql') {
    throw new RuntimeException('This helper only backs up a local MySQL database.');
}
$connection = Illuminate\Support\Facades\DB::connection();
$config = $connection->getConfig();
$directory = storage_path('app/private/backups');
if (! is_dir($directory)) mkdir($directory, 0700, true);
$file = $directory.'/before-pkl-tags-'.now('Asia/Jakarta')->format('Ymd-His').'.sql';
$binary = $argv[1] ?? 'mysqldump';
$process = new Symfony\Component\Process\Process([
    $binary, '--host='.$config['host'], '--port='.($config['port'] ?? 3306),
    '--user='.$config['username'], '--single-transaction', '--no-tablespaces',
    '--set-gtid-purged=OFF', '--result-file='.$file, $connection->getDatabaseName(),
], null, ['MYSQL_PWD' => $config['password'] ?? '']);
$process->setTimeout(120);
$process->mustRun();
if (! is_file($file) || filesize($file) < 100) throw new RuntimeException('Backup file is empty.');
echo 'Backup: '.$file.PHP_EOL.'Bytes: '.filesize($file).PHP_EOL;
