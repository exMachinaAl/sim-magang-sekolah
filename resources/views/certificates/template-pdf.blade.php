<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Sertifikat {{ $certificate->number }}</title>
<style>
@page{margin:32px}body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#222}h1{text-align:center;font-size:24px}h2{text-align:center;font-size:14px}.front{text-align:center;padding:55px 20px}.back{page-break-before:always}table{width:100%;border-collapse:collapse}th,td{border:1px solid #555;padding:6px;vertical-align:top}th{background:#eee}.name{font-size:22px;margin:28px 0}.statement{font-size:13px;line-height:1.8;margin:20px 0}.signature{margin-top:60px}.description{font-size:9px;line-height:1.4;margin-top:4px;white-space:pre-line}.identity{margin:14px 0;line-height:1.7}.right{text-align:right}tr{page-break-inside:avoid}
</style></head><body>
@php $assessment=$certificate->placement->assessment; $snapshot=$assessment->template_snapshot; $placement=$certificate->placement; @endphp
<section class="front">
<h2>SMK NEGERI 1 CERME GRESIK</h2><h1>{{ $snapshot['certificate_heading'] }}</h1><p>Nomor: {{ $certificate->number }}</p>
<p>Diberikan kepada</p><div class="name">{{ $placement->student->user->name }}</div><p>NIS {{ $placement->student->nis }} · {{ $placement->student->class_name }}</p><p>{{ $snapshot['major'] }}</p>
<div class="statement">{{ $snapshot['certificate_statement'] }}</div>
<p>Tempat PKL: <strong>{{ $placement->company->name }}</strong></p><p>{{ $startsAt?->locale('id')->translatedFormat('d F Y') }} – {{ $endsAt?->locale('id')->translatedFormat('d F Y') }}</p>
<div class="signature">Pengesahan sekolah / industri<br><br><br><br>____________________________</div>
</section>
<section class="back"><h2>DAFTAR NILAI PRAKTIK KERJA LAPANGAN</h2><p style="text-align:center">Lampiran Sertifikat {{ $certificate->number }}</p>
<div class="identity"><strong>{{ $placement->student->user->name }}</strong> · NIS {{ $placement->student->nis }}<br>{{ $placement->student->class_name }} · {{ $snapshot['major'] }}<br>{{ $placement->company->name }}<br>Tanggal penilaian: {{ $assessment->assessed_on->locale('id')->translatedFormat('d F Y') }}</div>
<table><thead><tr><th style="width:25px">No</th><th>Aspek dan ketentuan penilaian</th><th style="width:40px">Bobot</th><th style="width:85px">Hasil industri</th></tr></thead><tbody>
@foreach($snapshot['fields'] as $field)<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $field['label'] }}</strong><div class="description">{{ $field['description'] }}</div></td><td>{{ $field['type']==='score' ? $field['weight'].'%' : '—' }}</td><td>{{ $field['type']==='score' ? number_format((float)$assessment->field_values[$field['key']],2,',','.') : $assessment->field_values[$field['key']] }}</td></tr>@endforeach
<tr><td colspan="3" class="right">Hasil hitung berbobot</td><td>{{ number_format($assessment->calculated_score,2,',','.') }}</td></tr><tr><td colspan="3" class="right"><strong>Nilai akhir resmi</strong></td><td><strong>{{ number_format($assessment->final_score,2,',','.') }}</strong></td></tr>
</tbody></table>
@if($assessment->final_score_reason)<p>Keterangan nilai akhir: {{ $assessment->final_score_reason }}</p>@endif
<p>Penilai industri: <strong>{{ $assessment->industry_assessor }}</strong><br>Dicatat oleh koordinator: {{ $assessment->assessor?->name }}<br>Disahkan koordinator: {{ $assessment->approver?->name }}</p>
</section></body></html>
