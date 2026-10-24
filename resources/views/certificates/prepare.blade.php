@extends('layouts.app')
@section('title','Siapkan Sertifikat')
@section('subtitle','Lengkapi data cetak siswa. Sumber nilai dan jadwal tidak diubah dari halaman ini.')
@section('content')
<p><a class="btn btn-ghost" href="{{ route('certificates.index',['q'=>$placement->student->nis]) }}">Kembali ke pencarian</a> <a class="btn btn-secondary" href="{{ route('certificate-print-templates.index',['assessment_template_id'=>$placement->assessment?->assessment_template_id]) }}">Kelola template cetak</a></p>
@if($placement->certificate)<p><a class="btn btn-primary" href="{{ route('certificates.show',$placement->certificate) }}">Buka detail &amp; persetujuan</a></p>@endif
<div class="card"><h2>{{ $placement->student->user->name }} · {{ $placement->student->nis }}</h2><p>{{ $placement->roster?->class_name }} · {{ $placement->roster?->major }} · {{ $placement->period->academic_year }}<br>{{ $placement->company->name }}</p>
<p>Tanggal lahir dari Data Siswa: {{ $placement->student->birth_date?->format('d/m/Y') ?? 'Belum diisi — lengkapi Data Siswa' }}. Penilai industri: {{ $placement->assessment?->industry_assessor ?? 'Belum tersedia' }}.</p>
<form method="GET"><label>Template cetak yang sesuai ketentuan siswa<select name="print_template_id" required><option value="">Pilih template cetak</option>@foreach($templates as $t)<option value="{{ $t->id }}" @selected($selected?->id===$t->id)>{{ $t->name }} #{{ $t->id }} · {{ $t->front_hash?'DOCX':'PDF bawaan' }}</option>@endforeach</select></label><button class="btn btn-secondary">Pilih Format</button></form>
@if($templates->isEmpty())<p>Belum ada template cetak untuk versi penilaian siswa ini. Buat melalui Kelola template cetak. Penilaian skema lama harus diperiksa dahulu, tidak dikonversi otomatis.</p>@endif</div>
@if($selected)
@php $existing=$placement->certificate; $options=$existing?->print_options ?? []; @endphp
<div class="card"><h2>{{ $existing ? 'Ajukan versi baru' : 'Lengkapi data penerbitan' }}</h2><p>{{ config('certificate_print.front_variants.'.$selected->front_variant) }} / {{ config('certificate_print.back_variants.'.$selected->back_variant) }}. Nama di tanda tangan bukan pemberian hak akses sistem.</p>
<form method="POST" action="{{ $existing ? route('certificates.resubmit',$existing) : route('certificates.create') }}">@csrf
@if($existing)@method('PUT')<input type="hidden" name="version" value="{{ $existing->version }}">@endif
<input type="hidden" name="placement_id" value="{{ $placement->id }}"><input type="hidden" name="print_template_id" value="{{ $selected->id }}">
<div class="form-grid">
@if(!$existing)<label>Nomor sertifikat resmi (kosong = nomor SIMMAG)<input name="number" maxlength="100" value="{{ old('number') }}"></label>@else<p>Nomor tetap: {{ $existing->number }}. Versi baru tidak menimpa PDF lama.</p>@endif
@foreach(['birth_place'=>'Tempat lahir','bidang_pekerjaan'=>'Bidang pekerjaan saat PKL','nisn_nipd'=>'NISN / NIPD pada daftar nilai'] as $key=>$label)<label>{{ $label }}<input name="print_options[{{ $key }}]" required maxlength="{{ $key==='nisn_nipd'?50:100 }}" value="{{ old('print_options.'.$key,$options[$key] ?? ($key==='nisn_nipd' ? ($placement->student->nisn ?: $placement->student->nis) : '')) }}"></label>@endforeach
<label>Tanggal pengesahan pada dokumen<input type="date" name="print_options[tanggal_pengesahan]" required max="{{ now('Asia/Jakarta')->toDateString() }}" value="{{ old('print_options.tanggal_pengesahan',$options['tanggal_pengesahan'] ?? now('Asia/Jakarta')->toDateString()) }}"></label>
@if($selected->front_variant==='kepala_sekolah_dan_industri')<label>Nama pimpinan industri<input name="print_options[nama_pimpinan_industri]" required maxlength="150" value="{{ old('print_options.nama_pimpinan_industri',$options['nama_pimpinan_industri'] ?? '') }}"></label>@endif
@if($selected->back_variant==='pembimbing_sekolah')
<label>Nama pembimbing sekolah penandatangan<input name="print_options[nama_pembimbing]" required maxlength="150" value="{{ old('print_options.nama_pembimbing',$options['nama_pembimbing'] ?? '') }}"></label><label>NIP pembimbing penandatangan<input name="print_options[nip_pembimbing]" required maxlength="50" value="{{ old('print_options.nip_pembimbing',$options['nip_pembimbing'] ?? '') }}"></label>
@else<p>Penandatangan nilai diambil dari penilai industri pada penilaian disahkan: {{ $placement->assessment?->industry_assessor }}.</p>@endif
@if($existing)<label>Alasan revisi<textarea name="note" required maxlength="2500">{{ old('note') }}</textarea></label>@endif
</div><p>Nilai akhir resmi: {{ $placement->assessment?->final_score }}. Semua isi disimpan sebagai arsip versi saat diajukan. Pengesahan aplikasi tetap dilakukan koordinator.</p><button class="btn btn-primary">{{ $existing?'Ajukan Versi Baru':'Buat & Ajukan Sertifikat' }}</button>
</form></div>
@endif
@endsection
