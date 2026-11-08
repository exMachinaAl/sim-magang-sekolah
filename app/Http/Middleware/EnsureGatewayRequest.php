<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGatewayRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $forwardedHeaders = [
            'Forwarded',
            'X-Forwarded-For',
            'X-Forwarded-Host',
            'X-Forwarded-Port',
            'X-Forwarded-Proto',
            'X-Forwarded-Prefix',
        ];
        $hasForwardedHeaders = collect($forwardedHeaders)
            ->contains(fn (string $header): bool => $request->headers->has($header));

        abort_if($hasForwardedHeaders && ! $request->isFromTrustedProxy(), 400,
            'Header proxy hanya boleh dikirim oleh gateway tepercaya.');
        abort_if($request->headers->has('Forwarded'), 400, 'Header Forwarded tidak didukung.');

        if ($request->isFromTrustedProxy()) {
            $this->validateForwardedMetadata($request);
        }

        abort_unless($this->hostIsTrusted($request->getHost()), 400, 'Hostname aplikasi tidak dipercaya.');

        return $next($request);
    }

    private function validateForwardedMetadata(Request $request): void
    {
        $host = $request->headers->get('X-Forwarded-Host');
        abort_if($host !== null && (str_contains($host, ',') || preg_match('/[\r\n]/', $host)), 400,
            'Host gateway tidak valid.');

        $proto = strtolower((string) $request->headers->get('X-Forwarded-Proto', ''));
        abort_if($proto !== '' && ! in_array($proto, ['http', 'https'], true), 400,
            'Protokol gateway tidak valid.');

        $port = (string) $request->headers->get('X-Forwarded-Port', '');
        abort_if($port !== '' && (! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535), 400,
            'Port gateway tidak valid.');

        $header = $request->headers->get('X-Forwarded-Prefix');
        if ($header === null) {
            return;
        }

        // Multiple values or encoded/query fragments make the public mount
        // ambiguous and must never influence generated links.
        abort_if(str_contains($header, ',') || str_contains($header, '?') || str_contains($header, '#'), 400,
            'Prefix gateway tidak valid.');

        $header = trim($header);
        $prefix = in_array($header, ['', '/'], true) ? '' : '/'.trim($header, '/');
        abort_unless(($prefix === '' || preg_match('#^/[A-Za-z0-9._~-]+(?:/[A-Za-z0-9._~-]+)*$#', $prefix) === 1)
            && in_array($prefix, config('gateway.allowed_prefixes', ['', '/simmag']), true), 400,
            'Prefix gateway tidak diizinkan.');
    }

    private function hostIsTrusted(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        foreach (config('gateway.trusted_hosts', []) as $pattern) {
            $pattern = strtolower(rtrim((string) $pattern, '.'));
            if ($pattern === '') {
                continue;
            }
            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1);
                if ($host !== substr($suffix, 1) && str_ends_with($host, $suffix)) {
                    return true;
                }
                continue;
            }
            if (hash_equals($pattern, $host)) {
                return true;
            }
        }

        return false;
    }
}
