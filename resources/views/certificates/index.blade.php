@extends('layouts.app')
@section('title','Manajemen Sertifikat')
@section('breadcrumb','Administrasi / Sertifikat')
@section('subtitle','Penerbitan, persetujuan koordinator PKL, pencetakan, dan serah terima dokumen.')
@section('content')
@if(auth()->user()->isRole('koordinator'))<div class="card"><div class="card-head"><div><h2>Ajukan Sertifikat</h2><p>Penilaian industri berbasis template harus sudah disahkan. Untuk penilaian skema lama, seluruh jurnal juga harus disetujui.</p></div></div>
<form method="POST" action="{{ route('certificates.create') }}" class="filterbar">@csrf<select name="placement_id" required><option value="">Pilih penempatan siswa...</option>@foreach($placements as $p)<option value="{{ $p->id }}">{{ $p->student->user->name }} · {{ $p->period->name }}</option>@endforeach</select><button class="btn btn-primary">+ Buat & Ajukan Sertifikat</button></form></div>@endif
<div class="card"><div class="card-head"><div><h2>Daftar Sertifikat</h2><p>Klik nomor sertifikat untuk melihat persetujuan dan riwayat distribusi.</p></div><span class="small muted">{{ $items->total() }} dokumen</span></div>
<form class="filterbar"><select name="status"><option value="">Semua Status</option>@foreach(\App\Models\Certificate::statuses() as $k=>$label)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $label }}</option>@endforeach</select><button class="btn btn-secondary">Filter</button></form>
<div class="table-wrap"><table><thead><tr><th>Nomor Sertifikat</th><th>Peserta</th><th>Perusahaan</th><th>Status</th><th>Persetujuan</th><th>Jadwal Pengambilan</th><th></th></tr></thead><tbody>
@forelse($items as $c)<tr><td><strong>{{ $c->number }}</strong><div class="small muted">Versi {{ $c->version }}</div></td>
<td>{{ $c->placement->student->user->name }}</td><td>{{ $c->placement->company->name }}</td><td><span class="pill {{ $c->status }}">{{ $c->statusLabel() }}</span></td>
<td>@foreach($c->approvals as $a)<div class="small">Koordinator PKL: <strong>{{ ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'][$a->status]??$a->status }}</strong></div>@endforeach</td>
<td>{{ $c->pickup_at?->format('d M Y, H:i')??'Belum ditetapkan' }}</td><td><a class="btn btn-secondary btn-sm" href="{{ route('certificates.show',$c) }}">Detail →</a></td></tr>
@empty<tr><td colspan="7"><div class="empty">Belum ada sertifikat yang diterbitkan.</div></td></tr>@endforelse</tbody></table></div>@include('partials.pager')</div>
@endsection
