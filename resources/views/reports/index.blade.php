@extends('layouts.app')
@section('title','Laporan & Audit Log')
@section('breadcrumb','Manajemen / Laporan')
@section('subtitle','Unduh rekap dan telusuri aktivitas penting di dalam sistem.')
@section('content')
<div class="card"><div class="card-head"><div><h2>Ekspor Data CSV</h2><p>Berkas CSV dapat dibuka melalui Excel atau Google Sheets.</p></div></div>
<form method="get" action="{{ route('reports.index') }}" class="form-grid">
<div class="form-field"><label for="report-period">Tag tahun ajaran</label><select id="report-period" name="period_id">@forelse($periods as $period)<option value="{{ $period->id }}" @selected($chosen===$period->id)>{{ $period->academic_year }}</option>@empty<option value="">Belum ada tahun ajaran</option>@endforelse</select></div>
<div class="form-field"><label for="report-major">Jurusan (roster)</label><select id="report-major" name="major"><option value="">Seluruh jurusan</option>@foreach($majors as $option)<option @selected($major===$option)>{{ $option }}</option>@endforeach</select></div>
<div class="form-footer"><button class="btn btn-primary" type="submit">Terapkan filter</button><a class="btn btn-ghost" href="{{ route('reports.index') }}">Reset</a></div>
</form>
<p class="note-box">Hasil filter: <strong>{{ $rosterCount }} siswa dalam roster</strong> dan <strong>{{ $placementCount }} penempatan</strong>. Pilih <strong>Seluruh Siswa / Roster</strong> untuk mendata sekolah, termasuk siswa yang belum ditempatkan dan tidak magang. Ekspor lain berisi catatan yang sudah ada pada penempatan.</p>
<div class="header-actions">@foreach($types as $key=>$label)<a class="btn btn-secondary" href="{{ route('reports.export',['type'=>$key,'period_id'=>$chosen?:null,'major'=>$major]) }}">↓ {{ $label }}</a>@endforeach</div>
<p class="small muted" style="margin-top:14px">Terapkan filter sebelum mengunduh. Fase mengikuti tanggal siswa di Kontrol PKL pada hari ekspor (WIB); masuk sekolah bukan bukti kehadiran. NIS/NISN diberi awalan apostrof agar terbaca sebagai teks. CSV ini untuk rekap, bukan template impor.</p></div>
<p class="small muted">Riwayat aktivitas di bawah tetap mencakup seluruh tahun dan jurusan; filter di atas hanya berlaku untuk ekspor.</p>
<div class="card"><div class="card-head"><h2>Riwayat Aktivitas</h2><span class="small muted">{{ $audits->total() }} aktivitas</span></div><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Pelaku</th><th>Tindakan</th><th>Objek</th><th>Ringkasan</th></tr></thead><tbody>
@forelse($audits as $log)<tr><td>{{ $log->created_at?->format('d M Y H:i') }}</td><td>{{ $log->actor?->name??'Sistem' }}</td><td><strong>{{ $log->action }}</strong></td><td>{{ $log->subject_type }} #{{ $log->subject_id }}</td><td class="small muted">{{ \Illuminate\Support\Str::limit(json_encode($log->context,JSON_UNESCAPED_UNICODE),110) }}</td></tr>
@empty<tr><td colspan="5"><div class="empty">Belum ada aktivitas yang tercatat.</div></td></tr>@endforelse</tbody></table></div>
@if($audits->hasPages())<div class="pagination">Halaman {{ $audits->currentPage() }} / {{ $audits->lastPage() }}
@if($audits->previousPageUrl())<a class="btn btn-sm btn-ghost" href="{{ $audits->previousPageUrl() }}">← Kembali</a>@endif
@if($audits->nextPageUrl())<a class="btn btn-sm btn-ghost" href="{{ $audits->nextPageUrl() }}">Selanjutnya →</a>@endif</div>@endif</div>
@endsection
