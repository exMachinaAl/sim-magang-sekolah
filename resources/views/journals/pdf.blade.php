<!doctype html><html lang="id"><head><meta charset="utf-8"><style>
@page{margin:24px 28px}body{font-family:DejaVu Sans,sans-serif;color:#1c304a;font-size:10px}
h1{font-size:20px;margin:3px 0 0}p{color:#66788c;margin:4px 0 16px}.head{border-bottom:2px solid #1f4f90;padding-bottom:10px;margin-bottom:15px}
table{border-collapse:collapse;width:100%}th{background:#204f86;color:white}th,td{border:1px solid #c9d5e4;padding:8px;vertical-align:top;text-align:left}tr{page-break-inside:avoid}.small{font-size:9px;color:#65768c}
</style></head><body><div class="head"><h1>Rekap Jurnal Magang — SIMMAG</h1><p>SMKN 1 Cerme Gresik · Diekspor {{ now()->format('d/m/Y H:i') }} · {{ $items->count() }} baris (maksimal 300 per ekspor)</p></div>
<table><thead><tr><th style="width:8%">Tanggal</th><th style="width:17%">Siswa / Perusahaan</th><th>Kegiatan</th><th>Hasil & Kendala</th><th style="width:9%">Foto</th><th style="width:12%">Status</th></tr></thead><tbody>
@forelse($items as $journal)<tr><td>{{ $journal->activity_date->format('d/m/Y') }}</td><td><strong>{{ $journal->placement->student->user->name }}</strong><div class="small">{{ $journal->placement->company->name }}</div></td>
<td>{{ $journal->activity }}<div class="small">{{ $journal->work_area }} · {{ $journal->starts_time }} – {{ $journal->ends_time }}</div><div>{{ $journal->equipment_notes }}</div></td><td><strong>Hasil:</strong> {{ $journal->outcome }}<div><strong>Kendala:</strong> {{ $journal->obstacles??'—' }}</div><div>Nilai industri dicatat siswa: {{ $journal->industry_score ?? 'Belum diisi' }} ({{ $journal->scoreCategory() ?? '—' }})</div></td><td>{{ $journal->attachments->count() }} foto</td>
<td>{{ $journal->statusLabel() }}<div class="small">{{ $journal->review_note }}</div>
@php $validation=$journal->industryValidations->firstWhere('revision',$journal->revision); @endphp
<div>{{ $validation?'Tervalidasi industri':'Belum divalidasi industri' }}</div>
@if($validation)<div>{{ $validation->snapshot['mentor_name'] }} · {{ $validation->scanned_at->format('d/m/Y H:i') }}</div>@endif</td></tr>
@empty<tr><td colspan="6" style="text-align:center">Belum ada jurnal sesuai filter.</td></tr>@endforelse
</tbody></table><p>Dokumen hanya memuat jurnal yang diizinkan untuk akun pengunduh. Bukti foto dapat dilihat melalui aplikasi setelah login.</p></body></html>
