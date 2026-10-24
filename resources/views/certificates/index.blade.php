@extends('layouts.app')
@section('title','Manajemen Sertifikat')
@section('breadcrumb','Administrasi / Sertifikat')
@section('subtitle','Penerbitan, persetujuan koordinator PKL, pencetakan, dan serah terima dokumen.')
@section('content')
@if($demoCertificate)
<div class="card" data-certificate-demo><h2>Contoh siap diuji — bukan dokumen resmi</h2>
<p><strong>Siswa Uji Sertifikat (DEMO)</strong> · NIS <strong>DEMO-SERT-001</strong>. Nilai, identitas, dan template PDF sudah terisi; tidak perlu LibreOffice.</p>
<div class="form-footer"><a class="btn btn-primary" href="{{ route('certificates.show',$demoCertificate) }}">1. Buka contoh &amp; unduh PDF</a><a class="btn btn-secondary" href="{{ route('certificates.prepare',[$demoCertificate->placement_id,'print_template_id'=>$demoCertificate->print_template_id]) }}">2. Coba revisi contoh</a></div>
<p>Untuk Word, buka detail contoh: tersedia DOCX depan dan belakang, atau tombol <strong>Siapkan DOCX dari arsip</strong>. Tidak memerlukan LibreOffice.</p>
<p class="small muted">Periksa dua halaman: identitas/tanda tangan di depan; aspek, rata-rata 84,00, laporan 90,00, hasil hitung 85,20, nilai resmi 85,00 di belakang. Angka ini adalah kondisi awal demo. Revisi memerlukan alasan dan persetujuan ulang; arsip lama tetap tersimpan.</p></div>
@endif
@if(auth()->user()->isRole('koordinator'))<p><a class="btn btn-secondary" href="{{ route('certificate-print-templates.index') }}">Template Cetak & Penandatangan</a></p>@endif
<div class="card"><h2>Cari siswa / sertifikat</h2>
<form method="GET" class="filterbar"><label>Nama atau NIS<input name="q" value="{{ request('q') }}" maxlength="100" placeholder="Ketik nama / NIS siswa"></label>
<label>Tag tahun<select name="period_id"><option value="">Semua tahun</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(request('period_id')==$period->id)>{{ $period->name }}</option>@endforeach</select></label>
<label>Jurusan roster<select name="major"><option value="">Semua jurusan</option>@foreach(\App\Models\InternshipRoster::officialMajors() as $major)<option @selected(request('major')===$major)>{{ $major }}</option>@endforeach</select></label>
<label>Status dokumen<select name="status"><option value="">Semua status</option>@foreach(\App\Models\Certificate::statuses() as $k=>$label)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $label }}</option>@endforeach</select></label><button class="btn btn-secondary">Cari</button></form>
<p class="small muted">Pencarian menggunakan data siswa dan roster saat ini. Isi PDF terbit memakai arsip versi, bukan data hidup.</p></div>
@if(auth()->user()->isRole('koordinator'))<div class="card"><h2>Ajukan Sertifikat</h2><p>Cari nama/NIS di atas. Jadwal, identitas, nilai disahkan, dan kelayakan dua halaman diperiksa sebelum pengajuan. Kelas/jurusan mengikuti roster tahun tersebut.</p>
@forelse($placements as $p)<div class="filterbar"><div><strong>{{ $p->student->user->name }} · {{ $p->student->nis }}</strong><br>{{ $p->roster?->class_name }} · {{ $p->roster?->major }} · {{ $p->period->name }}<br>{{ $p->company->name }} · Nilai {{ $p->assessment?->status==='approved'?'disahkan':'belum disahkan' }}</div>
<a class="btn btn-primary" href="{{ route('certificates.prepare',$p) }}">Lengkapi &amp; Ajukan Sertifikat</a></div>
@empty<p>{{ request('q') ? 'Tidak ditemukan kandidat tanpa sertifikat pada pencarian ini.' : 'Masukkan nama atau NIS untuk menampilkan kandidat; tidak memuat seluruh siswa.' }}</p>@endforelse
@if($placements instanceof \Illuminate\Contracts\Pagination\Paginator)@include('partials.pager',['items'=>$placements])@endif</div>@endif
<div class="card"><div class="card-head"><div><h2>Daftar Sertifikat</h2><p>Klik nomor sertifikat untuk melihat persetujuan dan riwayat distribusi.</p></div><span class="small muted">{{ $items->total() }} dokumen</span></div>
<div class="table-wrap"><table><thead><tr><th>Nomor Sertifikat</th><th>Peserta</th><th>Perusahaan</th><th>Status</th><th>Persetujuan</th><th>Jadwal Pengambilan</th><th></th></tr></thead><tbody>
@forelse($items as $c)<tr><td><strong>{{ $c->number }}</strong><div class="small muted">Versi {{ $c->version }}</div></td>
<td>{{ $c->placement->student->user->name }}</td><td>{{ $c->placement->company->name }}</td><td><span class="pill {{ $c->status }}">{{ $c->statusLabel() }}</span></td>
<td>@foreach($c->approvals as $a)<div class="small">Koordinator PKL: <strong>{{ ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'][$a->status]??$a->status }}</strong></div>@endforeach</td>
<td>{{ $c->pickup_at?->format('d M Y, H:i')??'Belum ditetapkan' }}</td><td><a class="btn btn-secondary btn-sm" href="{{ route('certificates.show',$c) }}">Detail →</a></td></tr>
@empty<tr><td colspan="7"><div class="empty">Belum ada sertifikat yang diterbitkan.</div></td></tr>@endforelse</tbody></table></div>@include('partials.pager')</div>
@endsection
