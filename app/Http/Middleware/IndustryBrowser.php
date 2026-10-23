<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class IndustryBrowser
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(!app()->environment(['local','testing']) && !$request->isSecure(),400,'Kiosk harus menggunakan HTTPS.');
        $response=$next($request);
        $response->headers->set('Cache-Control','no-store, private');
        $response->headers->set('Referrer-Policy','no-referrer');
        $response->headers->set('X-Robots-Tag','noindex, nofollow');
        $response->headers->set('X-Frame-Options','DENY');
        return $response;
    }
}
