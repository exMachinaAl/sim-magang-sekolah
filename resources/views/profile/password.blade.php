@extends('layouts.app')
@section('title','Ubah Kata Sandi')
@section('breadcrumb','Profil / Keamanan')
@section('subtitle','Lindungi akun Anda dengan kata sandi yang kuat dan unik.')
@section('content')<div class="card" style="max-width:670px"><div class="card-head"><h2>Perbarui Kata Sandi</h2></div>
<form method="POST" action="{{ route('profile.password') }}">@csrf @method('PUT')
<div class="stack"><div class="form-field"><label>Kata Sandi Saat Ini</label><input type="password" name="current_password" required autocomplete="current-password"></div>
<div class="form-field"><label>Kata Sandi Baru</label><input type="password" name="password" minlength="10" required autocomplete="new-password"><div class="field-help">Gunakan minimal 10 karakter, gabungkan angka, huruf, dan simbol.</div></div>
<div class="form-field"><label>Konfirmasi Kata Sandi Baru</label><input type="password" name="password_confirmation" minlength="10" required autocomplete="new-password"></div></div>
<div class="form-footer"><button class="btn btn-primary">Perbarui Kata Sandi</button></div></form></div>@endsection
