@extends('layouts.app')
@section('title','QR Pendaftaran Perangkat Industri')
@section('content')
<div class="card"><h2>{{ $mentor->name }} · {{ $mentor->company->name }}</h2>
<p>Tunjukkan QR ini langsung kepada pembimbing. Dodi membuka kamera, memindai QR, lalu membuka link. Browser tersebut otomatis didaftarkan tanpa PIN.</p>
<img src="{{ $image }}" width="320" height="320" alt="QR pendaftaran perangkat pembimbing" style="max-width:100%;height:auto">
<p>QR pendaftaran sekali pakai · berlaku 24 jam. Siapa pun yang memperoleh QR sebelum dipakai dapat mendaftarkan perangkat sebagai pembimbing ini. Jangan kirim ke siswa atau grup umum.</p>
<p class="note-box">Alamat aplikasi pada QR: {{ parse_url($url,PHP_URL_HOST) }}. HP pembimbing harus bisa membuka alamat ini. localhost / 127.0.0.1 tidak bisa dipakai dari HP lain. Gunakan host jaringan/HTTPS yang dapat diakses.</p>
<a class="btn btn-secondary" href="{{ route('journals.mentors') }}">Kembali ke pembimbing & perangkat</a></div>
@endsection
