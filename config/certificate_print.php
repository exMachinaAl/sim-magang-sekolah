<?php
return [
    // Absolute server-side executable path, never accepted from an HTTP request.
    'libreoffice' => env('CERTIFICATE_LIBREOFFICE', ''),
    'front_variants' => ['kepala_sekolah'=>'Kepala sekolah', 'kepala_sekolah_dan_industri'=>'Kepala sekolah dan industri'],
    'back_variants' => ['pembimbing_industri'=>'Pembimbing industri', 'pembimbing_sekolah'=>'Pembimbing sekolah'],
    'settings' => [
        'nama_sekolah_judul'=>'Nama sekolah pada judul', 'nama_sekolah_ttd'=>'Nama sekolah pada tanda tangan',
        'nama_kepala_sekolah'=>'Nama kepala sekolah dan gelar', 'nip_kepala_sekolah'=>'NIP kepala sekolah',
        'bidang_keahlian'=>'Bidang keahlian', 'program_keahlian'=>'Program keahlian', 'kota_terbit'=>'Kota penerbitan',
    ],
    'mapping' => ['soft_skills'=>'Soft skills', 'hard_skills'=>'Hard skills', 'pengembangan_hard_skills'=>'Pengembangan hard skills',
        'kewirausahaan'=>'Kewirausahaan', 'laporan_pkl'=>'Laporan PKL'],
];
