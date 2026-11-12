@extends('layouts.app')
@section('title','Penilaian Siswa')
@section('breadcrumb','Operasional / Penilaian / Siswa')
@section('subtitle','Bukti, nilai resmi, pengesahan, dan kesiapan sertifikat dalam satu halaman.')
@section('page_actions')<a class="btn btn-ghost" href="{{ route('assessments.index',['period_id'=>$placement->internship_period_id,'major'=>$placement->roster?->major]) }}">← Kembali ke daftar</a>@endsection
@section('content')
@php
    $statusLabels=['draft'=>'Draf','submitted'=>'Menunggu pengesahan','revision'=>'Perlu revisi','approved'=>'Disahkan'];
    $prefill=$selectedSubmission?->status==='submitted'&&!$submissionIssue?$selectedSubmission:null;
@endphp

@if($assessment?->status==='approved' && $placement->certificate)
<p class="note-box"><strong>Nilai selesai dan sertifikat sudah dibuat.</strong> Status sertifikat: {{ $placement->certificate->statusLabel() }}.</p>
@elseif($assessment?->status==='approved')
<p class="note-box"><strong>Nilai sudah disahkan.</strong> Siswa siap dilanjutkan ke pengajuan sertifikat.</p>
@elseif($finished)
<p class="alert error"><strong>Perlu segera dinilai.</strong> Masa PKL selesai {{ $endsAt->format('d/m/Y') }}{{ $daysSinceEnd>0?' ('.$daysSinceEnd.' hari lalu)':'' }}, tetapi nilai belum disahkan. Periksa bukti, resmikan nilai, lalu lanjutkan sertifikat.</p>
@elseif($endsAt)
<p class="note-box">Masa PKL dijadwalkan selesai {{ $endsAt->format('d/m/Y') }} ({{ $daysUntilEnd }} hari lagi). Nilai dapat disiapkan sebagai draf, tetapi tetap cocokkan dengan lembar akhir industri.</p>
@else
<p class="alert error">Tanggal selesai PKL belum tersedia. Jadwal perlu diperbaiki di Kontrol PKL sebelum kesiapan akhir dapat dipastikan.</p>
@endif

<div class="card"><div class="card-head"><div><h2>{{ $placement->student->user->name }}</h2><p>NIS {{ $placement->student->nis }} · {{ $placement->period->academic_year }}</p></div><span class="pill {{ $assessment?->status ?? 'pending' }}">{{ $assessment ? ($statusLabels[$assessment->status]??$assessment->status) : 'Belum dinilai' }}</span></div>
<div class="detail-grid">
<div><small>Kelas / Jurusan</small><strong>{{ $placement->roster?->class_name ?? '—' }} / {{ $placement->roster?->major ?? 'Roster belum terhubung' }}</strong></div>
<div><small>Industri</small><strong>{{ $placement->company->name }}</strong></div>
<div><small>Mulai PKL</small><strong>{{ $startsAt?->format('d/m/Y') ?? 'Belum dijadwalkan' }}</strong></div>
<div><small>Selesai PKL</small><strong>{{ $endsAt?->format('d/m/Y') ?? 'Belum dijadwalkan' }}</strong></div>
<div><small>Setoran siswa</small><strong>{{ $submissions->where('status','submitted')->count() }} menunggu · {{ $submissions->where('status','applied')->count() }} diterapkan</strong></div>
<div><small>Bukti tambahan</small><strong>{{ $evidenceItems->count() }} dokumen</strong></div>
</div></div>

