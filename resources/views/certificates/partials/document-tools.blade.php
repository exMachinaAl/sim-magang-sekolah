<details class="card"><summary>Format, penandatangan, dan keluaran dokumen</summary>
@if(isset($frozen['print']))
<h3>Format versi {{ $certificate->version }}</h3>
<p>{{ $frozen['print']['template_name'] }} · {{ config('certificate_print.front_variants.'.$frozen['print']['front_variant']) }} / {{ config('certificate_print.back_variants.'.$frozen['print']['back_variant']) }}</p>
<p>Kepala sekolah: {{ $frozen['print']['values']['nama_kepala_sekolah'] ?? '—' }} · NIP {{ $frozen['print']['values']['nip_kepala_sekolah'] ?? '—' }}<br>Penandatangan nilai: {{ $frozen['print']['values']['nama_pembimbing'] ?? '—' }}</p>
@if(isset($frozen['print']['pdf_background']))<p>Versi ini memiliki PDF digital berdekorasi dan PDF cetak tanpa dekorasi dari layout aplikasi yang sama.</p>@endif
@if(isset($frozen['print']['docx_background']))<p>Versi ini memiliki PDF digital berdekorasi dan PDF cetak tanpa dekorasi dari DOCX teks yang sama. Font dan posisi mengikuti DOCX saat dirender LibreOffice server.</p>@endif
@else<p>Versi ini belum memiliki snapshot format cetak lengkap.</p>@endif

@if($document?->issued_at)
<div class="divider"></div><h3>Word / DOCX versi {{ $document->version }}</h3>
@if($document->front_hash || $document->wordExport)
<div class="form-footer"><a class="btn btn-primary" href="{{ route('certificates.docx',[$certificate,'front']) }}">Unduh DOCX depan</a><a class="btn btn-secondary" href="{{ route('certificates.docx',[$certificate,'back']) }}">Unduh DOCX belakang</a></div>
@if($document->wordExport)<p>Salinan Word dibuat dari arsip versi ini. Depan memakai template asli; belakang {{ $document->wordExport->layout==='paket_asli_80_20'?'memakai template asli 80/20.':'disusun sesuai seluruh aspek bebas, termasuk hasil teks.' }}</p>@endif
@elseif(isset($frozen['print']) && auth()->user()->isRole('koordinator'))
<p>Siapkan DOCX depan dan belakang dari arsip yang sudah disahkan tanpa membaca ulang data hidup.</p>
<form method="POST" action="{{ route('certificates.prepare-word',$certificate) }}">@csrf<input type="hidden" name="version" value="{{ $document->version }}"><button class="btn btn-primary">Siapkan DOCX dari arsip</button></form>
@else<p>DOCX belum tersedia. Arsip lama tanpa identitas cetak lengkap memerlukan revisi versi baru.</p>@endif
<p class="small muted">Periksa kedua DOCX di Word pada ukuran A4 sebelum cetak depan–belakang. Edit manual pada file unduhan tidak mengubah PDF atau arsip sistem.</p>
@endif
</details>
