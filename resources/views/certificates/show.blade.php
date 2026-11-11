@extends('layouts.app')
@section('title','Detail Sertifikat')
@section('breadcrumb','Sertifikat / Detail')
@section('subtitle','Detail persetujuan, penerbitan, dan penelusuran serah terima dokumen.')
@section('page_actions')<a href="{{ route('certificates.index') }}" class="btn btn-ghost">← Kembali</a>@endsection
@section('content')
@php $frozen=$document?->snapshot; @endphp
@if(auth()->user()->isRole('koordinator'))<p><a class="btn btn-secondary" href="{{ route('certificates.prepare',$certificate->placement_id) }}">Atur template / identitas cetak untuk versi baru</a></p>@endif
<div class="card"><div class="card-head"><div><h2>{{ $certificate->number }}</h2><p>Dokumen versi {{ $certificate->version }}</p></div><span class="pill {{ $certificate->status }}">{{ $certificate->statusLabel() }}</span></div>
<div class="detail-grid"><div><small>Nama Peserta</small><strong>{{ $frozen['student']['name'] ?? $certificate->placement->student->user->name }}</strong></div>
<div><small>NIS / Kelas</small><strong>{{ $frozen['student']['nis'] ?? $certificate->placement->student->nis }} / {{ $frozen['student']['class_name'] ?? $certificate->placement->roster?->class_name }}</strong></div>
<div><small>Perusahaan</small><strong>{{ $frozen['company']['name'] ?? $certificate->placement->company->name }}</strong></div>
<div><small>Periode PKL</small><strong>{{ $frozen['period']['name'] ?? $certificate->placement->period->name }}</strong></div>
<div><small>Tanggal PKL</small><strong>{{ $frozen['starts_at'] ?? $certificate->placement->roster?->internship_starts_at?->toDateString() ?? 'Belum dijadwalkan' }} — {{ $frozen['ends_at'] ?? $certificate->placement->roster?->internship_ends_at?->toDateString() ?? '—' }}</strong></div>
<div><small>Jadwal Pengambilan</small><strong>{{ $certificate->pickup_at?->format('d M Y · H:i')??'Belum ditetapkan' }}</strong></div>
<div><small>Lokasi Pengambilan</small><strong>{{ $certificate->pickup_location??'Belum ditetapkan' }}</strong></div>
<div><small>Dokumen yang perlu dibawa</small><strong>{{ $certificate->pickup_requirements??'—' }}</strong></div>
</div>
@if($document)<p class="note-box">Data di atas adalah salinan versi {{ $document->version }}, direkam {{ $document->created_at->format('d/m/Y H:i') }}. Perubahan master tidak menimpa isi PDF ini.</p>
@if($document->source==='legacy_reconstructed')<p class="alert error">Rekonstruksi dokumen lama dari data saat pengarsipan; bukan bukti identik dengan cetakan asli. {{ $document->note }}</p>@endif
@else<p class="alert error">Dokumen lama belum memiliki arsip. Data di atas masih data hidup; unduhan dan distribusi ditahan sampai pemeriksaan koordinator.</p>@endif
@if($document?->issued_at)<div class="note-box" style="margin-top:18px"><strong>Format dua halaman:</strong> sertifikat depan dan daftar nilai belakang. Setiap keluaran diambil dari arsip dan diperiksa hash.</div><div class="form-footer"><a href="{{ route('certificates.pdf',$certificate) }}" class="btn btn-primary">↓ {{ $document->print_pdf_sha256 ? 'Unduh PDF Digital Berdekorasi' : 'Unduh PDF Sertifikat' }}</a>@if($document->print_pdf_sha256)<a href="{{ route('certificates.print-pdf',$certificate) }}" class="btn btn-secondary">↓ Unduh PDF Cetak Tanpa Dekorasi</a>@endif</div>@endif
</div>
@php
    $activeApproval=$certificate->approvals->firstWhere('party','coordinator');
    $proposalReady=(bool)$document;
    $validated=$activeApproval?->status==='approved';
    $printed=in_array($certificate->status,['printed','sent_to_company','returned_to_school','ready_pickup','handed_to_student','completed'],true);
