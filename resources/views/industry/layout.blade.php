<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Kiosk Pembimbing Industri · SIMMAG</title><link rel="stylesheet" href="{{ asset('css/simmag.css') }}"></head>
<body><main style="max-width:700px;margin:30px auto;padding:16px">
<p class="eyebrow">SIMMAG · Pembimbing Industri</p>
@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
@yield('content')
<p class="small muted">Perangkat terpercaya adalah browser ini, bukan identifikasi perangkat keras. Gunakan browser yang sama saat membuka QR melalui kamera. Jangan bagikan QR pendaftaran atau cookie perangkat.</p>
</main>@stack('scripts')</body></html>
