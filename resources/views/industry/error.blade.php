@extends('industry.layout')
@section('content')
<div class="card" role="alert">
<h1>Permintaan belum bisa diproses</h1>
<p>{{ $explanation }}</p>
<p class="small muted">Kode {{ $status }}. Pesan ini bukan bukti validasi. Periksa riwayat pada perangkat atau status jurnal siswa sebelum mencoba lagi.</p>
<a class="btn btn-secondary" href="{{ route('industry.home') }}">Lihat status perangkat & riwayat</a>
</div>
@endsection
