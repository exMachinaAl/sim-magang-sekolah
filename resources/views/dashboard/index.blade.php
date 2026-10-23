@extends('layouts.app')
@section('title','Dashboard')
@section('breadcrumb','Dashboard')
@section('subtitle','Ringkasan aktivitas dan progres PKL sesuai hak akses Anda.')
@section('content')
@if($studentProfile)
<div class="card"><h2>Data Diri Saya</h2><div class="form-grid three">
<div><strong>Nama</strong><p>{{ auth()->user()->name }}</p></div>
<div><strong>NIS / NISN</strong><p>{{ $studentProfile->nis }} / {{ $studentProfile->nisn ?: 'Belum diisi' }}</p></div>
<div><strong>Tanggal lahir</strong><p>{{ $studentProfile->birth_date?->format('d/m/Y') ?: 'Belum diisi' }}</p></div>
<div><strong>Kelas / Jurusan</strong><p>{{ $studentProfile->class_name }} / {{ $studentProfile->major }}</p></div>
</div><p class="note-box">Jika data diri keliru, hubungi koordinator. Jika masih memakai password awal tanggal lahir, segera ganti melalui menu Profil.</p>
<h3>Status PKL Saya</h3>
@forelse($studentRosters as $roster)<p>{{ $roster->academic_year }}: <strong>{{ $roster->lifecycleLabel() }}</strong>
@if($roster->internship_starts_at && $roster->internship_ends_at) ({{ $roster->internship_starts_at->format('d/m/Y') }} sampai {{ $roster->internship_ends_at->format('d/m/Y') }})@endif</p>
@empty<p>Belum didaftarkan ke tahun ajaran PKL. Data diri dan akun Anda sudah tersimpan.</p>@endforelse
@forelse($studentPlacements as $placement)<p>Penempatan: {{ $placement->company->name }} / {{ $placement->period->name }}</p>
@empty<p>Industri belum ditentukan oleh koordinator.</p>@endforelse
</div>
@endif
<div class="stat-grid">
 <div class="stat-card"><div class="stat-icon">♟</div><div class="stat-number">{{ $studentsCount }}</div><div class="stat-label">{{ auth()->user()->isRole('admin','koordinator')?'Total Siswa':'Siswa Terpantau' }}</div><div class="stat-extra">Data siswa yang dapat Anda akses</div></div>
 <div class="stat-card"><div class="stat-icon teal">▥</div><div class="stat-number">{{ $companiesCount }}</div><div class="stat-label">Perusahaan Mitra</div><div class="stat-extra">Lokasi penempatan PKL</div></div>
 <div class="stat-card"><div class="stat-icon gold">▤</div><div class="stat-number">{{ $pendingJournals }}</div><div class="stat-label">Jurnal Belum Ditinjau</div><div class="stat-extra">Review sekolah opsional, bukan pengesahan industri</div></div>
 <div class="stat-card"><div class="stat-icon pink">▧</div><div class="stat-number">{{ $certificatesCount }}</div><div class="stat-label">Sertifikat Tercatat</div><div class="stat-extra">{{ $pendingApproval }} menunggu persetujuan Anda / relevan</div></div>
</div>
<div class="two-cols">
 <div class="card"><div class="card-head"><div><h2>Progres Jurnal Siswa</h2><p>Satu ringkasan untuk setiap penempatan</p></div><a class="link small" href="{{ route('journals.index') }}">Lihat semua →</a></div>
  @if($latestJournals->isEmpty())<div class="empty"><span class="empty-mark">▤</span>Belum ada data jurnal untuk ditampilkan.</div>@else
  <div class="table-wrap"><table><thead><tr><th>Nama Siswa</th><th>Total</th><th>Progres</th><th>Aktivitas Terakhir</th><th></th></tr></thead><tbody>
  @foreach($latestJournals as $placement)@php $progress=$placement->journals_count?round(($placement->approved_journals_count/$placement->journals_count)*100):0; @endphp<tr><td><strong>{{ $placement->student->user->name }}</strong></td><td>{{ $placement->journals_count }} jurnal</td><td><strong>{{ $placement->industry_validated_count }} tervalidasi industri</strong><div class="small muted">{{ $placement->approved_journals_count }} ditinjau sekolah · {{ $placement->submitted_journals_count }} tercatat</div></td><td>{{ $placement->journals_max_activity_date?\Illuminate\Support\Carbon::parse($placement->journals_max_activity_date)->format('d/m/Y'):'Belum ada' }}</td><td><a class="btn btn-secondary btn-sm" href="{{ route('journals.show',$placement) }}">Detail</a></td></tr>@endforeach
  </tbody></table></div>@endif
 </div>
 <div class="card"><div class="card-head"><div><h2>Progres Sertifikat</h2><p>Pantau penerbitan dan penyerahan</p></div><a class="link small" href="{{ route('certificates.index') }}">Lihat semua →</a></div>
 @forelse($latestCertificates as $c)<div style="display:flex;gap:12px;align-items:center;justify-content:space-between;border-bottom:1px solid #edf1f7;padding:12px 0"><div><strong>{{ $c->placement->student->user->name }}</strong><div class="small muted">{{ $c->number }}</div></div><span class="pill {{ $c->status }}">{{ $c->statusLabel() }}</span></div>
 @empty<div class="empty"><span class="empty-mark">▧</span>Belum ada sertifikat.</div>@endforelse
 </div>
</div>
<div class="card"><div class="card-head"><div><h2>Akses Cepat</h2><p>Pilih aktivitas utama sesuai peran Anda.</p></div></div><div style="display:flex;gap:10px;flex-wrap:wrap">
<a class="btn btn-secondary" href="{{ route('timeline.index') }}">◷ Lihat Timeline</a>
@if(auth()->user()->isRole('siswa','guru','admin','koordinator'))<a class="btn btn-secondary" href="{{ route('journals.index') }}">▤ Jurnal PKL</a>@endif
<a class="btn btn-secondary" href="{{ route('certificates.index') }}">▧ Sertifikat</a><a class="btn btn-ghost" href="{{ route('guide') }}">? Panduan</a>
</div></div>
@endsection
