@extends('layouts.app')
@section('title','Tindak Lanjut Monitoring')
@section('breadcrumb','Operasional / Monitoring / Kasus')
@section('subtitle','Status dan penanggung jawab dikontrol koordinator. Guru pendamping bersifat opsional.')
@section('content')
@php $statuses=\App\Models\MonitoringRecord::statuses(); $categories=\App\Models\MonitoringRecord::categories(); @endphp
<p><a class="btn btn-ghost" href="{{ route('monitoring.index',['placement_id'=>$record->placement_id]) }}">Kembali ke riwayat siswa</a></p>
<div class="card"><h2>Kasus #{{ $record->id }} · {{ $record->placement->student->user->name }}</h2>
<p>NIS {{ $record->placement->student->nis }} · {{ $record->placement->period->academic_year }}<br>{{ $record->placement->roster?->class_name ?? '—' }} · {{ $record->placement->roster?->major ?? 'Roster belum terhubung' }}</p>
<p><strong>{{ $statuses[$record->case_status] ?? $record->case_status }}</strong> · {{ $categories[$record->category] ?? 'Kategori belum ditetapkan' }} · Versi {{ $record->revision }}</p>
@if($record->case_status==='unclassified')<p class="note-box">Catatan lama belum ditentukan statusnya. Sistem tidak menganggap kasus ini sudah selesai; koordinator perlu mengklasifikasikannya.</p>@endif
<p>Penanggung jawab: {{ $record->coordinator?->name ?? 'Belum ditetapkan' }} (ID {{ $record->coordinator_id ?? '—' }})<br>Guru pendamping: {{ $record->teacher?->name ?? 'Tidak ditetapkan' }} (ID {{ $record->teacher_id ?? '—' }})</p>
<p>Tanggal kejadian: {{ $record->visit_date->format('d/m/Y') }}<br>Pencatat awal: {{ $record->creator?->name ?? 'Tidak tercatat' }} · ID {{ $record->created_by ?? '—' }} · {{ $record->created_at?->format('d/m/Y H:i') }}</p>
<h3>Catatan awal</h3><p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $record->notes }}</p>
<h3>Tindak lanjut awal</h3><p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $record->follow_up ?? 'Belum ada.' }}</p>
@if($record->resolved_at)<p>Ditandai selesai pada {{ $record->resolved_at->format('d/m/Y H:i') }}. Ini status penanganan kasus, bukan status PKL siswa.</p>@endif</div>
@if(auth()->user()->isRole('koordinator'))
<div class="card"><h2>Perbarui tindak lanjut</h2><p>Setiap perubahan menyimpan ID editor dan catatan. Kasus selesai dapat dibuka kembali dengan alasan; riwayat lama tidak dihapus.</p>
<form method="POST" action="{{ route('monitoring.update',$record) }}">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ $record->revision }}">
<div class="form-grid">
<label>Status<select name="case_status" required><option value="">Pilih status</option>@foreach($statuses as $key=>$label)@if($key!=='unclassified')<option value="{{ $key }}" @selected(old('case_status',$record->case_status)===$key)>{{ $label }}</option>@endif @endforeach</select></label>
<label>Kategori<select name="category" required><option value="">Pilih kategori</option>@foreach($categories as $key=>$label)<option value="{{ $key }}" @selected(old('category',$record->category)===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Koordinator penanggung jawab<select name="coordinator_id" required><option value="">Pilih koordinator aktif</option>@foreach($coordinators as $coordinator)<option value="{{ $coordinator->id }}" @selected((string)old('coordinator_id',$record->coordinator_id ?? auth()->id())===(string)$coordinator->id)>{{ $coordinator->name }} · ID {{ $coordinator->id }}</option>@endforeach</select></label>
<label>Guru pendamping kasus (opsional)<select name="teacher_id"><option value="">Tanpa guru pendamping</option>@if($record->placement->teacher?->is_active)<option value="{{ $record->placement->teacher_id }}" @selected((string)old('teacher_id',$record->teacher_id)===(string)$record->placement->teacher_id)>{{ $record->placement->teacher->name }}</option>@endif</select></label>
<label>Catatan tindak lanjut / alasan perubahan<textarea name="note" required minlength="10" maxlength="3000">{{ is_string(old('note')) ? old('note') : '' }}</textarea></label>
</div><p class="small muted">Pilihan guru mengikuti penempatan siswa agar akses tidak meluas ke siswa lain. Tidak ada kewajiban kunjungan rutin dan tidak ada perubahan otomatis pada Kontrol PKL.</p>
<button class="btn btn-primary">Simpan Tindak Lanjut</button></form></div>
@else<p>Perubahan status dan penugasan hanya dilakukan koordinator.</p>@endif
<div class="card"><h2>Riwayat perubahan</h2>
@forelse($items as $event)<div style="padding:12px 0;border-bottom:1px solid #ddd;overflow-wrap:anywhere">
<strong>{{ $statuses[$event->before_state['case_status']] ?? $event->before_state['case_status'] }} → {{ $statuses[$event->after_state['case_status']] ?? $event->after_state['case_status'] }}</strong>
<p class="small">{{ $event->created_at->format('d/m/Y H:i') }} · Editor {{ $event->actor->name }} (ID {{ $event->actor_id }}) · Versi {{ $event->after_state['revision'] }}<br>
Kategori: {{ $categories[$event->before_state['category']] ?? 'Belum ditetapkan' }} → {{ $categories[$event->after_state['category']] ?? 'Belum ditetapkan' }}<br>
PJ ID: {{ $event->before_state['coordinator_id'] ?? '—' }} → {{ $event->after_state['coordinator_id'] ?? '—' }} · Guru ID: {{ $event->before_state['teacher_id'] ?? '—' }} → {{ $event->after_state['teacher_id'] ?? '—' }}</p>
<p style="white-space:pre-wrap">{{ $event->note }}</p></div>
@empty<p>Belum ada perubahan. Catatan awal tetap tersedia di atas.</p>@endforelse
@include('partials.pager')</div>
@endsection
