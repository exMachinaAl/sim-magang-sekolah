@extends('layouts.app')
@section('title','Perusahaan Mitra')
@section('breadcrumb','Manajemen / Perusahaan')
@section('subtitle','Informasi lokasi mitra Dunia Usaha dan Dunia Industri (DUDI).')
@section('content')
<div class="card"><div class="card-head"><h2>Tambah Perusahaan</h2></div>
<form method="POST" action="{{ route('companies.store') }}">@csrf<div class="form-grid three">
<div class="form-field"><label>Nama Perusahaan</label><input name="name" required value="{{ old('name') }}"></div>
<div class="form-field"><label>Kontak</label><input name="contact" value="{{ old('contact') }}" placeholder="Telepon/email"></div>
<div class="form-field"><label>Alamat</label><input name="address" value="{{ old('address') }}"></div></div>
<div class="form-footer"><button class="btn btn-primary">+ Tambah Perusahaan</button></div></form></div>
<div class="card"><div class="card-head"><h2>Daftar Mitra</h2><span class="small muted">{{ $items->total() }} perusahaan</span></div>
<form class="filterbar"><input name="q" value="{{ request('q') }}" placeholder="Cari nama perusahaan..."><button class="btn btn-secondary">Cari</button></form>
<div class="table-wrap"><table><thead><tr><th>Perusahaan</th><th>Alamat</th><th>Kontak</th><th>Aksi</th></tr></thead><tbody>
@forelse($items as $c)<tr><td><strong>{{ $c->name }}</strong></td><td>{{ $c->address??'—' }}</td><td>{{ $c->contact??'—' }}</td><td><details><summary class="link" style="cursor:pointer">Edit</summary><form method="POST" action="{{ route('companies.update',$c) }}" class="stack" style="margin-top:10px;min-width:230px">@csrf @method('PUT')
<label>Perusahaan<input name="name" value="{{ $c->name }}" required></label>
<label>Alamat<textarea name="address">{{ $c->address }}</textarea></label>
<label>Kontak<input name="contact" value="{{ $c->contact }}"></label><button class="btn btn-primary btn-sm">Simpan</button></form></details></td></tr>@empty<tr><td colspan="4"><div class="empty">Belum ada data perusahaan.</div></td></tr>@endforelse
</tbody></table></div>@include('partials.pager')</div>
@endsection
