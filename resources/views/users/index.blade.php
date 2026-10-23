@extends('layouts.app')
@section('title','Pengguna & Hak Akses')
@section('breadcrumb','Manajemen / Pengguna')
@section('subtitle','Kelola akun internal dan pembatasan role secara terpusat.')
@section('content')
<div class="card"><div class="card-head"><div><h2>Tambah Akun Staf / Pembimbing</h2><p>Gunakan menu Data Siswa untuk membuat akun siswa dan profilnya.</p></div></div>
<form action="{{ route('users.store') }}" method="POST">@csrf<div class="form-grid three">
<div class="form-field"><label>Nama Pengguna</label><input name="name" required value="{{ old('name') }}"></div>
<div class="form-field"><label>NIP</label><input name="nip" value="{{ old('nip') }}" placeholder="Opsional untuk staf"></div>
<div class="form-field"><label>Email Login</label><input name="email" type="email" required value="{{ old('email') }}"></div>
<div class="form-field"><label>Role Akun</label><select name="role" required><option value="">Pilih role</option>@foreach(\App\Models\User::staffRoleLabels() as $role=>$name)<option value="{{ $role }}" @selected(old('role')==$role)>{{ $name }}</option>@endforeach</select></div>
<div class="form-field"><label>Kata Sandi Awal</label><input type="password" minlength="10" name="password" required></div>
</div><div class="form-footer"><button class="btn btn-primary">+ Tambah Akun</button></div></form></div>
<div class="card"><div class="card-head"><h2>Daftar Pengguna</h2><span class="small muted">{{ $items->total() }} akun</span></div>
<form class="filterbar"><input name="q" value="{{ request('q') }}" placeholder="Cari nama, email, atau NIP..."><button class="btn btn-secondary">Cari</button></form>
<div class="table-wrap"><table><thead><tr><th>Pengguna</th><th>NIP</th><th>Email</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($items as $u)<tr><td><strong>{{ $u->name }}</strong></td><td>@if(in_array($u->role,['admin','koordinator','guru'],true))<details><summary class="link" style="cursor:pointer">{{ $u->nip ?: 'Isi NIP' }}</summary><form method="POST" action="{{ route('users.identity',$u) }}" class="action-form" style="margin-top:7px">@csrf @method('PUT')<input name="nip" value="{{ $u->nip }}" placeholder="NIP"><button class="btn btn-primary btn-sm">Simpan</button></form></details>@else—@endif</td><td>{{ $u->email }}</td><td>{{ $u->roleLabel() }}</td><td><span class="pill {{ $u->is_active?'approved':'rejected' }}">{{ $u->is_active?'Aktif':'Nonaktif' }}</span></td><td>
@if(str_starts_with($u->role,'legacy_'))<span class="small muted">Arsip permanen</span>@elseif($u->id!==auth()->id())<form method="POST" action="{{ route('users.toggle',$u) }}">@csrf @method('PUT')<button class="btn btn-sm {{ $u->is_active?'btn-danger':'btn-secondary' }}" data-confirm="Ubah status akun {{ $u->name }}?">{{ $u->is_active?'Nonaktifkan':'Aktifkan' }}</button></form>@else<span class="small muted">Akun Anda</span>@endif</td></tr>
@empty<tr><td colspan="6"><div class="empty">Belum ada pengguna.</div></td></tr>@endforelse</tbody></table></div>@include('partials.pager')</div>
@endsection
