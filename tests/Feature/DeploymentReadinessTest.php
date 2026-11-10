<?php

namespace Tests\Feature;

use App\Support\DeploymentReadiness;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    public function test_local_configuration_is_not_misreported_as_staging_and_json_has_no_secrets(): void
    {
        config([
            'app.env' => 'local',
            'app.debug' => true,
            'app.url' => 'http://localhost:8000',
            'app.key' => 'visible-secret-must-not-be-reported',
            'session.secure' => false,
            'logging.channels.single.level' => 'debug',
        ]);

        $report = app(DeploymentReadiness::class)->inspect('staging');
        $this->assertFalse($report['ready']);
        foreach (['environment', 'debug_disabled', 'application_key', 'https_url', 'secure_cookie', 'database_driver', 'production_log_level'] as $key) {
            $this->assertFalse($report['checks'][$key]['passed'], $key);
        }

        $this->artisan('simmag:check-deployment', ['--target' => 'staging', '--json' => true])->assertExitCode(1);
        $this->assertStringNotContainsString('visible-secret-must-not-be-reported', \Illuminate\Support\Facades\Artisan::output());
        $this->assertStringNotContainsString('DB_PASSWORD', \Illuminate\Support\Facades\Artisan::output());
    }

    public function test_safe_staging_configuration_passes_required_checks_and_keeps_advisories_separate(): void
    {
        $key = base64_encode(str_repeat('k', 32));
        $database = tempnam(storage_path('framework/testing'), 'deployment-');
        config([
            'app.env' => 'staging',
            'app.debug' => false,
            'app.url' => 'https://staging.simmag.example',
            'app.key' => 'base64:'.$key,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'file',
            'cache.default' => 'file',
            'trustedproxy.proxies' => ['127.0.0.1'],
            'trustedproxy.invalid' => [],
            'gateway.trusted_hosts' => ['staging.simmag.example'],
            'gateway.invalid_hosts' => [],
            'gateway.allowed_prefixes' => ['', '/simmag'],
            'gateway.invalid_prefixes' => [],
            'session.domain' => null,
            'session.path' => '/',
            'database.default' => 'mysql',
            // Exercise the connection check on the isolated test database while
            // retaining the deployment-facing connection name expected by SIMMAG.
            'database.connections.mysql' => array_replace(config('database.connections.sqlite'), ['database' => $database]),
            'filesystems.default' => 'local',
            'filesystems.disks.local.root' => storage_path('app/private'),
            'logging.channels.single.level' => 'info',
        ]);
        DB::purge('mysql');
        try {
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $report = app(DeploymentReadiness::class)->inspect('staging');
            $this->assertTrue($report['ready']);
            $this->assertSame(0, $report['required_failures']);
            $this->assertSame(0, $report['advisories']);
            $this->assertTrue($report['checks']['demo_accounts_removed']['passed']);
            $this->assertTrue(collect($report['checks'])->where('level', 'required')->every(fn ($check) => $check['passed']));
            $this->assertStringNotContainsString($key, json_encode($report, JSON_THROW_ON_ERROR));
        } finally {
            DB::purge('mysql');
            if (is_file($database)) unlink($database);
        }
    }

    public function test_ip_host_is_advisory_and_invalid_target_is_rejected(): void
    {
        config(['app.url' => 'https://192.168.1.10']);
        $report = app(DeploymentReadiness::class)->inspect('staging');
        $this->assertTrue($report['checks']['https_url']['passed']);
        $this->assertFalse($report['checks']['fixed_hostname']['passed']);
        $this->artisan('simmag:check-deployment', ['--target' => 'invalid'])->assertExitCode(2);
    }

    public function test_stale_cross_platform_libreoffice_path_is_reported_without_exposing_it(): void
    {
        $secretPath = '/path-tidak-ada/rahasia/soffice.exe';
        config(['certificate_print.libreoffice' => $secretPath]);

        $report = app(DeploymentReadiness::class)->inspect('staging');

        $this->assertFalse($report['checks']['libreoffice_converter']['passed']);
        $this->assertSame('advisory', $report['checks']['libreoffice_converter']['level']);
        $this->assertStringContainsString('path Windows/Linux', $report['checks']['libreoffice_converter']['message']);
        $this->assertStringNotContainsString($secretPath, json_encode($report, JSON_THROW_ON_ERROR));
    }
}

