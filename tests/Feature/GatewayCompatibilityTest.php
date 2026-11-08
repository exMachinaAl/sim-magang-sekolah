<?php

namespace Tests\Feature;

use App\Models\{Journal, Placement, User};
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class GatewayCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
        config([
            'trustedproxy.proxies' => ['127.0.0.1'],
            'trustedproxy.invalid' => [],
            'gateway.trusted_hosts' => [
                'localhost', 'app.internal', 'smkn1cerme.test', '*.smkn1cerme.test', '*.example-tunnel.test',
            ],
            'gateway.invalid_hosts' => [],
            'gateway.allowed_prefixes' => ['', '/simmag'],
            'gateway.invalid_prefixes' => [],
            'session.secure' => null,
            'session.domain' => null,
            'session.path' => '/',
        ]);
    }

    private function server(string $host = 'app.internal', string $remote = '127.0.0.1'): static
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => $remote,
            'HTTP_HOST' => $host,
            'SERVER_PORT' => '8080',
        ]);
    }

    private function forwarded(string $host, string $prefix = '', string $scheme = 'http'): array
    {
        return array_filter([
            'X-Forwarded-For' => '192.0.2.25',
            'X-Forwarded-Host' => $host,
            'X-Forwarded-Proto' => $scheme,
            'X-Forwarded-Port' => $scheme === 'https' ? '443' : '80',
            'X-Forwarded-Prefix' => $prefix === '' ? null : $prefix,
        ], static fn ($value): bool => $value !== null);
    }

    public function test_subdomain_and_base_path_generate_correct_assets_forms_and_nested_links(): void
    {
        $subdomain = $this->server()->get('http://app.internal/login', $this->forwarded('simmag.smkn1cerme.test'));
        $subdomain->assertOk()
            ->assertSee('href="http://simmag.smkn1cerme.test/css/simmag.css"', false)
            ->assertSee('action="http://simmag.smkn1cerme.test/login"', false);

        $basePath = $this->server()->get('http://app.internal/login', $this->forwarded('smkn1cerme.test', '/simmag'));
        $basePath->assertOk()
            ->assertSee('href="http://smkn1cerme.test/simmag/css/simmag.css"', false)
            ->assertSee('action="http://smkn1cerme.test/simmag/login"', false);

        $root = $this->server()->get('http://app.internal/', $this->forwarded('smkn1cerme.test', '/simmag'));
        $root->assertRedirect('http://smkn1cerme.test/simmag/dashboard');

        $user = User::where('role', 'koordinator')->firstOrFail();
        $this->actingAs($user);
        $nested = $this->server()->get('http://app.internal/sertifikat', $this->forwarded('smkn1cerme.test', '/simmag'));
        $nested->assertOk()
            ->assertSee('href="http://smkn1cerme.test/simmag/dashboard"', false)
            ->assertSee('action="http://smkn1cerme.test/simmag/logout"', false);

        $this->server()->post('http://app.internal/logout', [], $this->forwarded('smkn1cerme.test', '/simmag'))
            ->assertRedirect('http://smkn1cerme.test/simmag/login');
        $this->assertGuest();
    }

    public function test_https_tunnel_host_is_runtime_driven_and_login_redirect_keeps_prefix(): void
    {
        $host = 'random-20261109.example-tunnel.test';
        $headers = $this->forwarded($host, '/simmag', 'https');

        $page = $this->server()->get('http://app.internal/login', $headers);
        $page->assertOk()
            ->assertSee('href="https://'.$host.'/simmag/css/simmag.css"', false)
            ->assertSee('action="https://'.$host.'/simmag/login"', false);

        $login = $this->server()->post('http://app.internal/login', [
            'login' => 'koordinator@simmag.test',
            'password' => 'password123',
        ], $headers);
        $login->assertRedirect('https://'.$host.'/simmag/dashboard');

        $cookie = collect($login->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
    }

    public function test_http_local_session_stays_usable_while_https_cookie_is_secure(): void
    {
        $headers = $this->forwarded('smkn1cerme.test', '/simmag', 'http');
        $login = $this->server()->post('http://app.internal/login', [
            'login' => 'koordinator@simmag.test',
            'password' => 'password123',
        ], $headers)->assertRedirect('http://smkn1cerme.test/simmag/dashboard');

        $cookie = collect($login->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertFalse($cookie->isSecure());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
    }

    public function test_untrusted_proxy_headers_hosts_and_prefixes_are_rejected(): void
    {
        $this->server('smkn1cerme.test', '198.51.100.20')
            ->get('http://smkn1cerme.test/login', $this->forwarded('evil.example', '/simmag', 'https'))
            ->assertStatus(400);

        $this->server('smkn1cerme.test', '198.51.100.20')
            ->get('http://smkn1cerme.test/login')
            ->assertOk()
            ->assertSee('action="http://smkn1cerme.test/login"', false);

        $this->server('evil.example')->get('http://evil.example/login')->assertStatus(400);
        $this->server()->get('http://app.internal/login', $this->forwarded('evil.example'))->assertStatus(400);
        $this->server()->get('http://app.internal/login', $this->forwarded('smkn1cerme.test', '/admin'))->assertStatus(400);
        $this->server()->get('http://app.internal/login', $this->forwarded('smkn1cerme.test', '/simmag,/evil'))->assertStatus(400);
        $this->server()->get('http://app.internal/login', $this->forwarded('smkn1cerme.test', '', 'ftp'))->assertStatus(400);
        $this->server()->get('http://app.internal/login', array_merge(
            $this->forwarded('smkn1cerme.test'), ['X-Forwarded-Port' => '70000']
        ))->assertStatus(400);
    }

    public function test_prefixed_upload_redirect_and_role_authorization_preserve_existing_rules(): void
    {
        $placement = Placement::whereHas('roster', fn ($query) => $query
            ->whereNotNull('internship_starts_at')->whereNotNull('internship_ends_at'))
            ->with(['student.user', 'roster'])->firstOrFail();
        $student = $placement->student->user;
        $date = $placement->roster->internship_starts_at->toDateString();
        $headers = $this->forwarded('smkn1cerme.test', '/simmag', 'https');
        $attachmentsBefore = \DB::table('journal_attachments')->count();

        $this->actingAs($student);
        $response = $this->server()->post('http://app.internal/jurnal', [
            'placement_id' => $placement->id,
            'activity_date' => $date,
            'activity' => 'Uji upload di belakang gateway',
            'intent' => 'draft',
            'photos' => [UploadedFile::fake()->image('gateway.png', 320, 240)],
        ], $headers);
        $response->assertSessionHasNoErrors()
            ->assertRedirect('https://smkn1cerme.test/simmag/jurnal/penempatan/'.$placement->id);
        $this->assertDatabaseCount('journal_attachments', $attachmentsBefore + 1);

        $this->server()->get('http://app.internal/siswa', $headers)->assertForbidden();
    }

    public function test_kiosk_qr_callback_uses_runtime_https_host_and_prefix(): void
    {
        $journal = Journal::whereNotNull('industry_score')
            ->where('status', '!=', 'draft')
            ->with('placement.student.user')
            ->firstOrFail();
        $host = 'rotating-host.example-tunnel.test';

        $response = $this->actingAs($journal->placement->student->user)
            ->server()
            ->post('http://app.internal/jurnal/'.$journal->id.'/qr', [], $this->forwarded($host, '/simmag', 'https'));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '#^https://'.preg_quote($host, '#').'/simmag/industry/scan/[A-Za-z0-9]{64}$#',
            $response->viewData('scanUrl'),
        );
    }

    public function test_csrf_contract_still_accepts_valid_token_and_rejects_invalid_token(): void
    {
        $session = app('session')->driver();
        $session->start();
        $middleware = new class(app(), app('encrypter')) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        $valid = Request::create('/login', 'POST', ['_token' => $session->token()]);
        $valid->setLaravelSession($session);
        $this->assertSame(200, $middleware->handle($valid, fn () => response('ok'))->getStatusCode());

        $invalid = Request::create('/login', 'POST', ['_token' => 'token-salah']);
        $invalid->setLaravelSession($session);
        $this->expectException(TokenMismatchException::class);
        $middleware->handle($invalid, fn () => response('tidak boleh tercapai'));
    }
}
