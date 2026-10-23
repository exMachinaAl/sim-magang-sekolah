@extends('layouts.app')
@section('title','Pembimbing Industri & Perangkat')
@section('subtitle','Koordinator mengatur identitas industri, penugasan siswa, QR pendaftaran, dan pencabutan browser terpercaya.')
@section('page_actions')<a class="btn btn-secondary" href="{{ route('journals.index') }}">Kembali ke jurnal</a>@endsection
@section('content')
<div class="card"><h2>Daftarkan pembimbing industri</h2>
<form method="GET" class="filterbar"><label>Perusahaan<select name="company_id" required><option value="">Pilih perusahaan</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(request('company_id')==$company->id)>{{ $company->name }}</option>@endforeach</select></label><label>Cari nama / NIS<input name="q" value="{{ request('q') }}" maxlength="100"></label><button class="btn btn-secondary">Tampilkan siswa</button></form>
@if(request('company_id'))
<form method="POST" action="{{ route('journals.mentors.store') }}">@csrf
<input type="hidden" name="company_id" value="{{ request('company_id') }}"><label>Nama pembimbing (contoh: Dodi)<input name="name" required maxlength="150" value="{{ old('name') }}"></label>
<p>Pilih siswa pada halaman ini; tambahkan penugasan lainnya setelah pembimbing dibuat.</p>
@foreach($placements as $placement)<label class="inline-check"><input type="checkbox" name="placement_ids[]" value="{{ $placement->id }}">{{ $placement->student->user->name }} · NIS {{ $placement->student->nis }} · {{ $placement->period->name }}</label>@endforeach
<button class="btn btn-primary">Daftarkan pembimbing & siswa terpilih</button>
</form>@include('partials.pager',['items'=>$placements])@endif
</div>
@foreach($mentors as $mentor)
<div class="card"><h2>{{ $mentor->name }} · {{ $mentor->company->name }}</h2><p>{{ $mentor->placements_count }} penempatan siswa · {{ $mentor->is_active?'Aktif':'Nonaktif' }}</p>
<div class="header-actions">
@if($mentor->is_active)<form method="POST" action="{{ route('journals.mentors.invite',$mentor) }}">@csrf<button class="btn btn-primary">Buat QR daftar perangkat</button></form>@endif
<form method="POST" action="{{ route('journals.mentors.toggle',$mentor) }}">@csrf @method('PUT')<button class="btn btn-secondary">{{ $mentor->is_active?'Nonaktifkan & cabut perangkat':'Aktifkan kembali' }}</button></form>
</div>
<details><summary>Atur penugasan siswa</summary>
<p>Cari perusahaan/nama/NIS pada formulir atas. Pilih salah satu hasil untuk menambah atau melepaskan penugasan.</p>
@foreach($placements->filter(fn($p)=>$p->company_id===$mentor->company_id) as $placement)
<form method="POST" action="{{ route('journals.mentors.assignment',$mentor) }}" class="filterbar">@csrf @method('PUT')<input type="hidden" name="placement_id" value="{{ $placement->id }}">
<span>{{ $placement->student->user->name }} · {{ $placement->student->nis }} · {{ $placement->period->name }}</span><button class="btn btn-secondary btn-sm" name="action" value="add">Tugaskan</button><button class="btn btn-ghost btn-sm" name="action" value="remove">Lepaskan</button></form>
@endforeach</details>
<details><summary>Perangkat terdaftar ({{ $mentor->devices->count() }})</summary>
@forelse($mentor->devices as $device)
<p>#{{ $device->id }} · {{ $device->label }}<br>Daftar {{ $device->created_at->format('d/m/Y H:i') }} · scan terakhir {{ $device->last_used_at?->format('d/m/Y H:i') ?? 'Belum ada' }} · {{ $device->revoked_at?'Dicabut':($device->expires_at->isPast()?'Kedaluwarsa':'Dipercaya') }}</p>
@if(!$device->revoked_at)<form method="POST" action="{{ route('journals.devices.revoke',$device) }}">@csrf @method('PUT')<button class="btn btn-ghost btn-sm">Cabut perangkat #{{ $device->id }}</button></form>@endif
@empty<p>Belum ada perangkat.</p>@endforelse
</details></div>
@endforeach
@include('partials.pager',['items'=>$mentors])
@endsection
