@extends('layouts.app')
@section('title','Jurnal & Dokumentasi')
@section('breadcrumb','Operasional / Jurnal')
@section('subtitle','Rekap progres jurnal per siswa. Buka detail untuk melihat kegiatan, foto, review, dan riwayat status.')
@section('content')
@if(auth()->user()->isRole('koordinator'))<p><a href="{{ route('journals.mentors') }}" class="btn btn-primary">Pembimbing Industri & Perangkat QR</a></p>@endif
<p class="note-box">Jurnal dipercaya sebagai catatan siswa. Review sekolah tidak wajib dan bukan validasi industri. Foto opsional; tanggal kegiatan dan waktu scan dicatat terpisah.</p>
<div class="card">
 <div class="card-head"><div><h2>Rekap Jurnal per Penempatan</h2><p>Setiap siswa hanya muncul satu kali untuk setiap periode PKL.</p></div><span class="small muted">{{ $items->total() }} penempatan</span></div>
 <form method="GET" class="filterbar">
  <input name="q" value="{{ request('q') }}" placeholder="Cari nama atau NIS siswa..." aria-label="Cari nama atau NIS siswa">
  <select name="period_id"><option value="">Semua periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(request('period_id')==$period->id)>{{ $period->name }}</option>@endforeach</select>
  <select name="status"><option value="">Semua status jurnal</option>@foreach(\App\Models\Journal::statuses() as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select>
  <input type="date" name="date_from" value="{{ request('date_from') }}" title="Dari tanggal" aria-label="Dari tanggal">
  <input type="date" name="date_to" value="{{ request('date_to') }}" title="Sampai tanggal" aria-label="Sampai tanggal">
  <button class="btn btn-secondary">Filter</button>
  <a href="{{ route('journals.index') }}" class="btn btn-ghost">Reset</a>
  <a href="{{ route('journals.pdf',request()->only(['q','status','date_from','date_to','period_id'])) }}" class="btn btn-ghost">↓ Unduh Rekap PDF</a>
 </form>
 <div class="table-wrap"><table>
  <thead><tr><th>Siswa</th><th>Penempatan</th><th>Ringkasan Jurnal</th><th>Review sekolah (opsional)</th><th>Aktivitas Terakhir</th><th></th></tr></thead>
  <tbody>
  @forelse($items as $placement)
   @php $progress=$placement->journals_count ? round(($placement->approved_journals_count/$placement->journals_count)*100) : 0; @endphp
   <tr>
    <td><strong>{{ $placement->student->user->name }}</strong><div class="small muted">{{ $placement->student->nis }} · {{ $placement->student->class_name }}</div></td>
    <td>{{ $placement->company->name }}<div class="small muted">{{ $placement->period->name }}</div></td>
    <td><strong>{{ $placement->journals_count }} jurnal</strong><div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:7px">
     @if($placement->submitted_journals_count)<span class="pill submitted">{{ $placement->submitted_journals_count }} tercatat</span>@endif
     @if($placement->revision_journals_count)<span class="pill revision">{{ $placement->revision_journals_count }} revisi</span>@endif
     @if($placement->rejected_journals_count)<span class="pill rejected">{{ $placement->rejected_journals_count }} ditolak</span>@endif
     @if($placement->draft_journals_count)<span class="pill draft">{{ $placement->draft_journals_count }} draf</span>@endif
    </div></td>
    <td><strong>{{ $placement->approved_journals_count }}/{{ $placement->journals_count }} ditinjau sekolah</strong><div>{{ $placement->industry_validated_count }} tervalidasi industri</div><span class="small muted">Tidak menentukan kelulusan PKL</span></td>
    <td>{{ $placement->journals_max_activity_date ? \Illuminate\Support\Carbon::parse($placement->journals_max_activity_date)->translatedFormat('d M Y') : 'Belum ada jurnal' }}</td>
    <td><a class="btn btn-primary btn-sm" href="{{ route('journals.show',$placement) }}">Lihat Detail →</a></td>
   </tr>
  @empty
   <tr><td colspan="6"><div class="empty"><span class="empty-mark">▤</span>Tidak ada penempatan yang sesuai dengan filter.</div></td></tr>
  @endforelse
  </tbody>
 </table></div>
 @include('partials.pager')
</div>
@endsection
