<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Sertifikat</title>
<style>@page{margin:30px 35px}body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#111}.front{text-align:center;padding-top:30px}h1{font-size:24px}h2{font-size:14px;text-align:center}.name{font-size:20px;margin:22px 0}table{width:100%;border-collapse:collapse}.identity{text-align:left;margin:18px 0}.identity td{padding:4px}.grades td,.grades th{border:1px solid #555;padding:5px;vertical-align:top}.grades th{background:#eee}.description{font-size:9px;white-space:pre-line}.back{page-break-before:always}.signatures{margin-top:30px}.signatures td{text-align:center;vertical-align:top}.sign-space{height:60px}.small{font-size:9px}tr{page-break-inside:avoid}</style></head><body>
@isset($pdfBox)<style>@page{margin:{{ $pdfBox['y'] }}mm {{ 210-$pdfBox['x']-$pdfBox['width'] }}mm {{ 297-$pdfBox['y']-$pdfBox['height'] }}mm {{ $pdfBox['x'] }}mm}.back{page-break-before:auto}body{margin:0;background:transparent}</style>@endisset
@php $v=$print['values']; $a=$certificate->placement->assessment; $fields=$a->template_snapshot['fields']; @endphp
@if(!isset($pdfSide) || $pdfSide==='front')
<section class="front"><h2>{{ $v['nama_sekolah_judul'] }}</h2><h1>{{ $a->template_snapshot['certificate_heading'] }}</h1><p>Nomor: {{ $v['nomor_sertifikat'] }} · Versi {{ $certificate->version }}</p><p>Diberikan kepada</p><div class="name"><strong>{{ $v['nama_siswa'] }}</strong></div>
<table class="identity">@foreach(['nis'=>'NIS','tempat_tanggal_lahir'=>'Tempat, tanggal lahir','bidang_keahlian'=>'Bidang keahlian','program_keahlian'=>'Program keahlian','konsentrasi_keahlian'=>'Konsentrasi keahlian'] as $key=>$label)<tr><td style="width:150px">{{ $label }}</td><td>: {{ $v[$key] }}</td></tr>@endforeach</table>
<p>{{ $a->template_snapshot['certificate_statement'] }}</p><p>di <strong>{{ $v['nama_industri'] }}</strong><br>Bidang pekerjaan: {{ $v['bidang_pekerjaan'] }}<br>{{ $v['tanggal_mulai_pkl'] }} – {{ $v['tanggal_selesai_pkl'] }}</p>
<p>{{ $v['kota_terbit'] }}, {{ $v['tanggal_pengesahan'] }}</p>
<table class="signatures"><tr>@if($print['front_variant']==='kepala_sekolah_dan_industri')<td>Pimpinan {{ $v['nama_industri'] }}<div class="sign-space"></div><strong>{{ $v['nama_pimpinan_industri'] }}</strong></td>@endif<td>Kepala {{ $v['nama_sekolah_ttd'] }}<div class="sign-space"></div><strong>{{ $v['nama_kepala_sekolah'] }}</strong><br>NIP {{ $v['nip_kepala_sekolah'] }}</td></tr></table></section>
@endif
@if(!isset($pdfSide) || $pdfSide==='back')
<section class="back"><h2>DAFTAR NILAI PRAKTIK KERJA LAPANGAN</h2><p>Nama: {{ $v['nama_siswa'] }}<br>NISN / NIPD: {{ $v['nisn_nipd'] }} · Kelas: {{ $v['kelas'] }}<br>Konsentrasi: {{ $v['konsentrasi_keahlian'] }}<br>Industri: {{ $v['nama_industri'] }}<br>Lampiran sertifikat: {{ $v['nomor_sertifikat'] }}</p>
<table class="grades"><thead><tr><th>No.</th><th>Aspek dan ketentuan</th><th>Nilai</th>@if(!$print['score_mapping'])<th>Bobot</th>@endif</tr></thead><tbody>
@if($print['score_mapping'])
@foreach($print['score_mapping'] as $name=>$key)@if($name!=='laporan_pkl')@php $field=collect($fields)->firstWhere('key',$key); @endphp<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $field['label'] }}</strong><div class="description">{{ $field['description'] }}</div></td><td>{{ $v['nilai_'.$name] }}</td></tr>@endif @endforeach
<tr><td colspan="2">Rata-rata empat aspek</td><td>{{ $v['nilai_rata_rata_aspek'] }}</td></tr><tr><td colspan="2">Nilai laporan PKL</td><td>{{ $v['nilai_laporan_pkl'] }}</td></tr><tr><td colspan="2">Hasil hitung: 80% rata-rata aspek + 20% laporan</td><td>{{ $v['nilai_hitung'] }}</td></tr>
@else
@foreach($fields as $field)<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $field['label'] }}</strong><div class="description">{{ $field['description'] }}</div></td><td>{{ $v['hasil_'.$loop->iteration] }}</td><td>{{ $v['bobot_'.$loop->iteration] }}</td></tr>@endforeach
<tr><td colspan="3">Hasil hitung berbobot</td><td>{{ $v['nilai_hitung'] }}</td></tr>
@endif
<tr><td colspan="{{ $print['score_mapping']?2:3 }}"><strong>Nilai akhir resmi</strong></td><td><strong>{{ $v['nilai_akhir_pkl'] }}</strong></td></tr></tbody></table>
@if($a->final_score_reason)<p class="small">Keterangan nilai akhir: {{ $a->final_score_reason }}</p>@endif
<p>{{ $v['kota_terbit'] }}, {{ $v['tanggal_penilaian'] }}<br>{{ config('certificate_print.back_variants.'.$print['back_variant']) }}</p><div class="sign-space"></div><p><strong>{{ $v['nama_pembimbing'] }}</strong>@if($print['back_variant']==='pembimbing_sekolah')<br>NIP {{ $v['nip_pembimbing'] }}@endif</p>
<p class="small">Penilai sumber industri: {{ $a->industry_assessor }}. Dicatat koordinator ID {{ $a->assessor_id }}; disahkan koordinator ID {{ $a->approved_by }}.</p></section>
@endif
</body></html>
