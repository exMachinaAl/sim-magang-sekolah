<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk | SIMMAG</title><link rel="stylesheet" href="{{ asset('css/simmag.css') }}"></head>
<body class="login-body"><div class="login-grid"><div class="login-intro"><div class="login-brand"><span class="brand-mark">S</span><span>SIMMAG <small>SMKN 1 CERME GRESIK</small></span></div>
<span class="hero-pill">Platform PKL Terintegrasi</span><h1>Kelola magang,<br><em>lebih mudah.</em></h1><p>Satu tempat untuk penempatan siswa, jurnal harian, monitoring, penilaian, dan penerbitan sertifikat Praktik Kerja Lapangan.</p>
<div class="login-features"><span>✓ Jurnal & bukti foto</span><span>✓ Monitoring pembimbing</span><span>✓ Validasi sertifikat</span></div></div>
<div class="login-side"><div class="login-card"><div class="login-icon">↗</div><h2>Selamat datang!</h2><p>Silakan masuk menggunakan akun SIMMAG Anda.</p>
@if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.attempt') }}">@csrf
<label for="login">NIS siswa / email staf</label><input id="login" type="text" name="login" value="{{ old('login',old('email')) }}" placeholder="Masukkan NIS atau email" autocomplete="username" required autofocus>
<label for="password">Kata sandi</label><input id="password" type="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
<button type="submit" class="btn btn-primary btn-block">Masuk ke SIMMAG <span>→</span></button></form>
<div class="login-help">Akun belum tersedia? Hubungi administrator sekolah.</div></div><div class="login-copy">© {{ date('Y') }} SMKN 1 Cerme Gresik</div></div></div></body></html>
