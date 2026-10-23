@extends('industry.layout')
@section('content')
<div class="card"><h1>Perangkat {{ $device->mentor->name }}</h1><p>{{ $device->mentor->company->name }}</p><p>Terdaftar sampai {{ $device->expires_at->format('d/m/Y') }}. Untuk memvalidasi, pindai QR jurnal yang ditampilkan siswa melalui kamera, lalu buka link pada browser ini.</p><p>Anda hanya dapat memvalidasi siswa yang ditugaskan koordinator. Tidak ada kewajiban scan setiap hari.</p></div>
<div class="card"><h2>10 validasi terakhir perangkat ini</h2>
@forelse($recent as $validation)
<p><strong>{{ $validation->snapshot['student_name'] }}</strong> · NIS {{ $validation->snapshot['nis'] }}<br>
Kegiatan {{ $validation->snapshot['activity_date'] }} · nilai {{ $validation->snapshot['industry_score'] }} · versi {{ $validation->revision }}<br>
Scan: {{ $validation->scanned_at->format('d/m/Y H:i:s') }} WIB</p>
@empty<p>Belum ada jurnal yang dipindai.</p>@endforelse
</div>
@endsection
