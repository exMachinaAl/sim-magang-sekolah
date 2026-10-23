@extends('layouts.app')
@section('title','Monitoring Guru')
@section('breadcrumb','Operasional / Monitoring')
@section('subtitle','Dokumentasikan kunjungan, hasil pemantauan, dan tindak lanjut pembimbing.')
@section('content')
@if(auth()->user()->isRole('guru'))<div class="card"><div class="card-head"><h2>Tambah Catatan Monitoring</h2></div>
<form method="POST" action="{{ route('monitoring.store') }}">@csrf<div class="form-grid">
<div class="form-field"><label>Siswa Bimbingan</label><select name="placement_id" required><option value="">Pilih penempatan</option>@foreach($placements as $p)<option value="{{ $p->id }}">{{ $p->student->user->name }} · {{ $p->period->name }}</option>@endforeach</select></div>
<div class="form-field"><label>Tanggal Kunjungan</label><input name="visit_date" type="date" required max="{{ date('Y-m-d') }}"></div>
<div class="form-field"><label>Hasil Monitoring</label><textarea name="notes" required minlength="10"></textarea></div><div class="form-field"><label>Tindak Lanjut</label><textarea name="follow_up"></textarea></div></div>
<div class="form-footer"><button class="btn btn-primary">+ Simpan Monitoring</button></div></form></div>@endif
<div class="card"><div class="card-head"><h2>Riwayat Monitoring</h2><span class="small muted">{{ $items->total() }} catatan</span></div><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Siswa</th><th>Guru</th><th>Hasil</th><th>Tindak Lanjut</th></tr></thead><tbody>
@forelse($items as $m)<tr><td>{{ $m->visit_date->format('d M Y') }}</td><td><strong>{{ $m->placement->student->user->name }}</strong></td><td>{{ $m->teacher->name }}</td><td>{{ $m->notes }}</td><td>{{ $m->follow_up??'—' }}</td></tr>@empty<tr><td colspan="5"><div class="empty">Belum ada kunjungan monitoring.</div></td></tr>@endforelse</tbody></table></div>@include('partials.pager')</div>
@endsection
