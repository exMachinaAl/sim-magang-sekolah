@extends('industry.layout')
@section('content')
<div class="card"><h1>Portal Kiosk {{ $device->mentor->name }}</h1><p>{{ $device->mentor->company->name }}</p><p>Perangkat terdaftar sampai {{ $device->expires_at->format('d/m/Y') }}. Portal dapat memindai banyak QR jurnal secara berurutan tanpa keluar ke Google Camera.</p><p>Jurnal baru selalu menunggu konfirmasi OK atau Batal. QR untuk isi dan versi yang sudah tervalidasi hanya diberi informasi lalu scanner melanjutkan tanpa popup.</p></div>
<div class="card kiosk-scanner" data-kiosk-scanner data-preview-url="{{ route('industry.portal.preview') }}" data-validate-url="{{ route('industry.portal.validate') }}" data-scan-prefix="{{ rtrim(route('industry.home'),'/') }}/scan/">
 <div class="card-head"><div><h2>Scanner QR Jurnal</h2><p>Arahkan kamera belakang ke QR yang ditampilkan pada HP siswa.</p></div><span class="pill active">HTTPS</span></div>
 <div class="kiosk-camera-frame"><video data-kiosk-video playsinline muted></video><div class="kiosk-camera-guide" aria-hidden="true"></div></div>
 <div class="form-footer"><button type="button" class="btn btn-primary" data-kiosk-start>Aktifkan Kamera</button><button type="button" class="btn btn-ghost" data-kiosk-stop hidden>Matikan Kamera</button></div>
 <p class="note-box" data-kiosk-status role="status">Kamera belum aktif. Jika browser tidak mendukung scanner portal, pindai QR memakai Google Camera; link akan diarahkan kembali ke portal ini.</p>
 <div class="kiosk-confirm" data-kiosk-confirm hidden>
  <h3>Periksa sebelum validasi</h3>
  <div class="detail-grid"><div><small>Siswa</small><strong data-kiosk-student>—</strong></div><div><small>NIS</small><strong data-kiosk-nis>—</strong></div><div><small>Tanggal kegiatan</small><strong data-kiosk-date>—</strong></div><div><small>Versi</small><strong data-kiosk-revision>—</strong></div><div><small>Nilai</small><strong data-kiosk-score>—</strong></div><div><small>Jam / area</small><strong data-kiosk-place>—</strong></div></div>
  <h4>Kegiatan</h4><p class="kiosk-activity" data-kiosk-activity>—</p><p class="small muted" data-kiosk-notes></p>
  <div class="form-footer"><button type="button" class="btn btn-primary" data-kiosk-confirm-ok>OK, Validasi</button><button type="button" class="btn btn-ghost" data-kiosk-confirm-cancel>Batal</button></div>
 </div>
 <div data-kiosk-session><h3>Hasil scan sesi ini</h3><p class="small muted" data-kiosk-session-empty>Belum ada QR diproses pada sesi ini.</p></div>
</div>
<div class="card"><h2>10 validasi terakhir perangkat ini</h2>
@forelse($recent as $validation)
<p><strong>{{ $validation->snapshot['student_name'] }}</strong> · NIS {{ $validation->snapshot['nis'] }}<br>
Kegiatan {{ $validation->snapshot['activity_date'] }} · nilai {{ $validation->snapshot['industry_score'] }} · versi {{ $validation->revision }}<br>
Scan: {{ $validation->scanned_at->format('d/m/Y H:i:s') }} WIB</p>
@empty<p>Belum ada jurnal yang dipindai.</p>@endforelse
</div>
@endsection
@push('scripts')<script src="{{ asset('js/industry-kiosk.js') }}" defer></script>@endpush
