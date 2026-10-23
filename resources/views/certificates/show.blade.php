@extends('layouts.app')
@section('title','Detail Sertifikat')
@section('breadcrumb','Sertifikat / Detail')
@section('subtitle','Detail persetujuan, penerbitan, dan penelusuran serah terima dokumen.')
@section('page_actions')<a href="{{ route('certificates.index') }}" class="btn btn-ghost">← Kembali</a>@endsection
@section('content')
<div class="card"><div class="card-head"><div><h2>{{ $certificate->number }}</h2><p>Dokumen versi {{ $certificate->version }}</p></div><span class="pill {{ $certificate->status }}">{{ $certificate->statusLabel() }}</span></div>
<div class="detail-grid"><div><small>Nama Peserta</small><strong>{{ $certificate->placement->student->user->name }}</strong></div>
<div><small>NIS / Kelas</small><strong>{{ $certificate->placement->student->nis }} / {{ $certificate->placement->student->class_name }}</strong></div>
<div><small>Perusahaan</small><strong>{{ $certificate->placement->company->name }}</strong></div>
<div><small>Periode PKL</small><strong>{{ $certificate->placement->period->name }}</strong></div>
<div><small>Tanggal PKL</small><strong>{{ $certificate->placement->roster?->internship_starts_at?->format('d M Y') ?? 'Belum dijadwalkan' }} — {{ $certificate->placement->roster?->internship_ends_at?->format('d M Y') ?? '—' }}</strong></div>
<div><small>Jadwal Pengambilan</small><strong>{{ $certificate->pickup_at?->format('d M Y · H:i')??'Belum ditetapkan' }}</strong></div>
<div><small>Lokasi Pengambilan</small><strong>{{ $certificate->pickup_location??'Belum ditetapkan' }}</strong></div>
<div><small>Dokumen yang perlu dibawa</small><strong>{{ $certificate->pickup_requirements??'—' }}</strong></div>
</div>
@if(!in_array($certificate->status,['awaiting_approval','revision_required'])&&$certificate->approvals->where('party','coordinator')->where('status','approved')->count()===1)<div class="note-box" style="margin-top:18px"><strong>Format cetak dua halaman:</strong> halaman pertama adalah sertifikat, halaman kedua adalah daftar nilai untuk dicetak pada sisi belakang.</div><div class="form-footer"><a href="{{ route('certificates.pdf',$certificate) }}" class="btn btn-primary">↓ Unduh Sertifikat + Nilai PDF</a></div>@endif
</div>
<div class="two-cols"><div class="card"><div class="card-head"><h2>Persetujuan Sertifikat</h2></div>
@foreach($certificate->approvals as $a)<div style="padding:15px 0;border-bottom:1px solid #edf1f7"><strong>Koordinator PKL</strong><span class="pill {{ $a->status }}" style="float:right">{{ ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'][$a->status]??$a->status }}</span>
<div class="small muted" style="margin-top:7px">{{ $a->note??'Belum ada catatan.' }} @if($a->decided_at) · {{ $a->decided_at->format('d M Y H:i') }}@endif</div>
@if($a->status==='pending'&&$certificate->status==='awaiting_approval'&&auth()->user()->isRole('koordinator')&&$a->party==='coordinator')
<form method="POST" action="{{ route('certificates.approve',$certificate) }}" style="margin-top:12px">@csrf @method('PUT')<label>Catatan (wajib jika menolak)</label><textarea name="note" style="min-height:65px" placeholder="Alasan atau keterangan persetujuan"></textarea>
<div class="form-footer"><button class="btn btn-primary btn-sm" name="decision" value="approved">✓ Setujui</button><button class="btn btn-danger btn-sm" name="decision" value="rejected">× Tolak</button></div></form>@endif</div>
@endforeach
@if($certificate->approvalEvents->isNotEmpty())<div class="divider"></div><h3>Riwayat Keputusan Persetujuan</h3>
@foreach($certificate->approvalEvents->sortByDesc('created_at') as $event)
<div class="small" style="padding:8px 0"><strong>Versi {{ $event->version }} · {{ ['coordinator'=>'Koordinator PKL','school'=>'Sekolah (arsip)','industry'=>'Industri (arsip)'][$event->party]??$event->party }}:</strong> {{ $event->decision==='approved'?'Disetujui':'Ditolak' }} · {{ $event->approver?->name??'Akun nonaktif' }} · {{ $event->created_at->format('d M Y H:i') }}<div class="muted">{{ $event->note }}</div></div>
@endforeach @endif
<p class="small muted" style="margin-top:17px">Persetujuan dalam aplikasi adalah rekaman administratif. Tanda tangan digital tersertifikasi atau tanda tangan basah mengikuti kebijakan sekolah.</p>
@if(auth()->user()->isRole('koordinator')&&$certificate->status==='revision_required')<form method="POST" action="{{ route('certificates.resubmit',$certificate) }}">@csrf @method('PUT')<button class="btn btn-secondary" data-confirm="Persetujuan lama direset dan dokumen versi baru diajukan?">↻ Ajukan Ulang (Versi Baru)</button></form>@endif
</div>
<div class="card"><div class="card-head"><h2>Riwayat Serah Terima</h2></div>
@forelse($certificate->movements->sortByDesc('moved_at') as $move)<div style="padding:11px 0;border-bottom:1px solid #edf1f7"><strong>{{ \App\Models\Certificate::statuses()[$move->to_status]??$move->to_status }}</strong><div class="small muted">{{ $move->moved_at->format('d M Y H:i') }} · {{ $move->actor->name }} · Penerima: {{ $move->recipient }}</div>@if($move->note)<div class="small">{{ $move->note }}</div>@endif</div>
@empty<div class="empty">Belum ada perpindahan dokumen.</div>@endforelse
</div></div>
@if(auth()->user()->isRole('koordinator')&&in_array($certificate->status,['awaiting_print','printed','sent_to_company','returned_to_school','ready_pickup','handed_to_student']))
@php $next=['awaiting_print'=>'Tandai Dicetak','printed'=>'Kirim ke Perusahaan','sent_to_company'=>'Kembali ke Sekolah','returned_to_school'=>'Siap Diambil Siswa','ready_pickup'=>'Serahkan ke Siswa','handed_to_student'=>'Tandai Selesai']; @endphp
<div class="card"><div class="card-head"><div><h2>Perbarui Status Distribusi</h2><p>Setiap perpindahan tersimpan bersama koordinator, penerima, waktu, dan keterangan.</p></div></div>
<form method="POST" action="{{ route('certificates.movement',$certificate) }}">@csrf @method('PUT')<input type="hidden" name="expected_status" value="{{ $certificate->status }}"><div class="form-grid">
<div class="form-field"><label>Nama Penerima / Tujuan</label><input name="recipient" required placeholder="Nama pihak yang menerima dokumen"></div>
<div class="form-field"><label>Keterangan Serah Terima</label><input name="note" placeholder="Nomor bukti, kondisi, atau informasi lain"></div>
@if($certificate->status==='returned_to_school')<div class="form-field"><label>Jadwal Pengambilan Siswa</label><input name="pickup_at" type="datetime-local" required></div><div class="form-field"><label>Lokasi Pengambilan</label><input name="pickup_location" required placeholder="Ruang TU / Humas"></div><div class="form-field wide"><label>Persyaratan Dibawa</label><input name="pickup_requirements" placeholder="Identitas siswa, tanda terima, ..."></div>@endif
</div><div class="form-footer"><button class="btn btn-primary" data-confirm="Lanjutkan ke status berikutnya?">{{ $next[$certificate->status] }} →</button></div></form></div>@endif
@endsection
