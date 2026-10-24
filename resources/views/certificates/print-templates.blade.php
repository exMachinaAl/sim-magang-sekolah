@extends('layouts.app')
@section('title','Template Cetak Sertifikat')
@section('subtitle','Format depan dan belakang dipilih terpisah. Nilai tetap bersumber dari industri dan dicatat koordinator.')
@section('content')
<p><a href="{{ route('certificates.index') }}" class="btn btn-ghost">Kembali ke Sertifikat</a></p>
<div class="card"><h2>1. Pilih ketentuan penilaian jurusan</h2>
<form method="GET"><div class="form-field"><label for="assessment-template">Template ketentuan (bukan siswa)</label><select id="assessment-template" name="assessment_template_id" required><option value="">Pilih template</option>@foreach($assessmentTemplates as $a)<option value="{{ $a->id }}" @selected($assessmentTemplate?->id===$a->id)>{{ $a->major }} · {{ $a->name }} #{{ $a->id }}</option>@endforeach</select></div><button class="btn btn-primary">Lanjutkan</button></form></div>
@if($assessmentTemplate)
<div class="card"><h2>2. Buat versi template cetak</h2><p>Ketentuan: {{ $assessmentTemplate->name }} · {{ $assessmentTemplate->major }}. Template tersimpan tidak ditimpa. Untuk revisi, buat versi baru.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('certificate-print-templates.store') }}">@csrf
<input type="hidden" name="assessment_template_id" value="{{ $assessmentTemplate->id }}">
<div class="form-grid">
<label>Nama versi template<input name="name" required maxlength="150" value="{{ old('name') }}"></label>
<label>Sertifikat depan<select name="front_variant">@foreach(config('certificate_print.front_variants') as $key=>$label)<option value="{{ $key }}" @selected(old('front_variant')===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Tanda tangan daftar nilai<select name="back_variant">@foreach(config('certificate_print.back_variants') as $key=>$label)<option value="{{ $key }}" @selected(old('back_variant')===$key)>{{ $label }}</option>@endforeach</select></label>
@foreach(config('certificate_print.settings') as $key=>$label)<label>{{ $label }}<input name="settings[{{ $key }}]" required maxlength="150" value="{{ old('settings.'.$key, in_array($key,['nama_sekolah_judul','nama_sekolah_ttd']) ? 'SMK Negeri 1 Cerme Gresik' : '') }}"></label>@endforeach
<label>Susunan nilai<select name="grade_format"><option value="custom" @selected(old('grade_format')==='custom')>Aspek bebas sesuai ketentuan</option><option value="80_20" @selected(old('grade_format')==='80_20')>Format asli: rata-rata 4 aspek (80%) + laporan (20%)</option></select></label>
</div>
<p>Untuk format asli, petakan aspek di bawah. Wajib tepat lima aspek angka berbobot 20% masing-masing. Empat aspek utama harus angka bulat; nilai resmi tidak dibulatkan ulang.</p>
<div class="form-grid">@foreach(config('certificate_print.mapping') as $key=>$label)<label>{{ $label }}<select name="score_mapping[{{ $key }}]"><option value="">Tidak dipetakan (format bebas)</option>@foreach($assessmentTemplate->fields as $field)<option value="{{ $field['key'] }}" @selected(old('score_mapping.'.$key)===$field['key'])>{{ $field['label'] }} · {{ $field['weight'] }}%</option>@endforeach</select></label>@endforeach</div>
<h3>Format berkas</h3>
<p>PDF bawaan berisi teks sederhana. PDF berdekorasi memakai desain kosong Anda sebagai latar; identitas, aspek nilai dan tanda tangan diisi sistem. Kedua pilihan bekerja tanpa LibreOffice.</p>
<label>Format cetak<select name="renderer"><option value="builtin" @selected(old('renderer')==='builtin')>PDF bawaan (tanpa dekorasi)</option><option value="pdf_background" @selected(old('renderer')==='pdf_background')>PDF berdekorasi (tanpa LibreOffice)</option><option value="docx" @selected(old('renderer')==='docx')>Ekstensi opsional: DOCX → PDF lewat LibreOffice</option></select></label>
<fieldset style="margin:16px 0;min-width:0"><legend>Untuk PDF berdekorasi</legend>
<p>Unggah satu PDF A4 portrait per sisi, tanpa sandi (maks. 2 MB per file). Kosongkan bagian tengah untuk isi otomatis; jangan masukkan nama/nilai siswa ke desain. PDF yang tidak didukung perlu diekspor ulang sebagai PDF 1.4.</p>
<div class="form-grid">@foreach(['front'=>'Depan','back'=>'Belakang'] as $side=>$label)<div class="form-field"><label>{{ $label }} — PDF<input type="file" name="{{ $side }}_pdf" accept=".pdf,application/pdf"></label>
<p><a href="{{ route('certificate-print-templates.pdf-sample',$side) }}">Unduh contoh dekorasi {{ strtolower($label) }}</a></p>
@foreach(['x'=>'Jarak dari kiri','y'=>'Jarak dari atas','width'=>'Lebar area isi','height'=>'Tinggi area isi'] as $key=>$caption)<label>{{ $caption }} (mm)<input type="number" step="0.01" min="0" max="297" name="pdf_layout[{{ $side }}][{{ $key }}]" value="{{ old('pdf_layout.'.$side.'.'.$key,\App\Support\CertificatePdfBackground::defaultLayout()[$side][$key]) }}"></label>@endforeach
</div>@endforeach</div><p class="small muted">Koordinat mengatur kotak seluruh isi pada tiap halaman, bukan posisi bebas setiap field. Tepi minimal 5 mm; area minimal 120 × 140 mm. Nama dan ketentuan membungkus di dalam area; isi yang melebihi satu halaman ditolak. Uji melalui Pratinjau PDF sebelum mengajukan.</p></fieldset>
<details><summary>Ekstensi DOCX / LibreOffice (opsional)</summary><p>Jalur DOCX tetap tersedia. Unggah dua berkas A4 portrait dengan token <code>${nama_siswa}</code> dan token lain pada contoh. Tidak menerima makro atau tautan eksternal.</p>
<div class="form-grid"><label>DOCX depan (maks. 4 MB)<input type="file" name="front_docx" accept=".docx"></label><label>DOCX belakang (maks. 4 MB)<input type="file" name="back_docx" accept=".docx"></label></div>
@if(!config('certificate_print.libreoffice') || !is_file(config('certificate_print.libreoffice')))<p class="note-box">LibreOffice belum dikonfigurasi. Hanya konversi DOCX yang memerlukannya; PDF bawaan dan PDF berdekorasi tetap tersedia.</p>@endif</details>
<div class="form-footer"><button class="btn btn-primary">Simpan Template Cetak Baru</button></div></form></div>
@endif
<div class="card"><h2>Contoh DOCX dari paket Anda</h2><p>Contoh ini menggunakan format nilai 80/20. Sesuaikan gaya di Word, pertahankan token. Untuk aspek bebas gunakan <code>${aspek_1}</code>, <code>${deskripsi_1}</code>, <code>${hasil_1}</code> sampai seluruh aspek (maks. 12). Urutan mengikuti template ketentuan.</p>
@foreach(array_merge(config('certificate_print.front_variants'),config('certificate_print.back_variants')) as $key=>$label)<p><a href="{{ route('certificate-print-templates.sample',$key) }}">{{ $label }} — DOCX</a></p>@endforeach</div>
<div class="card"><h2>Versi tersedia</h2><div class="table-wrap"><table><thead><tr><th>Template</th><th>Ketentuan / Jurusan</th><th>Depan / Belakang</th><th>Format</th></tr></thead><tbody>@forelse($items as $item)<tr><td>#{{ $item->id }} {{ $item->name }}</td><td>{{ $item->assessmentTemplate->name }}<br>{{ $item->assessmentTemplate->major }}</td><td>{{ config('certificate_print.front_variants.'.$item->front_variant) }}<br>{{ config('certificate_print.back_variants.'.$item->back_variant) }}</td><td>{{ $item->formatLabel() }}</td></tr>@empty<tr><td colspan="4">Belum ada template cetak.</td></tr>@endforelse</tbody></table></div>@include('partials.pager')</div>
@endsection