<div class="card"><div class="card-head"><div><h2>1. Setoran dan bukti dari siswa</h2><p>Periksa isi dan foto sebelum memasukkan nilai resmi.</p></div><span class="small muted">{{ $submissions->count() }} riwayat</span></div>
@forelse($submissions as $submission)
<details @if($selectedSubmission?->id===$submission->id) open @endif><summary><strong>Setoran #{{ $submission->id }} · nilai {{ $submission->final_score }}</strong> — {{ $submission->statusLabel() }} · {{ $submission->created_at->format('d/m/Y H:i') }}</summary>
<p>Penilai industri: {{ $submission->industry_assessor }} · tanggal {{ $submission->assessed_on->format('d/m/Y') }}<br>Dikirim oleh {{ $submission->submitter->name }} (ID {{ $submission->submitted_by }}).</p>
<div class="table-wrap"><table><thead><tr><th>Kriteria</th><th>Hasil</th><th>Bobot</th></tr></thead><tbody>@foreach($submission->template_snapshot['fields'] as $field)<tr><td>{{ $field['label'] }}<div class="small muted">{{ $field['description'] }}</div></td><td>{{ $submission->field_values[$field['key']] ?? '—' }}</td><td>{{ $field['type']==='score'?$field['weight'].'%':'—' }}</td></tr>@endforeach</tbody></table></div>
<p>Hasil hitung {{ $submission->calculated_score }} · nilai akhir {{ $submission->final_score }}@if($submission->final_score_reason)<br>Alasan perbedaan: {{ $submission->final_score_reason }}@endif</p>
@if($submission->notes)<p>Catatan siswa: {{ $submission->notes }}</p>@endif
@if($submission->hasPhoto())
@include('partials.assessment-image-preview',['previewUrl'=>route('assessments.submissions.photo',$submission),'downloadUrl'=>route('assessments.submissions.photo',[$submission,'download'=>1]),'title'=>'Foto setoran #'.$submission->id.' · '.$submission->photo_original_name,'actionLabel'=>'Lihat foto bukti lebih besar'])
@else<span class="small muted">Tanpa foto; gunakan jurnal/lembar fisik bila tersedia.</span>@endif
<div class="form-footer">
@if($submission->status==='submitted' && auth()->user()->isRole('koordinator'))<a class="btn btn-primary" href="{{ route('assessments.student',[$placement,'submission_id'=>$submission->id]) }}">Gunakan setoran ini</a>@endif</div>
</details>
@empty<p>Belum ada setoran digital. Koordinator tetap dapat mengisi berdasarkan jurnal atau lembar fisik.</p>@endforelse
</div>

<div class="card"><div class="card-head"><div><h2>2. Nilai resmi koordinator</h2><p>Setoran siswa adalah sumber awal; bagian inilah yang digunakan sertifikat setelah disahkan.</p></div>@if($assessment)<span class="pill {{ $assessment->status }}">{{ $statusLabels[$assessment->status]??$assessment->status }}</span>@endif</div>
@if($prefill)<p class="note-box">Form diprefill dari setoran #{{ $prefill->id }}. Bandingkan foto dan koreksi bila perlu sebelum menyimpan.</p>@endif
@if($submissionIssue)<p class="alert error">{{ $submissionIssue }} Setoran tetap dapat dibaca di atas, tetapi tidak diterapkan otomatis.</p>@endif

