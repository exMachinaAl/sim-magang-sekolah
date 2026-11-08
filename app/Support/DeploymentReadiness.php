<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeploymentReadiness
{
    public function inspect(string $target): array
    {
        if (! in_array($target, ['staging', 'production'], true)) {
            throw new InvalidArgumentException('Target harus staging atau production.');
        }

        $checks = [];
        $add = function (string $key, string $level, bool $passed, string $message) use (&$checks): void {
            $checks[$key] = compact('level', 'passed', 'message');
        };

        $environment = (string) config('app.env');
        $add('environment', 'required', $environment === $target,
            $environment === $target ? 'Lingkungan sesuai target.' : 'APP_ENV belum sesuai target.');
        $add('debug_disabled', 'required', config('app.debug') === false,
            config('app.debug') === false ? 'Mode debug mati.' : 'APP_DEBUG harus false.');

        $key = (string) config('app.key');
        $decodedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $validKey = is_string($decodedKey) && Encrypter::supported($decodedKey, (string) config('app.cipher'));
        $add('application_key', 'required', $validKey,
            $validKey ? 'Kunci aplikasi valid; nilainya tidak ditampilkan.' : 'APP_KEY kosong atau tidak sesuai cipher.');

        $url = (string) config('app.url');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $https = $scheme === 'https' && is_string($host) && $host !== '' && ! in_array(strtolower($host), ['localhost'], true);
        $add('https_url', 'required', $https,
            $https ? 'APP_URL memakai HTTPS dan host eksplisit.' : 'APP_URL harus HTTPS dan bukan localhost.');
        $fixedHost = is_string($host) && $host !== '' && filter_var($host, FILTER_VALIDATE_IP) === false;
        $add('fixed_hostname', 'advisory', $fixedHost,
            $fixedHost ? 'Hostname tetap tersedia.' : 'Gunakan hostname/domain tetap agar sertifikat dan QR tidak berubah saat IP berganti.');

        $secureCookie = config('session.secure');
        $add('secure_cookie', 'required', $secureCookie === true || $secureCookie === null,
            $secureCookie === true ? 'Cookie sesi selalu Secure.' : ($secureCookie === null
                ? 'Cookie sesi memakai mode auto: Secure pada HTTPS dan tetap berfungsi pada HTTP lokal.'
                : 'SESSION_SECURE_COOKIE harus true atau auto.'));
        $add('http_only_cookie', 'required', config('session.http_only') === true,
            config('session.http_only') === true ? 'Cookie sesi HttpOnly.' : 'Cookie sesi wajib HttpOnly.');
        $add('same_site_cookie', 'required', in_array(config('session.same_site'), ['lax', 'strict'], true),
            'SameSite cookie harus lax atau strict.');
        $add('host_only_cookie', 'required', empty(config('session.domain')),
            empty(config('session.domain')) ? 'Cookie host-only mendukung subdomain dan tunnel yang berubah.' : 'SESSION_DOMAIN harus kosong untuk deployment multi-host.');
        $add('root_cookie_path', 'required', config('session.path') === '/',
            config('session.path') === '/' ? 'Cookie tersedia pada root dan /simmag.' : 'Path cookie harus / agar kedua mode gateway berfungsi.');
        $add('persistent_session', 'required', ! in_array(config('session.driver'), [null, 'array'], true),
            'Session staging/produksi tidak boleh memakai driver array.');
        $add('persistent_cache', 'required', ! in_array(config('cache.default'), [null, 'array'], true),
            'Cache staging/produksi tidak boleh memakai driver array.');

        $proxies = config('trustedproxy.proxies', []);
        $validProxies = is_array($proxies) && $proxies !== [] && empty(config('trustedproxy.invalid', []));
        $add('trusted_proxies', 'required', $validProxies,
            $validProxies ? 'Daftar Nginx gateway hanya berisi IP/CIDR eksplisit.' : 'TRUSTED_PROXIES harus memuat IP/CIDR Nginx gateway yang valid.');
        $add('proxy_plan', 'advisory', ! empty($proxies),
            ! empty($proxies) ? 'Proxy HTTPS eksplisit dikonfigurasi.' : 'Tanpa proxy tepercaya, TLS harus berakhir langsung pada server aplikasi.');
        $gatewayHosts = config('gateway.trusted_hosts', []);
        $gatewayPrefixes = config('gateway.allowed_prefixes', []);
        $validGatewayHosts = is_array($gatewayHosts) && $gatewayHosts !== [] && empty(config('gateway.invalid_hosts', []));
        $add('gateway_hosts', 'required', $validGatewayHosts,
            $validGatewayHosts ? 'Allowlist hostname gateway tersedia dan valid.' : 'GATEWAY_TRUSTED_HOSTS kosong atau mengandung pola tidak valid.');
        $dualPrefix = is_array($gatewayPrefixes) && in_array('', $gatewayPrefixes, true) && in_array('/simmag', $gatewayPrefixes, true);
        $dualPrefix = $dualPrefix && empty(config('gateway.invalid_prefixes', []));
        $add('gateway_dual_prefix', 'required', $dualPrefix,
            $dualPrefix ? 'Mode root dan /simmag diizinkan.' : 'GATEWAY_ALLOWED_PREFIXES harus memuat / dan /simmag.');

        $driver = (string) config('database.default');
        $add('database_driver', 'required', $driver === 'mysql',
            $driver === 'mysql' ? 'Database memakai MySQL.' : 'Deployment SIMMAG ini harus memakai koneksi MySQL.');
        try {
            DB::connection()->getPdo();
            $databaseConnected = true;
        } catch (\Throwable) {
            $databaseConnected = false;
        }
        $add('database_connection', 'required', $databaseConnected,
            $databaseConnected ? 'Koneksi database berhasil.' : 'Koneksi database gagal.');
        $schemaReady = false;
        $migrationsCurrent = false;
        $demoAccounts = null;
        if ($databaseConnected) {
            try {
                $schema = DB::connection()->getSchemaBuilder();
                $schemaReady = collect(['users', 'internship_rosters', 'journals', 'certificates'])
                    ->every(fn ($table) => $schema->hasTable($table));
                $migrator = app('migrator');
                if ($migrator->repositoryExists()) {
                    $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
                    $ran = $migrator->getRepository()->getRan();
                    $migrationsCurrent = array_diff($files, $ran) === [];
                }
                if ($schema->hasTable('users')) {
                    $demoAccounts = DB::table('users')->where('email', 'like', '%@simmag.test')->count();
                }
            } catch (\Throwable) {
                $schemaReady = false;
                $migrationsCurrent = false;
            }
        }
        $add('database_schema', 'required', $schemaReady,
            $schemaReady ? 'Tabel inti tersedia.' : 'Skema database inti belum lengkap.');
        $add('migrations_current', 'required', $migrationsCurrent,
            $migrationsCurrent ? 'Tidak ada migrasi aplikasi yang tertunda.' : 'Jalankan dan periksa migrasi sebelum menerima trafik.');
        $demoLevel = $target === 'production' ? 'required' : 'advisory';
        $noDemo = $demoAccounts === 0;
        $add('demo_accounts_removed', $demoLevel, $noDemo,
            $noDemo ? 'Akun domain demo tidak ditemukan.' : ($target === 'production'
                ? 'Akun domain demo harus dihapus sebelum produksi.'
                : 'Akun demo masih ada; boleh untuk staging terbatas, jangan gunakan data asli.'));

        $root = (string) config('filesystems.disks.local.root');
        $expectedRoot = storage_path('app/private');
        $normalize = fn (string $path) => strtolower(str_replace('\\', '/', rtrim($path, '/\\')));
        $privateRoot = $normalize($root) === $normalize($expectedRoot)
            && ! str_starts_with($normalize($root), $normalize(public_path()).'/');
        $add('private_upload_storage', 'required', config('filesystems.default') === 'local' && $privateRoot,
            $privateRoot ? 'Upload utama berada di storage privat.' : 'FILESYSTEM_DISK/local harus menunjuk storage/app/private, bukan public.');
        $writable = is_dir($expectedRoot) && is_writable($expectedRoot)
            && is_dir(storage_path('framework')) && is_writable(storage_path('framework'))
            && is_dir(storage_path('logs')) && is_writable(storage_path('logs'));
        $add('writable_runtime', 'required', $writable,
            $writable ? 'Storage privat, framework, dan log dapat ditulis.' : 'Izin tulis storage runtime belum benar.');

        $logLevel = strtolower((string) config('logging.channels.single.level'));
        $add('production_log_level', 'required', ! in_array($logLevel, ['', 'debug'], true),
            ! in_array($logLevel, ['', 'debug'], true) ? 'Level log tidak debug.' : 'LOG_LEVEL harus info, warning, atau lebih ketat.');

        $requiredFailures = collect($checks)->where('level', 'required')->where('passed', false)->count();
        $advisories = collect($checks)->where('level', 'advisory')->where('passed', false)->count();

        return [
            'target' => $target,
            'ready' => $requiredFailures === 0,
            'required_failures' => $requiredFailures,
            'advisories' => $advisories,
            'checks' => $checks,
        ];
    }
}

