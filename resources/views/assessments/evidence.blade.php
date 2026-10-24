@extends('layouts.app')
@section('title','Bukti Nilai Industri')
@section('subtitle','Dokumen sumber penilaian; lampiran tidak mengubah nilai atau mengesahkan sertifikat.')
@section('breadcrumb','Operasional / Penilaian / Bukti')
@section('content')
<p><a class="btn btn-ghost" href="{{ route('assessments.index',['q'=>$assessment->placement->student->nis,'period_id'=>$assessment->placement->internship_period_id]) }}">Kembali ke penilaian siswa</a></p>
<div class="card"><h2>{{ $assessment->placement->student->user->name }}</h2>
<p>NIS {{ $assessment->placement->student->nis }} · {{ $assessment->placement->period->academic_year }}<br>{{ $assessment->placement->roster?->class_name ?? '—' }} · {{ $assessment->placement->roster?->major ?? 'Roster belum terhubung' }}</p>
<p>Penilai industri: {{ $assessment->industry_assessor ?? 'Skema lama' }}<br>Nilai resmi: {{ $assessment->final_score ?? 'Belum diisi' }} · Versi penilaian {{ $assessment->revision ?? 0 }} · {{ ['draft'=>'Draf','submitted'=>'Menunggu validasi','revision'=>'Perlu revisi','approved'=>'Disahkan'][$assessment->status] ?? $assessment->status }}</p>
<p class="note-box">Lampiran boleh ditambahkan setelah nilai disahkan sebagai bukti administratif; nilai tetap terkunci. Versi/status saat unggah dicatat apa adanya. Bukti versi lama tidak otomatis dianggap berlaku untuk perubahan nilai berikutnya.</p>
@if(auth()->user()->isRole('koordinator'))
<form method="POST" enctype="multipart/form-data" action="{{ route('assessments.evidence.store',$assessment) }}">@csrf
<input type="hidden" name="revision" value="{{ $assessment->revision ?? 0 }}">
<div class="form-grid"><label>Dokumen nilai industri (PDF/JPG/PNG, maksimal 2 MB)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></label>
<label>Asal dokumen / keterangan<textarea name="note" minlength="10" maxlength="2000" required placeholder="Contoh: lembar nilai diterima dari pembimbing industri tanggal ...">{{ is_string(old('note')) ? old('note') : '' }}</textarea></label></div>
<p class="small muted">Unggah dokumen yang relevan saja. Jika salah, tambahkan bukti pengganti dengan penjelasan; lampiran lama tetap tersimpan. Akses dokumen sumber hanya koordinator/admin.</p>
<button class="btn btn-primary">Unggah Bukti Industri</button></form>@endif</div>
<div class="card"><h2>Riwayat bukti ({{ $items->total() }})</h2>
@forelse($items as $item)<div style="padding:12px 0;border-bottom:1px solid #ddd;overflow-wrap:anywhere">
<strong>{{ $item->original_name }}</strong><p>{{ number_format($item->size/1024,1) }} KB · {{ $item->mime }}<br>Diunggah {{ $item->created_at->format('d/m/Y H:i') }} oleh {{ $item->uploader->name }} (ID {{ $item->uploaded_by }})<br>Versi saat unggah: {{ $item->assessment_revision }} · {{ ['draft'=>'Draf','submitted'=>'Menunggu validasi','revision'=>'Perlu revisi','approved'=>'Disahkan'][$item->assessment_status] ?? $item->assessment_status }}</p>
<p style="white-space:pre-wrap">{{ $item->note }}</p>
<a class="btn btn-secondary" href="{{ route('assessments.evidence.download',$item) }}">Unduh Bukti #{{ $item->id }}</a></div>
@empty<p>Belum ada bukti industri yang dilampirkan. Sistem tidak membuat atau menganggap bukti sudah tersedia.</p>@endforelse
@include('partials.pager')</div>
@endsection
