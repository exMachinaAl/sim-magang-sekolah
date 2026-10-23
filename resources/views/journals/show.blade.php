@extends('layouts.app')
@section('title','Detail Jurnal Siswa')
@section('subtitle','Catatan siswa, review sekolah, dan validasi industri dipisahkan. Foto dan validasi industri tidak wajib untuk mencatat.')
@section('page_actions')
<a href="{{ route('journals.index') }}" class="btn btn-ghost">Kembali</a>
@endsection
@section('content')
<div class="card">
<h2>{{ $placement->student->user->name }}</h2>
<p>NIS {{ $placement->student->nis }} · {{ $placement->roster?->class_name ?? $placement->student->class_name }} · {{ $placement->roster?->major ?? $placement->student->major }}</p>
<p>{{ $placement->company->name }} · {{ $placement->period->name }} · {{ $placement->lifecycleLabel() }}</p>
<p>Jadwal: {{ $placement->roster?->internship_starts_at?->format('d/m/Y') ?? '—' }} – {{ $placement->roster?->internship_ends_at?->format('d/m/Y') ?? '—' }}</p>
<form method="GET" action="{{ route('journals.excel',$placement) }}" class="filterbar">
<label>Dari tanggal<input type="date" name="date_from"></label><label>Sampai tanggal<input type="date" name="date_to"></label>
<button class="btn btn-primary">Unduh Excel · 6 entri/lembar</button>
<a href="{{ route('journals.pdf',['placement_id'=>$placement->id]) }}" class="btn btn-secondary">Rekap PDF</a>
</form><p class="small muted">Excel mengikuti template mingguan. Draf tidak diekspor; nilai belum divalidasi tetap ditandai. Foto tidak dicetak.</p>
</div>
@if(auth()->user()->isRole('siswa'))
@if($canWrite)
<div class="card" id="journal-form">
<h2 id="journal-form-title">Tulis Jurnal Kegiatan</h2>
<p>Satu ringkasan per tanggal; beberapa kegiatan hari yang sama dapat ditulis bersama. Tanggal kegiatan harus dalam jadwal PKL, tetapi pencatatan dan scan boleh dilakukan belakangan.</p>
<form method="POST" action="{{ route('journals.store') }}" enctype="multipart/form-data" id="journal-editor">@csrf
<input type="hidden" name="placement_id" value="{{ $placement->id }}"><input id="journal-revision" type="hidden" name="revision" value="{{ old('revision',0) }}">
<div class="form-grid">
<label>Tanggal kegiatan<input id="journal-date" type="date" name="activity_date" required value="{{ old('activity_date',now()->toDateString()) }}" max="{{ now()->toDateString() }}"></label>
<label>Ruang kerja<input id="journal-work_area" name="work_area" maxlength="150" value="{{ old('work_area') }}"></label>
<label>Mulai<input id="journal-starts_time" type="time" name="starts_time" value="{{ old('starts_time') }}"></label>
<label>Selesai<input id="journal-ends_time" type="time" name="ends_time" value="{{ old('ends_time') }}"></label>
<label>Kegiatan<textarea id="journal-activity" name="activity" required minlength="3" maxlength="5000">{{ old('activity') }}</textarea></label>
<label>Keterangan peralatan / bahan<textarea id="journal-equipment_notes" name="equipment_notes" maxlength="3000">{{ old('equipment_notes') }}</textarea></label>
<label>Nilai dari pembimbing industri (opsional)<input id="journal-industry_score" name="industry_score" type="number" step="0.01" min="0" max="100" value="{{ old('industry_score') }}"><small>Catat angka persis dari pembimbing. Kosong bukan nol. Nilai ini bukan nilai akhir sertifikat.</small></label>
<label>Foto (opsional)<input name="photos[]" type="file" accept=".jpg,.jpeg,.png" multiple><small>Maksimal total 5 foto, masing-masing 4 MB. Boleh dikirim tanpa foto.</small></label>
<label>Hasil pekerjaan (opsional)<textarea id="journal-outcome" name="outcome" maxlength="5000">{{ old('outcome') }}</textarea></label>
<label>Kendala (opsional)<textarea id="journal-obstacles" name="obstacles" maxlength="3000">{{ old('obstacles') }}</textarea></label>
</div>
<p class="note-box">Predikat otomatis: B− [75,81), B [81,86), B+ [86,91), A− [91,96), A [96,100]. Nilai di bawah 75 tetap disimpan dan dicetak di keterangan, tidak dipaksa masuk predikat.</p>
<div class="form-footer"><button class="btn btn-ghost" name="intent" value="draft">Simpan Draf</button><button class="btn btn-primary" name="intent" value="submitted">Simpan Jurnal</button><button class="btn btn-secondary" type="button" id="journal-new">Entri tanggal lain</button></div>
<p class="small">Perubahan membuat versi baru; review dan validasi versi lama tetap ada di audit, tetapi tidak mengesahkan versi baru.</p>
</form>
</div>
@else<div class="note-box">Jadwal Kontrol PKL belum lengkap atau siswa tidak mengikuti PKL. Hubungi koordinator.</div>@endif
@endif
<div class="card"><h2>Rincian Jurnal Harian</h2>
@forelse($journals as $journal)
<article style="border-bottom:1px solid #ddd;padding:18px 0">
<h3>{{ $journal->activity_date->locale('id')->translatedFormat('l, d F Y') }} · versi {{ $journal->revision }}</h3>
<p><span class="pill">{{ $journal->statusLabel() }}</span> <span class="pill">{{ $journal->industryValidated() ? 'Tervalidasi industri' : 'Belum divalidasi industri' }}</span></p>
<div class="detail-grid"><div><small>Kegiatan</small><strong style="white-space:pre-wrap">{{ $journal->activity }}</strong></div>
<div><small>Ruang / waktu</small><strong>{{ $journal->work_area ?: '—' }} · {{ $journal->starts_time ?: '—' }} – {{ $journal->ends_time ?: '—' }}</strong></div>
<div><small>Peralatan / bahan</small><strong>{{ $journal->equipment_notes ?: '—' }}</strong></div>
<div><small>Nilai dicatat siswa dari industri</small><strong>{{ $journal->industry_score ?? 'Belum diisi' }} · {{ $journal->scoreCategory() ?? '—' }}</strong></div>
<div><small>Hasil / kendala</small><strong>{{ $journal->outcome ?: '—' }} / {{ $journal->obstacles ?: '—' }}</strong></div></div>
@if($journal->reviewed_at)<p>Ditinjau {{ $journal->reviewer?->name }} · {{ $journal->reviewed_at->format('d/m/Y H:i') }}: {{ $journal->review_note ?: 'Sudah dibaca' }}</p>@endif
<div class="photo-grid">@foreach($journal->attachments as $photo)<a href="{{ route('journals.photo',$photo) }}" target="_blank" rel="noopener"><img class="photo-thumb" src="{{ route('journals.photo',$photo) }}" alt="Foto kegiatan"></a>@endforeach</div>
@if($canWrite)<button type="button" class="btn btn-secondary btn-sm" data-edit-journal="{{ $journal->id }}">Edit / isi nilai</button>@endif
@if(auth()->user()->isRole('siswa') && $journal->status!=='draft' && $journal->industry_score!==null)
<form method="POST" action="{{ route('journals.qr',$journal) }}" style="display:inline">@csrf<button class="btn btn-primary btn-sm">Tampilkan ringkasan & QR untuk DUDI</button></form>
@endif
@if(auth()->user()->isRole('koordinator','guru') && $journal->status!=='draft')
<form method="POST" action="{{ route('journals.review',$journal) }}" class="filterbar">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ $journal->revision }}"><label>Catatan sekolah (opsional)<input name="review_note" maxlength="2000"></label><button class="btn btn-secondary">Tandai sudah ditinjau</button>
</form><p class="small muted">Tidak menggantikan validasi industri dan tidak menghalangi siswa mencatat. Tindak lanjut dilakukan offline.</p>
@endif
@if($journal->industryValidations->isNotEmpty())
<details><summary>Riwayat validasi industri</summary>
@foreach($journal->industryValidations->sortByDesc('revision') as $validation)
<p>Versi {{ $validation->revision }} · {{ $validation->snapshot['mentor_name'] }} · nilai {{ $validation->snapshot['industry_score'] }} · kegiatan {{ $validation->snapshot['activity_date'] }} · scan {{ $validation->scanned_at->format('d/m/Y H:i:s') }}
{{ $validation->revision===$journal->revision ? '(versi saat ini)' : '(versi lama, isi sudah berubah)' }}</p>
@endforeach
</details>@endif
</article>
@empty<p>Belum ada jurnal.</p>@endforelse
@include('partials.pager',['items'=>$journals])
</div>
<details class="card"><summary>Timeline Status · 100 aktivitas sekolah/siswa terbaru</summary>
@foreach($events as $event)<p>{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->actor?->name ?? 'Arsip' }} · {{ $event->note }}</p>@endforeach
</details>
@endsection
@push('scripts')
@if($canWrite)
<script>
const editableJournals=@json($editableJournals);
document.querySelectorAll('[data-edit-journal]').forEach(button=>button.addEventListener('click',()=>{
 const journal=editableJournals[button.dataset.editJournal]; if(!journal)return;
 ['date','revision','activity','outcome','obstacles','work_area','starts_time','ends_time','equipment_notes','industry_score'].forEach(key=>document.getElementById('journal-'+key).value=journal[key]??'');
 document.getElementById('journal-date').readOnly=true;
 document.getElementById('journal-form-title').textContent='Edit jurnal '+journal.date;
 document.getElementById('journal-form').scrollIntoView({behavior:'smooth'});
}));
document.getElementById('journal-new').addEventListener('click',()=>{
 document.getElementById('journal-editor').reset(); document.getElementById('journal-revision').value=0;
 document.getElementById('journal-date').readOnly=false; document.getElementById('journal-form-title').textContent='Tulis Jurnal Kegiatan';
});
</script>
@endif
@endpush
