@extends('layouts.app')
@section('title','Detail Sertifikat')
@section('breadcrumb','Sertifikat / Detail')
@section('subtitle','Detail persetujuan, penerbitan, dan penelusuran serah terima dokumen.')
@section('page_actions')<a href="{{ route('certificates.index') }}" class="btn btn-ghost">← Kembali</a>@endsection
@section('content')
@php $frozen=$document?->snapshot; @endphp
@if(auth()->user()->isRole('koordinator'))<p><a class="btn btn-secondary" href="{{ route('certificates.prepare',$certificate->placement_id) }}">Atur template / identitas cetak untuk versi baru</a></p>@endif
@if(isset($frozen['print']))<div class="card"><h2>Format dokumen versi ini</h2><p>{{ $frozen['print']['template_name'] }} · {{ config('certificate_print.front_variants.'.$frozen['print']['front_variant']) }} / {{ config('certificate_print.back_variants.'.$frozen['print']['back_variant']) }}</p><p>Kepala sekolah: {{ $frozen['print']['values']['nama_kepala_sekolah'] }} · NIP {{ $frozen['print']['values']['nip_kepala_sekolah'] }}<br>Penandatangan nilai: {{ $frozen['print']['values']['nama_pembimbing'] }}</p>
@if(isset($frozen['print']['pdf_background']))<p>PDF berdekorasi: desain dan area isi terikat pada versi ini. Salinan DOCX memakai susunan Word tersendiri, tanpa menyalin dekorasi PDF.</p>@endif
</div>@endif
@if($document?->issued_at)
<div class="card"><h2>Word / DOCX versi {{ $document->version }}</h2>
@if($document->front_hash || $document->wordExport)
<div class="form-footer"><a class="btn btn-primary" href="{{ route('certificates.docx',[$certificate,'front']) }}">Unduh DOCX depan</a><a class="btn btn-secondary" href="{{ route('certificates.docx',[$certificate,'back']) }}">Unduh DOCX belakang</a></div>
@if($document->wordExport)<p>Salinan Word dibuat dari data arsip versi ini. Depan memakai template asli; belakang {{ $document->wordExport->layout==='paket_asli_80_20'?'memakai template asli 80/20.':'disusun sesuai seluruh aspek bebas, termasuk hasil teks.' }} PDF bawaan tetap menggunakan layout sebelumnya dan tidak berubah.</p>@endif
@elseif(isset($frozen['print']) && auth()->user()->isRole('koordinator'))
<p>Siapkan DOCX depan dan belakang dari arsip yang sudah disahkan, tanpa LibreOffice. Tidak membaca ulang data siswa/nilai yang mungkin sudah berubah.</p>
<form method="POST" action="{{ route('certificates.prepare-word',$certificate) }}">@csrf<input type="hidden" name="version" value="{{ $document->version }}"><button class="btn btn-primary">Siapkan DOCX dari arsip</button></form>
@else<p>DOCX belum tersedia. Koordinator perlu menyiapkan salinan Word; arsip lama tanpa identitas cetak lengkap memerlukan revisi versi baru.</p>@endif
<p class="small muted">Buka kedua berkas di Word, cek font, ukuran A4, dan jumlah halaman sebelum cetak depan–belakang. Layout cetak Word belum diverifikasi di perangkat Anda. Perubahan manual pada DOCX tidak mengubah nilai atau PDF arsip sistem.</p></div>
@endif
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
@if($document?->issued_at)<div class="note-box" style="margin-top:18px"><strong>Format cetak dua halaman:</strong> sertifikat depan dan daftar nilai belakang. PDF diambil dari arsip, dengan pemeriksaan hash.</div><div class="form-footer"><a href="{{ route('certificates.pdf',$certificate) }}" class="btn btn-primary">↓ Unduh Sertifikat + Nilai PDF</a></div>@endif
</div>
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
<div class="two-cols"><div class="card"><div class="card-head"><h2>Persetujuan Sertifikat</h2></div>
@foreach($certificate->approvals as $a)<div style="padding:15px 0;border-bottom:1px solid #edf1f7"><strong>Koordinator PKL</strong><span class="pill {{ $a->status }}" style="float:right">{{ ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'][$a->status]??$a->status }}</span>
<div class="small muted" style="margin-top:7px">{{ $a->note??'Belum ada catatan.' }} @if($a->decided_at) · {{ $a->decided_at->format('d M Y H:i') }}@endif</div>
@if($a->status==='pending'&&$certificate->status==='awaiting_approval'&&auth()->user()->isRole('koordinator')&&$a->party==='coordinator')
<form method="POST" action="{{ route('certificates.approve',$certificate) }}" style="margin-top:12px">@csrf @method('PUT')<label>Catatan (wajib jika menolak)</label><textarea name="note" style="min-height:65px" placeholder="Alasan atau keterangan persetujuan"></textarea>
<input type="hidden" name="version" value="{{ $certificate->version }}">
<div class="form-footer"><button class="btn btn-primary btn-sm" name="decision" value="approved" @disabled(!$document)>✓ Setujui</button><button class="btn btn-danger btn-sm" name="decision" value="rejected">× Tolak</button></div></form>@if(!$document)<p>Proposal lama belum memiliki arsip. Gunakan Ajukan Versi Baru setelah memeriksa sumber.</p>@endif @endif</div>
@endforeach
@if($certificate->approvalEvents->isNotEmpty())<div class="divider"></div><h3>Riwayat Keputusan Persetujuan</h3>
@foreach($certificate->approvalEvents->sortByDesc('created_at') as $event)
<div class="small" style="padding:8px 0"><strong>Versi {{ $event->version }} · {{ ['coordinator'=>'Koordinator PKL','school'=>'Sekolah (arsip)','industry'=>'Industri (arsip)'][$event->party]??$event->party }}:</strong> {{ $event->decision==='approved'?'Disetujui':'Ditolak' }} · {{ $event->approver?->name??'Akun nonaktif' }} · {{ $event->created_at->format('d M Y H:i') }}<div class="muted">{{ $event->note }}</div></div>
@endforeach @endif
<p class="small muted" style="margin-top:17px">Persetujuan dalam aplikasi adalah rekaman administratif. Tanda tangan digital tersertifikasi atau tanda tangan basah mengikuti kebijakan sekolah.</p>
@if(auth()->user()->isRole('koordinator'))<details><summary>Ajukan revisi / perbarui proposal menjadi versi baru</summary><p>Perbaiki data sumber dahulu. Versi lama tidak ditimpa; versi baru memerlukan persetujuan ulang dan mengulang administrasi distribusi. Jika sudah ada dokumen fisik, koordinasikan penarikan/penggantiannya di luar aplikasi.</p><form method="POST" action="{{ route('certificates.resubmit',$certificate) }}">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $certificate->version }}"><label>Alasan revisi<textarea name="note" required maxlength="2500"></textarea></label><button class="btn btn-secondary">↻ Ajukan Versi Baru</button></form></details>@endif
</div>
<div class="card"><div class="card-head"><h2>Riwayat Serah Terima</h2></div>
@forelse($certificate->movements->sortByDesc('moved_at') as $move)<div style="padding:11px 0;border-bottom:1px solid #edf1f7"><strong>{{ \App\Models\Certificate::statuses()[$move->to_status]??$move->to_status }}</strong><div class="small muted">{{ $move->moved_at->format('d M Y H:i') }} · {{ $move->actor->name }} · Penerima: {{ $move->recipient }} · {{ $move->version ? 'Versi '.$move->version : 'Arsip lama: versi tidak tercatat' }}</div>@if($move->note)<div class="small">{{ $move->note }}</div>@endif</div>
@empty<div class="empty">Belum ada perpindahan dokumen.</div>@endforelse
</div></div>
@if($document?->issued_at && auth()->user()->isRole('koordinator')&&in_array($certificate->status,['awaiting_print','printed','sent_to_company','returned_to_school','ready_pickup','handed_to_student']))
@php $next=['awaiting_print'=>'Tandai Dicetak','printed'=>'Kirim ke Perusahaan','sent_to_company'=>'Kembali ke Sekolah','returned_to_school'=>'Siap Diambil Siswa','ready_pickup'=>'Serahkan ke Siswa','handed_to_student'=>'Tandai Selesai']; @endphp
<div class="card"><div class="card-head"><div><h2>Perbarui Status Distribusi</h2><p>Setiap perpindahan tersimpan bersama koordinator, penerima, waktu, dan keterangan.</p></div></div>
<form method="POST" action="{{ route('certificates.movement',$certificate) }}">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $certificate->version }}"><input type="hidden" name="expected_status" value="{{ $certificate->status }}"><div class="form-grid">
<div class="form-field"><label>Nama Penerima / Tujuan</label><input name="recipient" required placeholder="Nama pihak yang menerima dokumen"></div>
<div class="form-field"><label>Keterangan Serah Terima</label><input name="note" placeholder="Nomor bukti, kondisi, atau informasi lain"></div>
@if($certificate->status==='returned_to_school')<div class="form-field"><label>Jadwal Pengambilan Siswa</label><input name="pickup_at" type="datetime-local" required></div><div class="form-field"><label>Lokasi Pengambilan</label><input name="pickup_location" required placeholder="Ruang TU / Humas"></div><div class="form-field wide"><label>Persyaratan Dibawa</label><input name="pickup_requirements" placeholder="Identitas siswa, tanda terima, ..."></div>@endif
</div><div class="form-footer"><button class="btn btn-primary" data-confirm="Lanjutkan ke status berikutnya?">{{ $next[$certificate->status] }} →</button></div></form></div>@endif
<div class="card"><h2>Arsip per versi</h2><p>Versi lama untuk penelusuran, bukan pengganti versi terbaru. Proposal yang belum disahkan tidak dapat diunduh sebagai sertifikat.</p>
@forelse($certificate->documents->sortByDesc('version') as $archive)<p>Versi {{ $archive->version }} · {{ $archive->issued_at?'Pernah disahkan':'Proposal belum disahkan' }} · {{ $archive->source }} · {{ $archive->note }}
@if($archive->issued_at)<a href="{{ route('certificates.pdf',['certificate'=>$certificate,'version'=>$archive->version]) }}">Unduh arsip v{{ $archive->version }}</a>
@if($archive->front_hash || $archive->wordExport) · <a href="{{ route('certificates.docx',[$certificate,'front','version'=>$archive->version]) }}">DOCX depan v{{ $archive->version }}</a> · <a href="{{ route('certificates.docx',[$certificate,'back','version'=>$archive->version]) }}">DOCX belakang v{{ $archive->version }}</a>@endif
@endif</p>@empty<p>Belum ada arsip.</p>@endforelse</div>
@endsection
