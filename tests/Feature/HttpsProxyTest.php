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
        config(['trustedproxy.proxies'=>['127.0.0.1'],'session.driver'=>'array','cache.default'=>'array']);
        Route::get('/https-probe',fn(Request $r)=>[
            'secure'=>$r->isSecure(),'ip'=>$r->ip(),'url'=>$r->url(),
        ]);
    }

    public function test_explicit_loopback_proxy_preserves_https_port_and_client_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'192.168.0.114:8443'])
            ->get('http://192.168.0.114:8443/https-probe',[
                'X-Forwarded-Proto'=>'https','X-Forwarded-Port'=>'8443',
                'X-Forwarded-For'=>'192.168.0.20','X-Forwarded-Host'=>'untrusted.example',
            ])->assertOk()->assertExactJson([
                'secure'=>true,'ip'=>'192.168.0.20','url'=>'https://192.168.0.114:8443/https-probe',
            ]);
    }

    public function test_untrusted_client_cannot_spoof_https_or_client_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR'=>'192.168.0.20'])
            ->get('http://localhost/https-probe',[
                'X-Forwarded-Proto'=>'https','X-Forwarded-For'=>'127.0.0.1',
            ])->assertOk()->assertJson(['secure'=>false,'ip'=>'192.168.0.20']);
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
