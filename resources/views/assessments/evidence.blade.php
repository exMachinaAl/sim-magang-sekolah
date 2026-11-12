@extends('layouts.app')
@section('title','Bukti & Informasi Nilai Industri')
@section('subtitle','Bandingkan dokumen sumber dengan nilai yang dicatat sebelum koordinator menerapkannya atau mengesahkannya.')
@section('breadcrumb','Operasional / Penilaian / Bukti')
@section('content')
@php
    $statusLabels=['draft'=>'Draf','submitted'=>'Menunggu validasi','revision'=>'Perlu revisi','approved'=>'Disahkan'];
    $snapshot=$assessment->template_snapshot;
@endphp
<p><a class="btn btn-ghost" href="{{ route('assessments.student',$assessment->placement_id) }}">Kembali ke penilaian siswa</a></p>

<div class="card"><div class="card-head"><div><h2>{{ $assessment->placement->student->user->name }}</h2><p>NIS {{ $assessment->placement->student->nis }} · {{ $assessment->placement->period->academic_year }}</p></div><span class="pill {{ $assessment->status }}">{{ $statusLabels[$assessment->status] ?? $assessment->status }}</span></div>
<div class="detail-grid">
<div><small>Kelas / Jurusan</small><strong>{{ $assessment->placement->roster?->class_name ?? '—' }} / {{ $assessment->placement->roster?->major ?? 'Roster belum terhubung' }}</strong></div>
<div><small>Industri</small><strong>{{ $assessment->placement->company->name }}</strong></div>
<div><small>Penilai industri</small><strong>{{ $assessment->industry_assessor ?? 'Skema lama' }}</strong></div>
<div><small>Tanggal penilaian</small><strong>{{ $assessment->assessed_on?->format('d/m/Y') ?? 'Tidak tercatat' }}</strong></div>
<div><small>Dicatat koordinator</small><strong>{{ $assessment->assessor?->name ?? 'Tidak tercatat' }} · ID {{ $assessment->assessor_id ?? '—' }}</strong></div>
<div><small>Versi penilaian</small><strong>{{ $assessment->revision ?? 0 }}</strong></div>
</div></div>

<div class="card"><div class="card-head"><div><h2>Informasi penilaian yang dicatat dari industri</h2><p>Gunakan bagian ini untuk membandingkan nilai sistem dengan lembar bukti di bawah.</p></div><strong>Nilai resmi {{ $assessment->final_score ?? 'Belum diisi' }}</strong></div>
@if($snapshot)
<div class="table-wrap"><table><thead><tr><th>Aspek / ketentuan</th><th>Nilai atau hasil</th><th>Bobot</th></tr></thead><tbody>
@foreach($snapshot['fields'] as $field)<tr><td><strong>{{ $field['label'] }}</strong><div class="small muted">{{ $field['description'] }}</div></td><td>{{ $assessment->field_values[$field['key']] ?? '—' }}</td><td>{{ $field['type']==='score' ? $field['weight'].'%' : '—' }}</td></tr>@endforeach
</tbody></table></div>
<p>Hasil hitung berbobot: <strong>{{ $assessment->calculated_score ?? '—' }}</strong>@if($assessment->final_score_reason)<br>Alasan nilai resmi berbeda: {{ $assessment->final_score_reason }}@endif</p>
@else
<p>Penilaian ini memakai skema lama.</p>
@if($assessment->scores->isNotEmpty())<div class="table-wrap"><table><thead><tr><th>Aspek</th><th>Nilai</th></tr></thead><tbody>@foreach($assessment->scores as $score)<tr><td>{{ $score->criterion->name }}</td><td>{{ $score->score }}</td></tr>@endforeach</tbody></table></div>@endif
@endif
@if($assessment->notes)<p><strong>Catatan penilaian:</strong><br><span style="white-space:pre-wrap">{{ $assessment->notes }}</span></p>@endif

@if(auth()->user()->isRole('koordinator'))
<div class="form-footer">
@if($assessment->status!=='approved')<a class="btn btn-secondary" href="{{ route('assessments.student',$assessment->placement_id) }}">Terapkan / koreksi pada form penilaian</a>@endif
@if($assessment->status==='submitted')
<form method="POST" action="{{ route('assessments.approve',$assessment) }}">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $assessment->revision }}"><button class="btn btn-primary" name="decision" value="approved">Sahkan setelah bukti diperiksa</button><button class="btn btn-ghost" name="decision" value="revision">Kembalikan untuk revisi</button></form>
@endif
</div>
@endif
<p class="small muted">Menerapkan nilai berarti koordinator menyalin atau mengoreksi isian berdasarkan dokumen industri. Sistem tidak membaca angka dari foto/PDF secara otomatis dan tidak mengubah nilai hanya karena sebuah file diunggah.</p></div>