@if(!$assessment && auth()->user()->isRole('koordinator'))
<form method="GET" class="filterbar" action="{{ route('assessments.student',$placement) }}">@if($prefill)<input type="hidden" name="submission_id" value="{{ $prefill->id }}">@endif<label>Template jurusan<select name="template_id" required><option value="">Pilih ketentuan</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected($selectedTemplate?->id===$template->id)>{{ $template->name }} (#{{ $template->id }}){{ $defaultTemplateId===$template->id?' — Default jurusan':'' }}</option>@endforeach</select></label><button class="btn btn-secondary">Gunakan Template</button></form>
@if($selectedTemplate && $selectedTemplate->id===$defaultTemplateId)<p class="small muted">Template default jurusan dipilih otomatis. Koordinator dapat memilih template aktif lain sebelum penilaian pertama disimpan.</p>@endif
@if($templates->isEmpty())<p class="alert error">Belum ada template aktif untuk jurusan ini.</p>@endif
@elseif($assessment && !$snapshot)<p>Penilaian memakai skema lama dan dipertahankan sebagai arsip.</p>@endif

@if($snapshot && $assessment?->status!=='approved' && auth()->user()->isRole('koordinator'))
<form method="POST" action="{{ route('assessments.store') }}">@csrf
<input type="hidden" name="placement_id" value="{{ $placement->id }}"><input type="hidden" name="template_id" value="{{ $snapshot['id'] }}"><input type="hidden" name="revision" value="{{ $assessment?->revision ?? 0 }}"><input type="hidden" name="intent" value="draft">@if($prefill)<input type="hidden" name="submission_id" value="{{ $prefill->id }}">@endif
<h3>{{ $snapshot['name'] }}</h3><p class="small muted">{{ $snapshot['certificate_heading'] }} — {{ $snapshot['certificate_statement'] }}</p>
<div class="form-grid"><label>Nama penilai industri<input name="industry_assessor" required maxlength="150" value="{{ old('industry_assessor',$assessment?->industry_assessor??$prefill?->industry_assessor) }}"></label><label>Tanggal penilaian<input type="date" name="assessed_on" required max="{{ now('Asia/Jakarta')->toDateString() }}" value="{{ old('assessed_on',$assessment?->assessed_on?->toDateString()??$prefill?->assessed_on?->toDateString()) }}"></label></div>
@foreach($snapshot['fields'] as $field)<div class="card"><label>{{ $field['label'] }} @if($field['type']==='score')({{ $field['weight'] }}%)@endif</label><p>{{ $field['description'] }}</p>
@if($field['type']==='score')<input class="score-input" data-weight="{{ $field['weight'] }}" aria-label="{{ $field['label'] }}" type="number" step="0.01" min="0" max="100" name="values[{{ $field['key'] }}]" required value="{{ old('values.'.$field['key'],$assessment?->field_values[$field['key']]??$prefill?->field_values[$field['key']]??'') }}">
@else<textarea aria-label="{{ $field['label'] }}" name="values[{{ $field['key'] }}]" required maxlength="600">{{ old('values.'.$field['key'],$assessment?->field_values[$field['key']]??$prefill?->field_values[$field['key']]??'') }}</textarea>@endif</div>@endforeach
<p>Hasil hitung berbobot: <strong id="calculated-score">—</strong></p>
<div class="form-grid"><label>Nilai akhir resmi<input type="number" step="0.01" min="0" max="100" name="final_score" value="{{ old('final_score',$assessment?->final_score??$prefill?->final_score) }}"></label><label>Alasan jika berbeda dari hasil hitung<input name="final_score_reason" maxlength="1000" value="{{ old('final_score_reason',$assessment?->final_score_reason??$prefill?->final_score_reason) }}"></label></div>
<label>Catatan koordinator<textarea name="notes" maxlength="2000">{{ old('notes',$assessment?->notes??$prefill?->notes) }}</textarea></label>
<label class="inline-check"><input id="assessment-finalize" type="checkbox" name="finalize" value="1" @checked(old('finalize'))><span><strong>Nilai sudah diperiksa dan dinyatakan valid</strong><small>Jika tidak dicentang, tombol hanya menyimpan draf. Jika dicentang, nilai dikunci sebagai sah lalu sistem membuka persiapan sertifikat.</small></span></label>
<div class="form-footer"><button id="assessment-save-button" class="btn btn-primary">Simpan Penilaian</button><span id="assessment-save-mode" class="small muted">Mode: simpan draf</span></div></form>
@elseif($assessment && $snapshot)
<div class="table-wrap"><table><thead><tr><th>Kriteria</th><th>Hasil</th><th>Bobot</th></tr></thead><tbody>@foreach($snapshot['fields'] as $field)<tr><td>{{ $field['label'] }}<div class="small muted">{{ $field['description'] }}</div></td><td>{{ $assessment->field_values[$field['key']]??'—' }}</td><td>{{ $field['type']==='score'?$field['weight'].'%':'—' }}</td></tr>@endforeach</tbody></table></div><p>Nilai resmi: <strong>{{ $assessment->final_score }}</strong> · Penilai industri: {{ $assessment->industry_assessor }}</p>
@endif

@if($assessment?->status==='submitted' && auth()->user()->isRole('koordinator'))
<p class="note-box">Nilai menunggu pengesahan. Periksa setoran/foto dan bukti tambahan pada halaman ini sebelum memutuskan.</p><form method="POST" action="{{ route('assessments.approve',$assessment) }}">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $assessment->revision }}"><div class="form-footer"><button class="btn btn-primary" name="decision" value="approved">Sahkan Nilai</button><button class="btn btn-ghost" name="decision" value="revision">Kembalikan untuk Revisi</button></div></form>
@endif
</div>

