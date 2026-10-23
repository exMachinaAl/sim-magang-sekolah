@extends('layouts.app')
@section('title','Tag Tahun Ajaran PKL')
@section('breadcrumb','Manajemen / Periode PKL')
@section('subtitle','Periode hanya tag pengelompokan, bukan rentang tanggal magang.')
@section('content')
<p class="note-box">Tag <strong>2026/2027</strong> boleh berisi siswa dengan tanggal PKL berbeda. Status siswa hanya mengikuti <a href="{{ route('internships.index') }}">Kontrol PKL</a>. Tag otomatis dibuat saat pendaftaran siswa.</p>
<div class="card"><h2>Tambah Tag</h2><form method="POST" action="{{ route('periods.store') }}">@csrf
<label>Tahun ajaran<input name="name" value="{{ old('name') }}" placeholder="2026/2027" pattern="[0-9]{4}/[0-9]{4}" required></label><div class="form-footer"><button class="btn btn-primary">Simpan Tag</button></div></form></div>
<div class="card"><h2>Daftar Tag</h2><div class="table-wrap"><table><thead><tr><th>Tag</th><th>Siswa</th><th>Penempatan</th><th>Pemakaian tag</th><th></th></tr></thead><tbody>
@forelse($items as $p)<tr><td>{{ $p->academic_year ?? $p->name }}</td><td>{{ $p->rosters_count }}</td><td>{{ $p->placements_count }}</td><td>{{ $p->is_active ? 'Tersedia untuk penempatan baru' : 'Diarsipkan; fase siswa tidak berubah' }}</td><td><details><summary>Edit tag</summary><form method="POST" action="{{ route('periods.update',$p) }}" class="stack">@csrf @method('PUT')
<label>Tahun ajaran<input name="name" value="{{ $p->academic_year ?? $p->name }}" pattern="[0-9]{4}/[0-9]{4}" required @readonly($p->rosters_count || $p->placements_count)></label><label>Pemakaian tag<select name="is_active"><option value="1" @selected($p->is_active)>Tersedia untuk penempatan baru</option><option value="0" @selected(!$p->is_active)>Arsipkan tag</option></select></label><small>Tag yang sudah dipakai tidak dapat diganti tahunnya. Buat tag baru untuk tahun lain.</small><button class="btn btn-primary btn-sm">Simpan</button></form></details></td></tr>
@empty<tr><td colspan="5">Belum ada tag.</td></tr>@endforelse
</tbody></table></div>@include('partials.pager')</div>
@endsection
