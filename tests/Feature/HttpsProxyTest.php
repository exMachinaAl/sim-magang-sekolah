<?php
namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HttpsProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'trustedproxy.proxies'=>['127.0.0.1'],'trustedproxy.invalid'=>[],
            'gateway.trusted_hosts'=>['localhost','192.168.0.114','simmag.smkn1cerme.test'],
            'gateway.invalid_hosts'=>[],'gateway.allowed_prefixes'=>['','/simmag'],'gateway.invalid_prefixes'=>[],
            'session.driver'=>'array','cache.default'=>'array',
        ]);
        Route::get('/https-probe',fn(Request $r)=>[
            'secure'=>$r->isSecure(),'ip'=>$r->ip(),'url'=>$r->url(),
        ]);
    }

    public function test_explicit_loopback_proxy_preserves_https_port_and_client_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'192.168.0.114:8443'])
            ->get('http://192.168.0.114:8443/https-probe',[
                'X-Forwarded-Proto'=>'https','X-Forwarded-Port'=>'443','X-Forwarded-Prefix'=>'/simmag',
                'X-Forwarded-For'=>'192.168.0.20','X-Forwarded-Host'=>'simmag.smkn1cerme.test',
            ])->assertOk()->assertExactJson([
                'secure'=>true,'ip'=>'192.168.0.20','url'=>'https://simmag.smkn1cerme.test/simmag/https-probe',
            ]);
    }

    public function test_untrusted_client_cannot_send_forwarded_headers(): void
    {
        $this->withServerVariables(['REMOTE_ADDR'=>'192.168.0.20'])
            ->get('http://localhost/https-probe',[
                'X-Forwarded-Proto'=>'https','X-Forwarded-For'=>'127.0.0.1','X-Forwarded-Prefix'=>'/simmag',
            ])->assertStatus(400);
    }

    public function test_kiosk_requires_real_https_or_trusted_https_proxy_outside_local(): void
    {
        app()->detectEnvironment(fn()=>'production');
        $this->withServerVariables(['REMOTE_ADDR'=>'192.168.0.20'])
            ->get('http://localhost/industry',['X-Forwarded-Proto'=>'https'])->assertStatus(400);
        $this->withServerVariables(['REMOTE_ADDR'=>'127.0.0.1'])
            ->get('http://localhost/industry',['X-Forwarded-Proto'=>'https'])->assertForbidden(); // HTTPS passed; device enrollment still required.
    }
}