<div class="card"><div class="card-head"><div><h2>3. Bukti tambahan koordinator</h2><p>Opsional bila foto siswa tidak ada atau dokumen tambahan perlu diarsipkan.</p></div><span class="small muted">{{ $evidenceItems->count() }} dokumen</span></div>
@if($assessment && auth()->user()->isRole('koordinator'))<form method="POST" enctype="multipart/form-data" action="{{ route('assessments.evidence.store',$assessment) }}">@csrf<input type="hidden" name="revision" value="{{ $assessment->revision }}"><div class="form-grid"><label>PDF/JPG/PNG maksimal 2 MB<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></label><label>Asal/keterangan<textarea name="note" required minlength="10" maxlength="2000"></textarea></label></div><button class="btn btn-secondary">Unggah Bukti Tambahan</button></form>
@elseif(!$assessment)<p>Simpan draf nilai dahulu sebelum mengarsipkan bukti tambahan.</p>@endif
@forelse($evidenceItems as $evidence)<div style="padding:12px 0;border-bottom:1px solid #ddd"><strong>{{ $evidence->original_name }}</strong><p class="small muted">{{ number_format($evidence->size/1024,1) }} KB · {{ $evidence->created_at->format('d/m/Y H:i') }} · {{ $evidence->uploader->name }}</p><p>{{ $evidence->note }}</p>
@if(str_starts_with($evidence->mime,'image/'))
@include('partials.assessment-image-preview',['previewUrl'=>route('assessments.evidence.download',[$evidence,'inline'=>1]),'downloadUrl'=>route('assessments.evidence.download',$evidence),'title'=>'Bukti tambahan #'.$evidence->id.' · '.$evidence->original_name,'actionLabel'=>'Lihat bukti tambahan lebih besar'])
@else<a class="btn btn-secondary btn-sm" href="{{ route('assessments.evidence.download',$evidence) }}">Unduh Bukti PDF</a>@endif</div>@empty<p>Belum ada bukti tambahan koordinator.</p>@endforelse
</div>

<div class="card"><h2>4. Lanjutkan ke sertifikat</h2>
@if($placement->certificate)<p>Sertifikat sudah dibuat dengan status <strong>{{ $placement->certificate->statusLabel() }}</strong>.</p><a class="btn btn-primary" href="{{ route('certificates.show',$placement->certificate) }}">Buka Sertifikat</a>
@elseif($assessment?->status==='approved')<p>Nilai telah disahkan dan siswa siap diajukan.</p>@if(auth()->user()->isRole('koordinator'))<a class="btn btn-primary" href="{{ route('certificates.prepare',$placement) }}">Siapkan Sertifikat</a>@endif
@else<p class="small muted">Sertifikat belum dapat diajukan sampai nilai resmi disahkan koordinator.</p>@endif</div>
@include('partials.assessment-image-lightbox')
@endsection
@push('scripts')<script>(()=>{const inputs=Array.from(document.querySelectorAll('.score-input')),output=document.getElementById('calculated-score');if(!output)return;const refresh=()=>{output.textContent=inputs.some(x=>x.value==='')?'Belum lengkap':inputs.reduce((sum,x)=>sum+Number(x.value)*Number(x.dataset.weight)/100,0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});};inputs.forEach(x=>x.addEventListener('input',refresh));refresh();})();(()=>{const checkbox=document.getElementById('assessment-finalize'),button=document.getElementById('assessment-save-button'),mode=document.getElementById('assessment-save-mode');if(!checkbox||!button||!mode)return;const refresh=()=>{button.textContent=checkbox.checked?'Sahkan & Lanjut ke Sertifikat':'Simpan Penilaian';mode.textContent=checkbox.checked?'Mode: simpan dan sahkan nilai':'Mode: simpan draf';};checkbox.addEventListener('change',refresh);refresh();})();</script>@endpush