@endphp
<div class="card"><h2>Alur sertifikat versi {{ $certificate->version }}</h2>
<div class="detail-grid">
<div><small>1. Proposal</small><strong>{{ $proposalReady ? 'Tersimpan' : 'Belum lengkap' }}</strong></div>
<div><small>2. Validasi koordinator</small><strong>{{ $validated ? 'Disetujui' : ($activeApproval?->status==='rejected' ? 'Ditolak' : 'Menunggu') }}</strong></div>
<div><small>3. Pencetakan</small><strong>{{ $printed ? 'Sudah diproses' : 'Belum diproses' }}</strong></div>
<div><small>4. Distribusi</small><strong>{{ App\Models\Certificate::statuses()[$certificate->status] ?? $certificate->status }}</strong></div>
</div><p class="small muted">Hanya langkah kedua yang merupakan pengesahan aplikasi. Nama kepala sekolah dan pembimbing pada desain adalah penandatangan dokumen, bukan tombol persetujuan tambahan.</p></div>

@include('certificates.partials.approval-panel')
@include('certificates.partials.document-tools')

@if($document)
<details class="card" @if(!$document->issued_at) open @endif><summary>Isi penilaian yang dibekukan pada versi ini</summary>
<p><strong>Nilai akhir resmi: {{ $frozen['assessment']['final_score'] }}</strong> · Penilai industri: {{ $frozen['assessment']['industry_assessor'] ?: 'Skema lama' }}</p>
<div class="table-wrap"><table><thead><tr><th>Ketentuan / aspek</th><th>Hasil</th><th>Bobot</th></tr></thead><tbody>
@if($frozen['assessment']['template_snapshot'])
@foreach($frozen['assessment']['template_snapshot']['fields'] as $field)<tr><td>{{ $field['label'] }}<div class="small muted">{{ $field['description'] }}</div></td><td>{{ $frozen['assessment']['field_values'][$field['key']] }}</td><td>{{ $field['type']==='score'?$field['weight'].'%':'—' }}</td></tr>@endforeach
@else
@foreach($frozen['scores'] as $score)<tr><td>{{ $score['name'] }}</td><td>{{ $score['score'] }}</td><td>{{ $score['weight'] }}%</td></tr>@endforeach
@endif
</tbody></table></div><p class="small muted">Ini salinan isi proposal, bukan pembacaan ulang nilai aktif. Jika ada kesalahan, koreksi sumber lalu ajukan versi baru.</p></details>
@endif
@if(!$document && $certificate->issued_at && auth()->user()->isRole('koordinator'))
<div class="card"><h2>Periksa & arsipkan dokumen lama</h2><p>Bandingkan data saat ini dengan dokumen asli terlebih dahulu. Sistem tidak bisa memulihkan isi cetakan lama yang tidak pernah disimpan. Catatan pemeriksaan wajib; ini tidak otomatis mengganti dokumen fisik yang sudah dibagikan.</p>
<form method="POST" action="{{ route('certificates.archive-legacy',$certificate) }}">@csrf<input type="hidden" name="version" value="{{ $certificate->version }}"><label>Dasar pemeriksaan / perbedaan<textarea name="note" required minlength="10" maxlength="2500"></textarea></label><button class="btn btn-secondary">Arsipkan rekonstruksi dari data saat ini</button></form></div>@endif
<div class="card"><div class="card-head"><h2>Riwayat Serah Terima</h2></div>
@forelse($certificate->movements->sortByDesc('moved_at') as $move)<div style="padding:11px 0;border-bottom:1px solid #edf1f7"><strong>{{ \App\Models\Certificate::statuses()[$move->to_status]??$move->to_status }}</strong><div class="small muted">{{ $move->moved_at->format('d M Y H:i') }} · {{ $move->actor->name }} · Penerima: {{ $move->recipient }} · {{ $move->version ? 'Versi '.$move->version : 'Arsip lama: versi tidak tercatat' }}</div>@if($move->note)<div class="small">{{ $move->note }}</div>@endif</div>
@empty<div class="empty">Belum ada perpindahan dokumen.</div>@endforelse
</div>
@if($document?->issued_at && auth()->user()->isRole('koordinator')&&in_array($certificate->status,['awaiting_print','printed','sent_to_company','returned_to_school','ready_pickup','handed_to_student']))
@php $next=['awaiting_print'=>'Tandai Dicetak','printed'=>'Kirim ke Perusahaan','sent_to_company'=>'Kembali ke Sekolah','returned_to_school'=>'Siap Diambil Siswa','ready_pickup'=>'Serahkan ke Siswa','handed_to_student'=>'Tandai Selesai']; @endphp
<div class="card"><div class="card-head"><div><h2>Perbarui Status Distribusi</h2><p>Setiap perpindahan tersimpan bersama koordinator, penerima, waktu, dan keterangan.</p></div></div>
<form method="POST" action="{{ route('certificates.movement',$certificate) }}">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $certificate->version }}"><input type="hidden" name="expected_status" value="{{ $certificate->status }}"><div class="form-grid">
<div class="form-field"><label>Nama Penerima / Tujuan</label><input name="recipient" required placeholder="Nama pihak yang menerima dokumen"></div>
<div class="form-field"><label>Keterangan Serah Terima</label><input name="note" placeholder="Nomor bukti, kondisi, atau informasi lain"></div>
@if($certificate->status==='returned_to_school')<div class="form-field"><label>Jadwal Pengambilan Siswa</label><input name="pickup_at" type="datetime-local" required></div><div class="form-field"><label>Lokasi Pengambilan</label><input name="pickup_location" required placeholder="Ruang TU / Humas"></div><div class="form-field wide"><label>Persyaratan Dibawa</label><input name="pickup_requirements" placeholder="Identitas siswa, tanda terima, ..."></div>@endif
</div><div class="form-footer"><button class="btn btn-primary" data-confirm="Lanjutkan ke status berikutnya?">{{ $next[$certificate->status] }} →</button></div></form></div>@endif
<div class="card"><h2>Arsip per versi @include('partials.inline-help',['label'=>'arsip versi','text'=>'Setiap versi disimpan terpisah dan diperiksa hash. Gunakan versi terbaru untuk operasional, sedangkan versi lama tetap tersedia sebagai riwayat dan tidak dibangun ulang.'])</h2><p>Versi lama untuk penelusuran, bukan pengganti versi terbaru. Proposal yang belum disahkan tidak dapat diunduh sebagai sertifikat.</p>
@forelse($certificate->documents->sortByDesc('version') as $archive)<p>Versi {{ $archive->version }} · {{ $archive->issued_at?'Pernah disahkan':'Proposal belum disahkan' }} · {{ $archive->source }} · {{ $archive->note }}
@if($archive->issued_at)<a href="{{ route('certificates.pdf',['certificate'=>$certificate,'version'=>$archive->version]) }}">{{ $archive->print_pdf_sha256 ? 'PDF digital' : 'PDF sertifikat' }} v{{ $archive->version }}</a>@if($archive->print_pdf_sha256) · <a href="{{ route('certificates.print-pdf',['certificate'=>$certificate,'version'=>$archive->version]) }}">PDF cetak bersih v{{ $archive->version }}</a>@endif
@if($archive->front_hash || $archive->wordExport) · <a href="{{ route('certificates.docx',[$certificate,'front','version'=>$archive->version]) }}">DOCX depan v{{ $archive->version }}</a> · <a href="{{ route('certificates.docx',[$certificate,'back','version'=>$archive->version]) }}">DOCX belakang v{{ $archive->version }}</a>@endif
@endif</p>@empty<p>Belum ada arsip.</p>@endforelse</div>
@endsection
