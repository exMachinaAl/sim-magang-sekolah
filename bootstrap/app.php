<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureActiveAccount;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withCommands([\App\Console\Commands\AuditMainMenu::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class, 'active' => EnsureActiveAccount::class]);
        $middleware->trustProxies(headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            if (! $request->is('industry', 'industry/*')) return null;
            $http=$exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
            $status=$http ? $exception->getStatusCode() : 500;
            // Static explanations: never expose tokens, SQL, stack traces or student data on kiosk errors.
            $messages=[
                400=>'Gunakan alamat HTTPS resmi sekolah untuk membuka kiosk.',
                403=>'Browser belum dipercaya atau siswa bukan penugasan pembimbing ini. Gunakan browser yang didaftarkan; hubungi koordinator bila perlu.',
                404=>'QR atau perangkat tidak ditemukan, dicabut, atau sudah kedaluwarsa. Hubungi koordinator untuk memeriksa perangkat.',
                405=>'Cara membuka tautan tidak didukung. Buka kembali tautan QR melalui browser.',
                409=>'Isi jurnal sudah berubah. Minta siswa menampilkan QR dari versi jurnal terbaru.',
                410=>'QR sudah digunakan atau kedaluwarsa. Untuk pendaftaran, minta QR baru kepada koordinator; untuk jurnal, minta siswa membuat QR baru.',
                419=>'Sesi browser berakhir. Buka ulang tautan QR dan izinkan cookie pada browser yang sama.',
                422=>'Data belum lengkap atau tidak sesuai. Periksa jurnal bersama siswa sebelum memindai ulang.',
                429=>'Terlalu banyak permintaan. Tunggu sebentar sebelum membuka ulang QR.',
            ];
            return response()->view('industry.error', ['status'=>$status, 'explanation'=>$messages[$status] ?? 'Layanan belum dapat memproses permintaan. Coba lagi nanti atau hubungi koordinator.'], $status, $http ? $exception->getHeaders() : [])
                ->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer')
                ->header('X-Robots-Tag','noindex, nofollow')->header('X-Frame-Options','DENY');
        });
    })
    ->create();