@if($studentSubmissions->isNotEmpty())
<div class="card"><h2>Setoran siswa yang pernah diterapkan</h2><p>Bagian ini mempertahankan asal input siswa. Nilai resmi tetap informasi penilaian koordinator di atas.</p>
@foreach($studentSubmissions as $submission)<details><summary>Setoran #{{ $submission->id }} · {{ $submission->created_at->format('d/m/Y H:i') }} · nilai {{ $submission->final_score }}</summary>
<p>Dikirim oleh {{ $submission->submitter->name }} (ID {{ $submission->submitted_by }}) dan diterapkan oleh {{ $submission->applier?->name ?? 'Akun nonaktif' }} (ID {{ $submission->applied_by ?? '—' }}).<br>Penilai industri: {{ $submission->industry_assessor }} · hasil hitung {{ $submission->calculated_score }}.</p>
@if($submission->hasPhoto())
@include('partials.assessment-image-preview',['previewUrl'=>route('assessments.submissions.photo',$submission),'downloadUrl'=>route('assessments.submissions.photo',[$submission,'download'=>1]),'title'=>'Foto sumber setoran #'.$submission->id.' · '.$submission->photo_original_name,'actionLabel'=>'Lihat foto sumber siswa lebih besar'])
@else<p class="small muted">Setoran ini tidak menyertakan foto; pemeriksaan dapat berasal dari jurnal fisik.</p>@endif
</details>@endforeach</div>
@endif

<div class="card"><h2>Lampirkan bukti industri</h2>
<p class="note-box">Lampiran dapat ditambahkan sebagai bukti administratif. Versi dan status saat unggah dicatat; bukti versi lama tidak otomatis dianggap berlaku untuk perubahan nilai berikutnya.</p>
@if(auth()->user()->isRole('koordinator'))
<form method="POST" enctype="multipart/form-data" action="{{ route('assessments.evidence.store',$assessment) }}">@csrf
<input type="hidden" name="revision" value="{{ $assessment->revision ?? 0 }}">
<div class="form-grid"><label>Dokumen nilai industri (PDF/JPG/PNG, maksimal 2 MB)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></label>
<label>Asal dokumen / keterangan<textarea name="note" minlength="10" maxlength="2000" required placeholder="Contoh: lembar nilai diterima dari pembimbing industri tanggal ...">{{ is_string(old('note')) ? old('note') : '' }}</textarea></label></div>
<p class="small muted">Jika unggahan salah, tambahkan bukti pengganti dengan penjelasan. Lampiran lama tetap tersimpan untuk audit dan hanya dapat diakses koordinator/admin.</p>
<button class="btn btn-primary">Unggah Bukti Industri</button></form>@endif</div>

<div class="card"><h2>Riwayat bukti ({{ $items->total() }})</h2>
@forelse($items as $item)<div style="padding:12px 0;border-bottom:1px solid #ddd;overflow-wrap:anywhere">
<strong>{{ $item->original_name }}</strong><p>{{ number_format($item->size/1024,1) }} KB · {{ $item->mime }}<br>Diunggah {{ $item->created_at->format('d/m/Y H:i') }} oleh {{ $item->uploader->name }} (ID {{ $item->uploaded_by }})<br>Versi saat unggah: {{ $item->assessment_revision }} · {{ $statusLabels[$item->assessment_status] ?? $item->assessment_status }}</p>
<p style="white-space:pre-wrap">{{ $item->note }}</p>
@if(str_starts_with($item->mime,'image/'))
@include('partials.assessment-image-preview',['previewUrl'=>route('assessments.evidence.download',[$item,'inline'=>1]),'downloadUrl'=>route('assessments.evidence.download',$item),'title'=>'Bukti industri #'.$item->id.' · '.$item->original_name,'actionLabel'=>'Lihat bukti industri lebih besar'])
@else<a class="btn btn-secondary" href="{{ route('assessments.evidence.download',$item) }}">Unduh Bukti PDF #{{ $item->id }}</a>@endif</div>
@empty<p>Belum ada bukti industri yang dilampirkan. Nilai di atas tetap merupakan catatan koordinator dan belum dianggap memiliki bukti dokumen.</p>@endforelse
@include('partials.pager')</div>
@include('partials.assessment-image-lightbox')
@endsection
