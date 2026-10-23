<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Sertifikat {{ $certificate->number }}</title>
<style>
@page { margin: 32px; }
* { box-sizing: border-box; }
body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #162c4d; }
.page { position: relative; height: 580px; }
.front { border: 9px double #203e6a; padding: 34px 40px; text-align: center; }
.back { page-break-before: always; border: 3px solid #203e6a; padding: 28px 34px; }
.school { font-size: 17px; letter-spacing: 3px; font-weight: bold; margin-bottom: 22px; }
.big { font-size: 43px; letter-spacing: 3px; font-weight: bold; margin: 5px 0; }
.line { height: 2px; background: #c89b45; width: 280px; margin: 18px auto; }
.num { font-size: 13px; color: #5f6881; }
.name { font-size: 30px; font-weight: bold; color: #15396f; margin-top: 16px; }
.body-copy { font-size: 15px; line-height: 2; }
.footer { display: table; width: 100%; margin-top: 44px; font-size: 12px; }
.half { display: table-cell; width: 50%; padding: 5px; }
.sign { margin-top: 50px; font-weight: bold; }
.note { font-size: 10px; color: #667389; margin-top: 10px; }
.back h1 { margin: 0; text-align: center; font-size: 22px; letter-spacing: 1px; }
.back .subtitle { text-align: center; margin: 5px 0 18px; font-size: 12px; color: #5f6881; }
.identity { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 12px; }
.identity td { padding: 3px 6px; vertical-align: top; }
.identity .label { width: 130px; color: #5f6881; }
.scores { width: 100%; border-collapse: collapse; font-size: 11px; }
.scores th { background: #203e6a; color: white; padding: 8px 6px; border: 1px solid #203e6a; }
.scores td { padding: 7px 6px; border: 1px solid #aab4c3; }
.scores .center { text-align: center; }
.scores .right { text-align: right; }
.scores .total td { font-weight: bold; background: #edf2f8; font-size: 12px; }
.assessment-note { margin-top: 14px; border: 1px solid #aab4c3; padding: 10px; min-height: 48px; font-size: 11px; }
.validation { margin-top: 15px; text-align: right; font-size: 11px; }
</style>
</head>
<body>
@php
 $assessment = $certificate->placement->assessment;
 $scores = $assessment->scores->sortBy(fn($score) => $score->criterion->display_order);
@endphp

<section class="page front">
 <div class="school">PEMERINTAH PROVINSI JAWA TIMUR<br>SMK NEGERI 1 CERME GRESIK</div>
 <div class="big">SERTIFIKAT</div>
 <div class="num">Nomor: {{ $certificate->number }} · Versi {{ $certificate->version }}</div>
 <div class="line"></div>
 <div class="body-copy">Diberikan kepada</div>
 <div class="name">{{ $certificate->placement->student->user->name }}</div>
 <div class="body-copy">NIS: {{ $certificate->placement->student->nis }} · {{ $certificate->placement->student->class_name }}<br>
 atas pelaksanaan <strong>Praktik Kerja Lapangan (PKL)</strong> di<br>
 <strong>{{ $certificate->placement->company->name }}</strong><br>
 {{ $certificate->placement->roster->internship_starts_at->translatedFormat('d F Y') }} – {{ $certificate->placement->roster->internship_ends_at->translatedFormat('d F Y') }}</div>
 <div class="footer"><div class="half">Tempat Tanda Tangan/Stempel Mitra<div class="sign">___________________</div></div><div class="half">Koordinator PKL<div class="sign">___________________</div></div></div>
 <div class="note">Halaman depan sertifikat. Tata letak tanda tangan dan stempel akan disesuaikan dengan template resmi.</div>
</section>

<section class="page back">
 <h1>DAFTAR NILAI PRAKTIK KERJA LAPANGAN</h1>
 <div class="subtitle">Lampiran belakang Sertifikat Nomor {{ $certificate->number }}</div>
 <table class="identity">
  <tr><td class="label">Nama peserta</td><td>: <strong>{{ $certificate->placement->student->user->name }}</strong></td><td class="label">NIS</td><td>: {{ $certificate->placement->student->nis }}</td></tr>
  <tr><td class="label">Kelas / Jurusan</td><td>: {{ $certificate->placement->student->class_name }} / {{ $certificate->placement->student->major }}</td><td class="label">Periode</td><td>: {{ $certificate->placement->period->name }}</td></tr>
  <tr><td class="label">Tempat PKL</td><td colspan="3">: {{ $certificate->placement->company->name }}</td></tr>
 </table>
 <table class="scores">
  <thead><tr><th style="width:38px">No.</th><th>Aspek Penilaian</th><th style="width:75px">Bobot</th><th style="width:75px">Nilai</th><th style="width:110px">Nilai Berbobot</th></tr></thead>
  <tbody>
  @foreach($scores as $score)
   <tr><td class="center">{{ $loop->iteration }}</td><td>{{ $score->criterion->name }}</td><td class="center">{{ $score->criterion->weight }}%</td><td class="center">{{ $score->score }}</td><td class="right">{{ number_format(($score->score * $score->criterion->weight) / 100,2,',','.') }}</td></tr>
  @endforeach
   <tr class="total"><td colspan="2" class="right">Jumlah / Nilai Akhir</td><td class="center">{{ $scores->sum(fn($score) => $score->criterion->weight) }}%</td><td></td><td class="right">{{ number_format((float)$assessment->final_score,2,',','.') }}</td></tr>
  </tbody>
 </table>
 <div class="assessment-note"><strong>Catatan Guru Pembimbing:</strong><br>{{ $assessment->notes ?: 'Tidak ada catatan tambahan.' }}</div>
 <div class="validation">Dinilai oleh: <strong>{{ $assessment->assessor?->name ?? 'Guru Pembimbing' }}</strong><br>Disahkan oleh: <strong>{{ $assessment->approver?->name ?? 'Koordinator PKL' }}</strong>@if($assessment->approved_at)<br>{{ $assessment->approved_at->translatedFormat('d F Y') }}@endif</div>
</section>
</body>
</html>
