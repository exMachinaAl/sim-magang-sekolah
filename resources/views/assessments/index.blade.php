@extends('layouts.app')
@section('title','Penilaian Industri')
@section('subtitle','Ketentuan mengikuti template jurusan. Nilai dari industri dicatat dan disahkan oleh koordinator PKL.')
@section('content')
<p class="note-box">Nilai akhir industri di sini terpisah dari nilai kegiatan pada jurnal. Scan QR jurnal tidak otomatis mengesahkan atau menghitung nilai akhir sertifikat.</p>
@if(auth()->user()->isRole('koordinator'))
<div class="card"><div class="card-head"><h2>Input nilai berdasarkan template</h2><a class="btn btn-secondary" href="{{ route('assessment-templates.index') }}">Kelola Template Ketentuan</a></div>
<form method="GET" class="filterbar"><label>Cari siswa berdasarkan nama atau NIS<input name="q" value="{{ $search }}" placeholder="Ketik nama atau NIS siswa" maxlength="100" required></label><button class="btn btn-primary">Cari Siswa</button></form>
@if($search !== '')
<div class="table-wrap"><table><thead><tr><th>Nama / NIS</th><th>Kelas / Jurusan</th><th>Periode / Industri</th><th></th></tr></thead><tbody>
@forelse($placements as $placement)
<tr><td>{{ $placement->student->user->name }}<div>NIS {{ $placement->student->nis }}</div></td><td>{{ $placement->student->class_name }}<div>{{ $placement->student->major }}</div></td><td>{{ $placement->period->name }}<div>{{ $placement->company->name }}</div></td><td><a class="btn btn-secondary btn-sm" href="{{ route('assessments.index',['q'=>$search,'placement_id'=>$placement->id]) }}">Buka Penilaian</a></td></tr>
@empty<tr><td colspan="4">Tidak ditemukan penempatan untuk nama/NIS tersebut. Pastikan siswa sudah terdaftar di Penempatan PKL, bukan hanya di Kontrol PKL.</td></tr>@endforelse
</tbody></table></div>@include('partials.pager',['items'=>$placements])
@endif
@if($selectedPlacement)
<p><strong>{{ $selectedPlacement->student->user->name }}</strong> · NIS {{ $selectedPlacement->student->nis }} · {{ $selectedPlacement->period->name }}</p>
<p><strong>{{ $selectedPlacement->student->major }}</strong> · {{ $selectedPlacement->company->name }}</p>
@if(!$assessment)
<form method="GET" class="filterbar"><input type="hidden" name="placement_id" value="{{ $selectedPlacement->id }}"><label>Template jurusan<select name="template_id" required><option value="">Pilih ketentuan</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected($selectedTemplate?->id===$template->id)>{{ $template->name }} (#{{ $template->id }})</option>@endforeach</select></label><button class="btn btn-secondary">Gunakan Template</button></form>
@if($templates->isEmpty())<p>Belum ada template aktif untuk jurusan ini. Buat melalui Kelola Template Ketentuan.</p>@endif
@elseif(!$snapshot)<p>Penilaian ini memakai skema lama dan tetap disimpan sebagai arsip.</p>
@endif
@if($snapshot && $assessment?->status!=='approved')
<form method="POST" action="{{ route('assessments.store') }}">@csrf
<input type="hidden" name="placement_id" value="{{ $selectedPlacement->id }}"><input type="hidden" name="template_id" value="{{ $snapshot['id'] }}"><input type="hidden" name="revision" value="{{ $assessment?->revision ?? 0 }}">
<h3>{{ $snapshot['name'] }}</h3><p class="note-box">Depan sertifikat: {{ $snapshot['certificate_heading'] }} — {{ $snapshot['certificate_statement'] }}</p>
<div class="form-grid"><label>Nama penilai dari industri<input name="industry_assessor" value="{{ old('industry_assessor',$assessment?->industry_assessor) }}" required maxlength="150"></label><label>Tanggal penilaian<input type="date" name="assessed_on" value="{{ old('assessed_on',$assessment?->assessed_on?->toDateString()) }}" required></label></div>
@foreach($snapshot['fields'] as $field)
<div class="card"><label>{{ $field['label'] }} @if($field['type']==='score') ({{ $field['weight'] }}%) @endif</label><p>{{ $field['description'] }}</p>
@if($field['type']==='score')<input aria-label="{{ $field['label'] }}" class="score-input" data-weight="{{ $field['weight'] }}" type="number" step="0.01" min="0" max="100" name="values[{{ $field['key'] }}]" value="{{ old('values.'.$field['key'],$assessment?->field_values[$field['key']] ?? '') }}" required>
@else<textarea aria-label="{{ $field['label'] }}" name="values[{{ $field['key'] }}]" required maxlength="600">{{ old('values.'.$field['key'],$assessment?->field_values[$field['key']] ?? '') }}</textarea>@endif</div>
@endforeach
<p>Hasil hitung berbobot: <strong id="calculated-score">—</strong>. Nilai resmi mengikuti dokumen penilaian yang diterima.</p>
<div class="form-grid"><label>Nilai akhir resmi (wajib untuk validasi)<input type="number" step="0.01" min="0" max="100" name="final_score" value="{{ old('final_score',$assessment?->final_score) }}"></label><label>Alasan jika berbeda dari hasil hitung<input name="final_score_reason" maxlength="1000" value="{{ old('final_score_reason',$assessment?->final_score_reason) }}" placeholder="Contoh: dibulatkan pada dokumen industri"></label></div>
<label>Catatan<textarea name="notes" maxlength="2000">{{ old('notes',$assessment?->notes) }}</textarea></label>
<div class="form-footer"><button class="btn btn-ghost" name="intent" value="draft">Simpan Draf</button><button class="btn btn-primary" name="intent" value="submitted">Simpan untuk Validasi</button></div>
</form>
@elseif($assessment?->status==='approved')<p class="note-box">Nilai sudah disahkan. Ketentuan dan nilai dikunci untuk penerbitan sertifikat.</p>@endif
@endif
</div>
@endif
<div class="card"><h2>Rekap Penilaian</h2><div class="table-wrap"><table><thead><tr><th>Siswa / Industri</th><th>Ketentuan dan hasil</th><th>Nilai akhir</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($items as $item)<tr><td><strong>{{ $item->placement->student->user->name }}</strong><div>{{ $item->placement->company->name }}</div>@if($item->template_snapshot)<div class="small">Penilai industri: {{ $item->industry_assessor }}<br>Diinput: {{ $item->assessor?->name }}</div>@endif</td><td><details><summary>{{ $item->template_snapshot['name'] ?? 'Skema penilaian lama' }}</summary>@if($item->template_snapshot)@foreach($item->template_snapshot['fields'] as $field)<p><strong>{{ $field['label'] }}: {{ $item->field_values[$field['key']] ?? '—' }}</strong><br>{{ $field['description'] }}</p>@endforeach @else @foreach($item->scores as $score)<p>{{ $score->criterion->name }}: {{ $score->score }}</p>@endforeach @endif</details></td><td>{{ $item->final_score !== null ? number_format($item->final_score,2,',','.') : 'Belum diisi' }}</td><td>{{ ['draft'=>'Draf','submitted'=>'Menunggu validasi','revision'=>'Perlu revisi','approved'=>'Disahkan'][$item->status] ?? $item->status }}</td><td>@if(auth()->user()->isRole('koordinator'))<a href="{{ route('assessments.index',['placement_id'=>$item->placement_id]) }}">Buka</a>@if($item->status==='submitted')<form method="POST" action="{{ route('assessments.approve',$item) }}">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $item->revision }}"><button class="btn btn-primary btn-sm" name="decision" value="approved">Sahkan</button><button class="btn btn-ghost btn-sm" name="decision" value="revision">Revisi</button></form>@endif @endif</td></tr>@empty<tr><td colspan="5">Belum ada penilaian.</td></tr>@endforelse
</tbody></table></div>@include('partials.pager',['items'=>$items])</div>
@endsection
@push('scripts')
<script>(()=>{const inputs=Array.from(document.querySelectorAll('.score-input')),output=document.getElementById('calculated-score');if(!output)return;function update(){output.textContent=inputs.some(x=>x.value==='')?'Belum lengkap':inputs.reduce((sum,x)=>sum+Number(x.value)*Number(x.dataset.weight)/100,0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});}inputs.forEach(x=>x.addEventListener('input',update));update();})();</script>
@endpush
