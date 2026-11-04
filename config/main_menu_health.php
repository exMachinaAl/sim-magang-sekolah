<?php

// Status hasil audit kode, bukan health check otomatis. Hapus tag hanya setelah
// kriteria penerimaan di docs/AUDIT_MENU_UTAMA.md diuji kembali.
return [
    'reviewed_at' => '2026-11-04',
    'modules' => [
        'dashboard' => [
            'name' => 'Dashboard', 'routes' => ['dashboard'],
            'status' => 'stable', 'priority' => 'P2', 'order' => 6,
            'summary' => 'Temuan audit Dashboard ditutup: filter tag tahun, siswa unik, dan rekap fase dari roster per jurusan sesuai hak akses.',
            'working' => 'Ringkasan jurnal, sertifikat, mitra dan penempatan mengikuti tahun terpilih. Koordinator/admin melihat roster sekolah termasuk siswa tanpa penempatan; guru hanya siswa dampingan, siswa hanya dirinya. Fase memakai tanggal WIB.',
            'issues' => [],
            'next' => 'Evaluasi angka dengan roster sekolah nyata. Masuk sekolah adalah fase tanggal, bukan verifikasi kehadiran. Stabil untuk cakupan audit, bukan klaim seluruh UAT selesai.',
        ],
        'timeline' => [
            'name' => 'Timeline PKL', 'routes' => ['timeline.*'],
            'status' => 'stable', 'priority' => 'P1', 'order' => 3,
            'summary' => 'Temuan audit Timeline ditutup: roster sebagai sumber, navigasi jurusan/siswa, dan pencarian serta paginasi database.',
            'working' => 'Siswa tanpa penempatan tetap terlihat; guru/siswa dibatasi hak akses, kelas/jurusan historis dari roster, fase otomatis sesuai tanggal WIB. Tes regresi, browser desktop/mobile, dan pemeriksaan SQL MySQL lulus.',
            'issues' => [],
            'next' => 'Pemantauan regresi tetap berjalan. Uji kapasitas sekolah nyata bukan jaminan dari pengujian data sintetis.',
        ],
        'journals' => [
            'name' => 'Jurnal & Foto', 'routes' => ['journals.*'],
            'status' => 'stable', 'priority' => 'P1', 'order' => 2,
            'summary' => 'Temuan audit Jurnal ditutup: kontrol koordinator, kiosk HTTPS, validasi QR, Excel enam entri, dan pemulihan foto telah diuji.',
            'working' => 'Koordinator mengontrol pembimbing/perangkat, guru boleh meninjau siswa terkait, foto opsional, nilai persis di kolom predikat, dan validasi QR terikat versi serta waktu scan. Excel A4 memakai margin seimbang dan lembar Rincian untuk narasi panjang.',
            'issues' => [],
            'next' => 'Lanjutkan koreksi hasil cetak pada printer sekolah bila ditemukan perbedaan. Uji kapasitas, pemulihan server tujuan, dan konfigurasi HTTPS produksi tetap menjadi checklist deployment, bukan bug modul lokal.',
        ],
        'assessments' => [
            'name' => 'Penilaian', 'routes' => ['assessments.*', 'assessment-templates.*'],
            'status' => 'stable', 'priority' => 'P1', 'order' => 5,
            'summary' => 'Temuan audit Penilaian ditutup: identitas historis roster, filter rekap dan bukti sumber industri.',
            'working' => 'Nama/NIS, tahun dan jurusan menyaring kandidat serta rekap sesuai akses. Lampiran privat tercatat dengan hash, pengunggah dan versi penilaian. Nilai disahkan serta PDF lama tidak diubah oleh unggahan.',
            'issues' => [],
            'next' => 'Koordinator melengkapi bukti yang belum ada dan memeriksa relevansi dokumennya. Stabil untuk temuan audit, bukan pengesahan otomatis isi bukti atau jaminan seluruh UAT selesai.',
        ],
        'monitoring' => [
            'name' => 'Monitoring', 'routes' => ['monitoring.*'],
            'status' => 'stable', 'priority' => 'P1', 'order' => 4,
            'summary' => 'Temuan audit Monitoring ditutup: pencatatan koordinator, kategori, penanggung jawab, status dan riwayat tindak lanjut.',
            'working' => 'Guru pendamping penempatan opsional; perubahan khusus koordinator, ID editor tercatat, tab lama ditolak. Catatan lama tidak dianggap selesai otomatis. Pencarian/filter dan paginasi tetap dibatasi hak akses.',
            'issues' => [],
            'next' => 'Klasifikasikan catatan lama bila diperlukan dan lanjutkan evaluasi pengguna. Monitoring berlaku pada siswa yang sudah ditempatkan; siswa tanpa penempatan tetap di Kontrol PKL. Stabil untuk temuan audit, bukan jaminan bebas bug baru.',
        ],
        'certificates' => [
            'name' => 'Sertifikat', 'routes' => ['certificates.*','certificate-print-templates.*'],
            'status' => 'stable', 'priority' => 'P1', 'order' => 1,
            'summary' => 'Temuan audit Sertifikat ditutup: identitas PDF terpusat, arsip per versi immutable, dan integritas dokumen diperiksa sebelum distribusi.',
            'working' => 'PDF bawaan dan PDF berdekorasi tersedia tanpa LibreOffice, dengan area isi per sisi dalam mm dan pratinjau tanpa penerbitan. DOCX/LibreOffice tetap ekstensi opsional. Nilai, penandatangan, dan arsip per versi tetap terjaga.',
            'issues' => [],
            'next' => 'Gunakan revisi resmi bila dokumen lama perlu layout baru; arsip terbit tidak ditimpa. Koreksi cetak printer sekolah tetap dicatat sebagai UAT. Pemulihan server dan kapasitas berada pada checklist deployment. DOCX/LibreOffice tetap ekstensi opsional.',
        ],
    ],
];
