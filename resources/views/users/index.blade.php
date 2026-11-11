@extends('layouts.app')
@section('title',$teacherOnly ? 'Guru Pendamping Sekolah' : 'Pengguna & Hak Akses')
@section('breadcrumb',$teacherOnly ? 'Manajemen / Guru Pendamping' : 'Administrasi Sistem / Pengguna')
@section('subtitle',$teacherOnly ? 'Buat akun guru sekolah, lalu tugaskan kepada siswa melalui menu Penempatan.' : 'Kelola akun internal dan pembatasan role secara terpusat.')
@section('content')
@if(!$teacherOnly)
<div class="card"><div class="header-actions"><a class="btn {{ !$archiveMode?'btn-primary':'btn-ghost' }}" href="{{ route('users.index') }}">Akun Tersedia</a><a class="btn {{ $archiveMode?'btn-primary':'btn-ghost' }}" href="{{ route('users.index',['view'=>'archived']) }}">Arsip Akun <span class="pill">{{ $archivedCount }}</span></a></div>
<p class="small muted">Arsip hanya menyembunyikan dan menonaktifkan akun. Identitas, relasi siswa/guru, dokumen, dan audit tidak dihapus.</p></div>
@endif
@if(!$archiveMode)
<div class="card"><div class="card-head"><div><h2>{{ $teacherOnly ? 'Tambah Akun Guru Pendamping' : 'Tambah Akun Staf / Pembimbing' }}</h2><p>{{ $teacherOnly ? 'Akun yang dibuat di sini hanya memiliki role guru dan belum otomatis ditugaskan kepada siswa.' : 'Gunakan menu Data Siswa untuk membuat akun siswa dan profilnya.' }}</p></div></div>
<form action="{{ route('users.store') }}" method="POST">@csrf<div class="form-grid three">
<div class="form-field"><label>{{ $teacherOnly ? 'Nama Guru' : 'Nama Pengguna' }}</label><input name="name" required maxlength="150" value="{{ old('name') }}"></div>
<div class="form-field"><label>NIP</label><input name="nip" value="{{ old('nip') }}" placeholder="Opsional untuk staf"></div>
<div class="form-field"><label>Email Login</label><input name="email" type="email" required value="{{ old('email') }}"></div>
@if($teacherOnly)
<input type="hidden" name="role" value="guru"><div class="form-field"><label>Role Akun</label><input value="Guru Pendamping Sekolah" disabled><small>Koordinator tidak dapat membuat akun Admin atau Koordinator lain.</small></div>
@else
<div class="form-field"><label>Role Akun</label><select name="role" required><option value="">Pilih role</option>@foreach(\App\Models\User::staffRoleLabels() as $role=>$name)<option value="{{ $role }}" @selected(old('role')==$role)>{{ $name }}</option>@endforeach</select></div>
@endif
<div class="form-field"><label>Kata Sandi Awal</label><input type="password" minlength="10" name="password" required></div>
</div><div class="form-footer"><button class="btn btn-primary">+ {{ $teacherOnly ? 'Buat Akun Guru' : 'Tambah Akun' }}</button>@if($teacherOnly)<a class="btn btn-secondary" href="{{ route('placements.index') }}">Buka Penempatan Siswa</a>@endif</div></form></div>
@endif
<div class="card"><div class="card-head"><div><h2>{{ $archiveMode ? 'Arsip Akun Tersembunyi' : ($teacherOnly ? 'Daftar Guru Pendamping' : 'Daftar Pengguna') }}</h2>@if($teacherOnly)<p>Guru aktif akan muncul pada pilihan pendamping di menu Penempatan.</p>@elseif($archiveMode)<p>Pulihkan akun untuk mengembalikannya ke daftar utama. Akun tetap nonaktif sampai diaktifkan secara terpisah.</p>@endif</div><span class="small muted">{{ $items->total() }} akun</span></div>
<form class="filterbar">@if($archiveMode)<input type="hidden" name="view" value="archived">@endif<input name="q" value="{{ request('q') }}" placeholder="Cari nama, email, atau NIP..."><button class="btn btn-secondary">Cari</button></form>
<div class="table-wrap"><table><thead><tr><th>Pengguna</th><th>NIP</th><th>Email</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($items as $u)<tr><td><strong>{{ $u->name }}</strong>@if($archiveMode)<div class="small muted">Diarsipkan {{ $u->archived_at?->format('d/m/Y H:i') }} oleh {{ $u->archiver?->name ?? 'akun yang tidak tersedia' }}</div>@endif</td><td>
@if($archiveMode){{ $u->nip ?: '—' }}
@elseif(in_array($u->role,['admin','koordinator','guru'],true))<details><summary class="link" style="cursor:pointer">{{ $u->nip ?: 'Isi NIP' }}</summary><form method="POST" action="{{ route('users.identity',$u) }}" class="action-form" style="margin-top:7px">@csrf @method('PUT')<input name="nip" value="{{ $u->nip }}" placeholder="NIP"><button class="btn btn-primary btn-sm">Simpan</button></form></details>
@else—@endif</td><td>{{ $u->email }}</td><td>{{ $teacherOnly && $u->role==='guru' ? 'Guru Pendamping Sekolah' : $u->roleLabel() }}</td><td>
@if($archiveMode)<span class="pill rejected">Tersembunyi &amp; nonaktif</span>@else<span class="pill {{ $u->is_active?'approved':'rejected' }}">{{ $u->is_active?'Aktif':'Nonaktif' }}</span>@endif</td><td>
@if($archiveMode)<form method="POST" action="{{ route('users.restore',$u) }}">@csrf @method('PUT')<button class="btn btn-primary btn-sm" data-confirm="Pulihkan akun {{ $u->name }} ke daftar utama dalam keadaan nonaktif?">Pulihkan</button></form>
@else
@if(str_starts_with($u->role,'legacy_'))<span class="small muted">Role lama · tidak dapat diaktifkan</span>@elseif($u->id!==auth()->id())<form method="POST" action="{{ route('users.toggle',$u) }}">@csrf @method('PUT')<button class="btn btn-sm {{ $u->is_active?'btn-danger':'btn-secondary' }}" data-confirm="Ubah status akun {{ $u->name }}?">{{ $u->is_active?'Nonaktifkan':'Aktifkan' }}</button></form>@else<span class="small muted">Akun Anda</span>@endif
@if(!$teacherOnly && $u->id!==auth()->id())<form method="POST" action="{{ route('users.archive',$u) }}" style="margin-top:7px">@csrf @method('PUT')<button class="btn btn-ghost btn-sm" data-confirm="Arsipkan {{ $u->name }}? Akun akan disembunyikan dan dinonaktifkan, tetapi seluruh data tetap tersimpan.">Arsipkan / Sembunyikan</button></form>@endif
@endif</td></tr>
@empty<tr><td colspan="6"><div class="empty">Belum ada pengguna.</div></td></tr>@endforelse</tbody></table></div>@include('partials.pager')</div>
@endsection
