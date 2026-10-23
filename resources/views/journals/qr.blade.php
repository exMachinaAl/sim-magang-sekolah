@extends('layouts.app')
@section('title','Setoran Jurnal ke Industri')
@section('content')
<div class="card">
<h2>{{ $journal->placement->student->user->name }} · NIS {{ $journal->placement->student->nis }}</h2>
<p>{{ $journal->placement->company->name }} · kegiatan {{ $journal->activity_date->format('d/m/Y') }} · versi {{ $journal->revision }}</p>
<p>Ruang {{ $journal->work_area ?: '—' }} · {{ $journal->starts_time ?: '—' }} – {{ $journal->ends_time ?: '—' }}</p>
<h3>Kegiatan</h3><p style="white-space:pre-wrap">{{ $journal->activity }}</p>
<p>Peralatan/bahan: {{ $journal->equipment_notes ?: '—' }}</p><p>Hasil: {{ $journal->outcome ?: '—' }} · Kendala: {{ $journal->obstacles ?: '—' }}</p>
<h2>Nilai {{ $journal->industry_score }} · {{ $journal->scoreCategory() }}</h2>
<p>Tunjukkan isi dan nilai ini kepada pembimbing. Jika sudah benar, pembimbing memindai QR memakai kamera HP yang browsernya sudah didaftarkan. Link yang dibuka akan memvalidasi otomatis.</p>
<img src="{{ $image }}" width="320" height="320" alt="QR validasi jurnal versi {{ $journal->revision }}" style="max-width:100%;height:auto">
<p>QR berlaku 24 jam. Jurnal hari lalu tetap dapat divalidasi hari ini. Jika isi diedit, tampilkan QR baru. Scan bukan syarat untuk menyimpan atau mengekspor jurnal.</p>
<a href="{{ route('journals.show',$journal->placement) }}" class="btn btn-secondary">Kembali / cek status validasi</a>
</div>
@endsection
