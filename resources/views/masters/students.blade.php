@extends('layouts.app')
@section('title','Data Siswa')
@section('breadcrumb','Manajemen / Siswa')
@section('subtitle','Kelola identitas dan akun peserta magang.')
@section('content')
<div class="card"><form method="GET" class="filterbar"><label>Periode data siswa<select name="period_id"><option value="all" @selected($showAllPeriods)>Semua Periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(!$showAllPeriods&&$selectedPeriod?->id===$period->id)>{{ $period->academic_year }}{{ $period->is_active?'':' (arsip)' }}</option>@endforeach</select></label><input name="q" placeholder="Cari nama atau NIS..." value="{{ request('q') }}"><button class="btn btn-primary">Tampilkan</button><a class="btn btn-ghost" href="{{ route('students.index') }}">Periode Default</a></form>
<p class="small muted">Default mengikuti periode aktif terbaru. Semua Periode menampilkan setiap akun siswa satu kali dengan riwayat tahun ajarannya; kelas dan jurusan tabel mengikuti roster pada periode yang dipilih.</p></div>
@include('masters.student-import')
<p class="note-box">Kelas dan jurusan profil mengikuti tahun ajaran terbaru yang terdaftar. Koreksi di sini memperbarui roster terbaru saja, bukan riwayat tahun sebelumnya. Jadwal PKL tidak berubah.</p>
<div class="card"><div class="card-head"><div><h2>Tambah Siswa</h2><p>Akun siswa otomatis dibuat bersama data profil.</p></div></div>
<form action="{{ route('students.store') }}" method="POST">@csrf<div class="form-grid three">
<div class="form-field"><label>Tahun ajaran pendaftaran</label><input name="academic_year" value="{{ old('academic_year',$academicYear) }}" pattern="[0-9]{4}/[0-9]{4}" required></div>
<div class="form-field"><label>Nama lengkap</label><input name="name" value="{{ old('name') }}" required placeholder="Nama siswa"></div>
<div class="form-field"><label>Nomor Induk Siswa (NIS)</label><input name="nis" value="{{ old('nis') }}" required placeholder="Contoh: 12816/596.030"><div class="field-help">NIS disimpan utuh. Username dibuat otomatis dari seluruh angkanya.</div></div>
<div class="form-field"><label>NISN (opsional)</label><input name="nisn" value="{{ old('nisn') }}"></div>
<div class="form-field"><label>Kelas</label><input name="class_name" value="{{ old('class_name') }}" required placeholder="XII TKJ 1"></div>
<div class="form-field"><label>Jurusan resmi</label><select name="major" required><option value="">Pilih jurusan</option>@foreach($majors as $major)<option value="{{ $major }}" @selected(old('major')===$major)>{{ $major }}</option>@endforeach</select></div>
<div class="form-field"><label>Email (opsional)</label><input type="email" name="email" value="{{ old('email') }}"></div>
<div class="form-field"><label>Tanggal lahir</label><input type="date" name="birth_date" value="{{ old('birth_date') }}" required><div class="field-help">Login: seluruh angka NIS tanpa tanda baca. Password awal: tanggal lahir DDMMYYYY, contoh 7 November 2008 = 07112008. Segera ganti melalui profil.</div></div>
</div><div class="form-footer"><button class="btn btn-primary">+ Simpan Siswa</button></div></form></div>
<div class="card"><div class="card-head"><h2>Daftar Siswa · {{ $showAllPeriods?'Semua Periode':($selectedPeriod?->academic_year ?? 'Tidak ada periode aktif') }}</h2><span class="small muted">{{ $items->total() }} siswa</span></div>
<div class="table-wrap"><table><thead><tr><th>Nama</th><th>NIS / Username</th><th>Periode</th><th>Kelas</th><th>Jurusan</th><th>Email</th><th>Aksi</th></tr></thead><tbody>
@forelse($items as $s)@php $displayRoster=$s->rosters->first(); @endphp<tr><td><strong>{{ $s->user->name }}</strong></td><td>{{ $s->nis }}<div class="small muted">Username: {{ $s->user->username }}@if($s->nisn) · NISN: {{ $s->nisn }}@endif</div></td><td>{{ $displayRoster?->academic_year ?? '—' }}@if($showAllPeriods&&$s->rosters->count()>1)<details><summary class="small link">{{ $s->rosters->count() }} periode</summary>@foreach($s->rosters as $history)<div class="small">{{ $history->academic_year }} · {{ $history->class_name }}</div>@endforeach</details>@endif</td><td>{{ $displayRoster?->class_name ?? $s->class_name }}</td><td>{{ $displayRoster?->major ?? $s->major }}</td><td>{{ $s->user->email }}</td><td><details><summary class="link" style="cursor:pointer">Edit master</summary><form method="POST" action="{{ route('students.update',$s) }}" class="stack" style="margin-top:10px;min-width:230px">@csrf @method('PUT')
<label>Nama<input name="name" value="{{ $s->user->name }}" required></label>
<label>Email (opsional)<input name="email" type="email" value="{{ $s->user->email }}"></label>
<label>Tanggal lahir<input type="date" name="birth_date" value="{{ $s->birth_date?->toDateString() }}"></label>
<p class="small">Perubahan identitas tidak mereset password.</p>
<label>NIS<input name="nis" value="{{ $s->nis }}" required><small>Jika NIS dikoreksi, username ikut dihitung ulang setelah lolos pemeriksaan konflik.</small></label>
<label>NISN<input name="nisn" value="{{ $s->nisn }}"></label>
<label>Kelas<input name="class_name" value="{{ $s->class_name }}" required></label>
<label>Jurusan resmi<select name="major" required><option value="">Pilih jurusan</option>@foreach($majors as $major)<option value="{{ $major }}" @selected($s->major===$major)>{{ $major }}</option>@endforeach</select></label>
<p class="small">Roster terbaru yang ikut diperbarui: {{ $s->latestRoster?->academic_year ?? \App\Models\InternshipRoster::currentAcademicYear() }}. Baris periode arsip di tabel hanya referensi dan tidak ditimpa.</p>
<a href="{{ route('internships.index',['academic_year'=>$displayRoster?->academic_year ?? $academicYear,'q'=>$s->nis]) }}">Lihat siswa di Kontrol PKL</a>
<button class="btn btn-primary btn-sm">Simpan</button></form></details></td></tr>@empty<tr><td colspan="7"><div class="empty">Belum ada siswa.</div></td></tr>@endforelse
</tbody></table></div>@include('partials.pager')</div>
@endsection
