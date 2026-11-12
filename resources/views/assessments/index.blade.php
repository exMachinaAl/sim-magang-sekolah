@extends('layouts.app')
@section('title','Penilaian Industri')
@section('subtitle','Ketentuan mengikuti template jurusan. Nilai dari industri dicatat dan disahkan oleh koordinator PKL.')
@section('content')
<p class="note-box">Nilai akhir industri di sini terpisah dari nilai kegiatan pada jurnal. Scan QR jurnal tidak otomatis mengesahkan atau menghitung nilai akhir sertifikat.</p>
@if(auth()->user()->isRole('siswa'))
<div class="card"><h2>Setor nilai akhir dari industri</h2><p>Salin nilai persis dari lembar penilaian industri. Foto bukti boleh dilampirkan. Setoran ini belum menjadi nilai resmi sampai diperiksa dan diterapkan koordinator.</p>
@forelse($studentSubmissionPlacements as $studentPlacement)
@php $source=$studentSubmissionSources[$studentPlacement->id]; $latest=$studentLatestSubmissions->get($studentPlacement->id); @endphp
<div class="card"><div class="card-head"><div><h3>{{ $studentPlacement->company->name }}</h3><p>{{ $studentPlacement->period->academic_year }} · {{ $studentPlacement->roster?->major ?? 'Roster belum terhubung' }}</p></div>@if($latest)<span class="pill {{ $latest->status }}">{{ $latest->statusLabel() }}</span>@endif</div>
@if($latest)<p class="small muted">Setoran terakhir {{ $latest->created_at->format('d/m/Y H:i') }} · nilai resmi yang diajukan {{ $latest->final_score }}@if($latest->hasPhoto()) · <a target="_blank" rel="noopener" href="{{ route('assessments.submissions.photo',$latest) }}">Lihat foto bukti</a>@endif</p>@endif
@if($source['issue'])<p class="alert error">{{ $source['issue'] }}</p>
@else
<form method="POST" enctype="multipart/form-data" action="{{ route('assessments.submissions.store') }}">@csrf<input type="hidden" name="placement_id" value="{{ $studentPlacement->id }}">
<p><strong>Kriteria: {{ $source['snapshot']['name'] }}</strong></p>
<div class="form-grid"><label>Nama penilai industri<input name="industry_assessor" required maxlength="150"></label><label>Tanggal penilaian<input type="date" name="assessed_on" required max="{{ now('Asia/Jakarta')->toDateString() }}"></label></div>
@foreach($source['snapshot']['fields'] as $field)
<label>{{ $field['label'] }} @if($field['type']==='score')({{ $field['weight'] }}%)@endif <span class="small muted">{{ $field['description'] }}</span>
@if($field['type']==='score')<input class="submission-score-input" data-submission="{{ $studentPlacement->id }}" data-weight="{{ $field['weight'] }}" type="number" step="0.01" min="0" max="100" name="values[{{ $field['key'] }}]" required>
@else<textarea name="values[{{ $field['key'] }}]" required maxlength="600"></textarea>@endif</label>
@endforeach
<p>Hasil hitung: <strong data-submission-score="{{ $studentPlacement->id }}">Belum lengkap</strong></p>
<div class="form-grid"><label>Nilai akhir resmi dari industri<input type="number" step="0.01" min="0" max="100" name="final_score" required></label><label>Alasan jika berbeda dari hasil hitung<input name="final_score_reason" maxlength="1000"></label><label>Foto lembar nilai (opsional, JPG/PNG maksimal 2 MB)<input type="file" name="photo" accept=".jpg,.jpeg,.png"></label><label>Catatan siswa (opsional)<textarea name="notes" maxlength="2000"></textarea></label></div>
<button class="btn btn-primary">Kirim ke Koordinator</button></form>
@endif</div>
@empty<p>Belum ada penempatan PKL pada tahun yang dipilih.</p>@endforelse
</div>
@endif
<div class="card"><h2>Daftar siswa berdasarkan tahun ajaran dan jurusan</h2>
<form method="GET" action="{{ route('assessments.index') }}"><div class="form-grid">
<label>Tag tahun ajaran<select name="period_id"><option value="" @selected($periodId===null)>Semua tahun</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected((string)$periodId===(string)$period->id)>{{ $period->academic_year }}{{ $period->is_active ? '' : ' (arsip)' }}</option>@endforeach</select></label>
<label>Jurusan pada tahun PKL<select name="major"><option value="">Semua jurusan</option>@foreach($majors as $major)<option value="{{ $major }}" @selected(request('major')===$major)>{{ $major }}</option>@endforeach</select></label>
</div><details @if($search !== '') open @endif><summary>Pencarian lanjutan berdasarkan nama atau NIS</summary><label>Nama atau NIS<input name="q" value="{{ $search }}" placeholder="Opsional: ketik nama atau NIS siswa" maxlength="100"></label></details>
<div class="form-footer"><button class="btn btn-primary">Tampilkan</button><a class="btn btn-ghost" href="{{ route('assessments.index') }}">Reset</a></div></form>
<p class="small muted">Siswa langsung tampil maksimal 10 per halaman mengikuti tahun ajaran aktif dan jurusan yang dipilih. Nama/NIS hanya pencarian lanjutan. Kelas/jurusan mengikuti roster tahun PKL, bukan profil terbaru.</p></div>
@if(auth()->user()->isRole('koordinator','guru'))
<div class="card"><div class="card-head"><div><h2>Setoran nilai dari siswa</h2><p>{{ auth()->user()->isRole('koordinator')?'Periksa kriteria dan foto sumber sebelum menerapkan ke nilai resmi.':'Lihat kriteria dan foto sumber siswa yang Anda dampingi. Perubahan nilai tetap dilakukan koordinator.' }}</p></div><span class="small muted">{{ $submissions->total() }} menunggu</span></div>
<div class="table-wrap"><table><thead><tr><th>Siswa / NIS</th><th>Jurusan / Tahun</th><th>Nilai / Bukti</th><th>Prioritas</th><th>Dikirim</th><th></th></tr></thead><tbody>
@forelse($submissions as $submission)@php $submissionEnd=$submission->placement->roster?->internship_ends_at; $submissionUrgent=$submissionEnd&&$submissionEnd->lte(now('Asia/Jakarta')->startOfDay())&&$submission->placement->assessment?->status!=='approved'; @endphp<tr><td><strong>{{ $submission->placement->student->user->name }}</strong><div>NIS {{ $submission->placement->student->nis }}</div></td><td>{{ $submission->placement->roster?->major ?? 'Roster belum terhubung' }}<div>{{ $submission->placement->period->academic_year }}</div></td><td>Nilai {{ $submission->final_score }}<div>{{ $submission->hasPhoto()?'Ada foto sumber':'Tanpa foto' }}</div></td><td>@if($submissionUrgent)<span class="pill rejected">Segera dinilai</span><div class="small">PKL selesai {{ $submissionEnd->format('d/m/Y') }}</div>@elseif($submissionEnd)<span class="pill">Selesai {{ $submissionEnd->format('d/m/Y') }}</span>@else<span class="small muted">Jadwal belum lengkap</span>@endif</td><td>{{ $submission->created_at->format('d/m/Y H:i') }}</td><td><a class="btn btn-primary btn-sm" href="{{ route('assessments.student',[$submission->placement_id,'submission_id'=>$submission->id]) }}">{{ auth()->user()->isRole('koordinator')?'Periksa Penilaian Siswa':'Lihat Penilaian & Bukti' }}</a></td></tr>
@empty<tr><td colspan="6">Belum ada setoran nilai siswa yang menunggu diterapkan.</td></tr>@endforelse
</tbody></table></div>
@if($submissions instanceof \Illuminate\Contracts\Pagination\Paginator)@include('partials.pager',['items'=>$submissions])@endif
</div>
@endif
@if(auth()->user()->isRole('koordinator'))
<div class="card"><div class="card-head"><div><h2>Daftar siswa untuk penilaian</h2><p class="small muted">{{ $placements->total() }} siswa sesuai tahun, jurusan, dan pencarian.</p></div><a class="btn btn-secondary" href="{{ route('assessment-templates.index') }}">Kelola Template Ketentuan</a></div>
<div class="table-wrap"><table><thead><tr><th>Nama / NIS</th><th>Kelas / Jurusan</th><th>Selesai PKL</th><th>Status nilai</th><th></th></tr></thead><tbody>
@forelse($placements as $placement)
@php $end=$placement->roster?->internship_ends_at; $needsUrgent=$end&&$end->lte(now('Asia/Jakarta')->startOfDay())&&$placement->assessment?->status!=='approved'; @endphp
<tr><td><strong>{{ $placement->student->user->name }}</strong><div>NIS {{ $placement->student->nis }} · {{ $placement->company->name }}</div></td><td>{{ $placement->roster?->class_name ?? '—' }}<div>{{ $placement->roster?->major ?? 'Roster belum terhubung' }}</div></td><td>{{ $end?->format('d/m/Y') ?? 'Belum dijadwalkan' }}@if($needsUrgent)<div class="alert error">Segera dinilai</div>@elseif($placement->pending_submission_count)<div>{{ $placement->pending_submission_count }} setoran menunggu</div>@endif</td><td>{{ $placement->assessment ? (['draft'=>'Draf','submitted'=>'Menunggu pengesahan','revision'=>'Perlu revisi','approved'=>'Disahkan'][$placement->assessment->status]??$placement->assessment->status) : 'Belum dinilai' }}@if($placement->certificate)<div>Sertifikat: {{ $placement->certificate->statusLabel() }}</div>@endif</td><td><a class="btn {{ $needsUrgent?'btn-primary':'btn-secondary' }} btn-sm" href="{{ route('assessments.student',$placement) }}">Buka Penilaian Siswa</a></td></tr>
@empty<tr><td colspan="5">{{ $search !== '' || request()->filled('major') || request()->has('period_id') ? 'Tidak ditemukan penempatan yang sesuai pencarian/filter.' : 'Belum ada penempatan siswa pada periode aktif. Pastikan siswa sudah terdaftar di Penempatan PKL, bukan hanya di Kontrol PKL.' }}</td></tr>@endforelse
</tbody></table></div>@include('partials.pager',['items'=>$placements])
</div>
@endif
<div class="card"><h2>Rekap Penilaian</h2><div class="table-wrap"><table><thead><tr><th>Siswa / Industri</th><th>Ketentuan dan hasil</th><th>Nilai akhir</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($items as $item)<tr><td><strong>{{ $item->placement->student->user->name }}</strong><div>NIS {{ $item->placement->student->nis }} · {{ $item->placement->period->academic_year }}</div><div>{{ $item->placement->roster?->class_name ?? '—' }} · {{ $item->placement->roster?->major ?? 'Roster belum terhubung' }}</div><div>{{ $item->placement->company->name }}</div>@if($item->template_snapshot)<div class="small">Penilai industri: {{ $item->industry_assessor }}<br>Diinput: {{ $item->assessor?->name }}</div>@endif</td><td><details><summary>{{ $item->template_snapshot['name'] ?? 'Skema penilaian lama' }}</summary>@if($item->template_snapshot)@foreach($item->template_snapshot['fields'] as $field)<p><strong>{{ $field['label'] }}: {{ $item->field_values[$field['key']] ?? '—' }}</strong><br>{{ $field['description'] }}</p>@endforeach @else @foreach($item->scores as $score)<p>{{ $score->criterion->name }}: {{ $score->score }}</p>@endforeach @endif</details></td><td>{{ $item->final_score !== null ? number_format($item->final_score,2,',','.') : 'Belum diisi' }}</td><td>{{ ['draft'=>'Draf','submitted'=>'Menunggu validasi','revision'=>'Perlu revisi','approved'=>'Disahkan'][$item->status] ?? $item->status }}</td><td>@if(auth()->user()->isRole('admin','koordinator','guru'))<a class="btn btn-secondary btn-sm" href="{{ route('assessments.student',$item->placement_id) }}">{{ auth()->user()->isRole('guru')?'Lihat Nilai & Bukti':'Buka Penilaian Siswa' }}</a>@endif</td></tr>@empty<tr><td colspan="5">Belum ada penilaian yang sesuai pencarian/filter.</td></tr>@endforelse
</tbody></table></div>@include('partials.pager',['items'=>$items])</div>
@endsection
@push('scripts')<script>(()=>{document.querySelectorAll('[data-submission-score]').forEach(output=>{const group=output.dataset.submissionScore,fields=Array.from(document.querySelectorAll('.submission-score-input[data-submission="'+group+'"]'));const refresh=()=>{output.textContent=fields.some(x=>x.value==='')?'Belum lengkap':fields.reduce((sum,x)=>sum+Number(x.value)*Number(x.dataset.weight)/100,0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});};fields.forEach(x=>x.addEventListener('input',refresh));refresh();});})();</script>@endpush
