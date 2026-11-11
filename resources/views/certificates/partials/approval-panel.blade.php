@php
    $currentApproval=$certificate->approvals->firstWhere('party','coordinator');
    $approvalLabels=['pending'=>'Menunggu keputusan','approved'=>'Disetujui','rejected'=>'Ditolak'];
@endphp
<div class="card" data-certificate-approval-panel>
<div class="card-head"><div><h2>Validasi koordinator</h2><p>Satu keputusan aktif untuk versi {{ $certificate->version }}. Tanda tangan pada desain sertifikat bukan tahap persetujuan tambahan.</p></div>
@if($currentApproval)<span class="pill {{ $currentApproval->status }}">{{ $approvalLabels[$currentApproval->status] ?? $currentApproval->status }}</span>@endif</div>

@if(!$currentApproval)
<p class="alert error">Rekaman validasi aktif tidak ditemukan. Dokumen tidak boleh dilanjutkan sebelum data diperiksa.</p>
@else
<div class="detail-grid">
<div><small>Versi yang diperiksa</small><strong>{{ $certificate->version }}</strong></div>
<div><small>Proposal tersimpan</small><strong>{{ $document ? 'Tersedia' : 'Belum tersedia' }}</strong></div>
<div><small>Keputusan oleh</small><strong>{{ $currentApproval->approver?->name ?? 'Belum diputuskan' }}</strong></div>
<div><small>Waktu keputusan</small><strong>{{ $currentApproval->decided_at?->format('d M Y H:i') ?? 'Belum diputuskan' }}</strong></div>
</div>
@if($currentApproval->note)<p><strong>Catatan keputusan:</strong><br>{{ $currentApproval->note }}</p>@endif

@if($currentApproval->status==='pending' && $certificate->status==='awaiting_approval' && auth()->user()->isRole('koordinator'))
<p class="note-box">Sebelum menyetujui, periksa identitas peserta, nilai yang dibekukan, desain depan/belakang, dan penandatangan pada versi ini.</p>
<form method="POST" action="{{ route('certificates.approve',$certificate) }}">@csrf @method('PUT')
<input type="hidden" name="version" value="{{ $certificate->version }}">
<label>Catatan keputusan (wajib jika menolak)<textarea name="note" placeholder="Tuliskan alasan penolakan atau catatan pemeriksaan"></textarea></label>
<div class="form-footer"><button class="btn btn-primary" name="decision" value="approved" @disabled(!$document)>Setujui versi {{ $certificate->version }}</button><button class="btn btn-danger" name="decision" value="rejected">Tolak &amp; minta revisi</button></div>
</form>
@if(!$document)<p class="alert error">Proposal lama belum memiliki arsip, sehingga belum dapat disetujui. Ajukan versi baru setelah sumber diperiksa.</p>@endif
@endif
@endif

@if($certificate->approvalEvents->isNotEmpty())
<details><summary>Riwayat keputusan terdahulu ({{ $certificate->approvalEvents->count() }})</summary>
@foreach($certificate->approvalEvents->sortByDesc('created_at') as $event)
<div class="small" style="padding:10px 0;border-bottom:1px solid #edf1f7"><strong>Versi {{ $event->version }}: {{ $event->decision==='approved'?'Disetujui':'Ditolak' }}</strong><br>{{ $event->approver?->name??'Akun nonaktif' }} · {{ $event->created_at->format('d M Y H:i') }}@if($event->note)<div class="muted">{{ $event->note }}</div>@endif</div>
@endforeach
</details>
@endif

@if(auth()->user()->isRole('koordinator'))
<details><summary>Ajukan koreksi sebagai versi baru</summary><p>Perbaiki sumber atau data cetak dahulu. Versi lama tidak ditimpa dan versi baru akan meminta satu validasi koordinator lagi.</p>
<form method="POST" action="{{ route('certificates.resubmit',$certificate) }}">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $certificate->version }}"><label>Alasan revisi<textarea name="note" required minlength="10" maxlength="2500"></textarea></label><button class="btn btn-secondary">Ajukan Versi Baru</button></form></details>
@endif
<p class="small muted">Validasi aplikasi adalah rekaman administratif. Tanda tangan basah atau digital tersertifikasi tetap mengikuti kebijakan sekolah.</p>
</div>
