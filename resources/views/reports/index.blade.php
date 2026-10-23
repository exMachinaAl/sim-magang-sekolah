@extends('layouts.app')
@section('title','Laporan & Audit Log')
@section('breadcrumb','Manajemen / Laporan')
@section('subtitle','Unduh rekap dan telusuri aktivitas penting di dalam sistem.')
@section('content')
<div class="card"><div class="card-head"><div><h2>Ekspor Data CSV</h2><p>Berkas CSV dapat dibuka melalui Excel atau Google Sheets.</p></div></div>
<div class="three-cols">@foreach(['placements'=>'Penempatan PKL','journals'=>'Jurnal Harian','assessments'=>'Nilai Siswa','certificates'=>'Sertifikat'] as $key=>$label)<a class="btn btn-secondary" href="{{ route('reports.export',$key) }}">↓ {{ $label }}</a>@endforeach</div></div>
<div class="card"><div class="card-head"><h2>Riwayat Aktivitas</h2><span class="small muted">{{ $audits->total() }} aktivitas</span></div><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Pelaku</th><th>Tindakan</th><th>Objek</th><th>Ringkasan</th></tr></thead><tbody>
@forelse($audits as $log)<tr><td>{{ $log->created_at?->format('d M Y H:i') }}</td><td>{{ $log->actor?->name??'Sistem' }}</td><td><strong>{{ $log->action }}</strong></td><td>{{ $log->subject_type }} #{{ $log->subject_id }}</td><td class="small muted">{{ \Illuminate\Support\Str::limit(json_encode($log->context,JSON_UNESCAPED_UNICODE),110) }}</td></tr>
@empty<tr><td colspan="5"><div class="empty">Belum ada aktivitas yang tercatat.</div></td></tr>@endforelse</tbody></table></div>
@if($audits->hasPages())<div class="pagination">Halaman {{ $audits->currentPage() }} / {{ $audits->lastPage() }}
@if($audits->previousPageUrl())<a class="btn btn-sm btn-ghost" href="{{ $audits->previousPageUrl() }}">← Kembali</a>@endif
@if($audits->nextPageUrl())<a class="btn btn-sm btn-ghost" href="{{ $audits->nextPageUrl() }}">Selanjutnya →</a>@endif</div>@endif</div>
@endsection
