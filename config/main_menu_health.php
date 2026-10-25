<?php

// Status hasil audit kode, bukan health check otomatis. Hapus tag hanya setelah
// kriteria penerimaan di docs/AUDIT_MENU_UTAMA.md diuji kembali.
return [
    'reviewed_at' => '2026-10-25',
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
            'status' => 'unstable', 'priority' => 'P1', 'order' => 2,
            'summary' => 'Alur jurnal, QR, dan Excel lulus tes otomatis/browser; enam skenario transaksi MySQL bersamaan juga lulus. Menunggu UAT HP, HTTPS, cetak fisik dan pemulihan foto.',
            'working' => 'Koordinator mengontrol pembimbing/perangkat, guru boleh meninjau siswa terkait, foto opsional, nilai persis di kolom predikat, dan validasi QR terikat versi serta waktu scan.',
            'issues' => [
                'QR pendaftaran adalah akses sekali pakai: siapa yang memakainya pertama dapat mendaftarkan browser. Berikan hanya kepada pembimbing; koordinator dapat mencabut perangkat.',
                'Kepercayaan perangkat berbasis cookie browser. Incognito, penghapusan cookie, atau browser kamera yang berbeda memerlukan pendaftaran ulang.',
                'PDF menolak hasil di atas 300 entri, bukan memotong data. Excel menyediakan enam entri per lembar dan Rincian untuk narasi panjang; hasil cetak fisik perlu diperiksa.',
                'Dua scan/edit bersamaan, QR setelah edit, pencabutan perangkat, undangan ganda dan koreksi jadwal sudah diuji di MySQL lokal terpisah. Kapasitas staging, HTTPS dan pemulihan foto masih perlu diuji.',
            ],
            'next' => 'Lakukan uji Google Camera pada HP pembimbing melalui host yang dapat diakses, lalu evaluasi hasil cetak template bersama pengguna.',
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
            'status' => 'unstable', 'priority' => 'P1', 'order' => 1,
            'summary' => 'Arsip per versi dan transaksi bersamaan sudah diuji. Database demo telah direset; tidak ada sertifikat terbit tanpa arsip aktif. Menunggu UAT cetak, pemulihan server dan kapasitas staging.',
            'working' => 'PDF bawaan dan PDF berdekorasi tersedia tanpa LibreOffice, dengan area isi per sisi dalam mm dan pratinjau tanpa penerbitan. DOCX/LibreOffice tetap ekstensi opsional. Nilai, penandatangan, dan arsip per versi tetap terjaga.',
            'issues' => [
                'Jika database lain masih mempunyai dokumen lama tanpa arsip, unduhan/distribusi tetap ditahan. Rekonstruksi harus eksplisit setelah pemeriksaan koordinator; PDF asli tidak dapat dipulihkan dari data hidup.',
                'Dokumen fisik lama yang diganti melalui revisi perlu penarikan/koordinasi offline. Tautan arsip versi lama tetap tersedia sebagai riwayat, bukan versi yang berlaku.',
                'Pemulihan database/arsip dan sepuluh skenario transaksi Sertifikat bersamaan sudah lulus pada MySQL lokal terpisah. Pemulihan server baru beserta foto, kapasitas staging dan cetak bolak-balik sekolah masih perlu diuji.',
                'Desain PDF harus satu halaman A4 portrait per sisi, tanpa sandi, dengan area kosong untuk isi. Pengaturan berlaku pada kotak seluruh isi, bukan editor posisi tiap field; tata letak/cetak sekolah tetap perlu diperiksa.',
                'Konversi unggahan DOCX menjadi PDF adalah ekstensi opsional yang memerlukan LibreOffice. Salinan DOCX tetap tersedia tanpa konversi, tetapi tidak otomatis menyalin dekorasi PDF.',
            ],
            'next' => 'Periksa hasil cetak 100% dan bolak-balik sesuai dokumen sekolah, lanjutkan pemulihan server/foto serta uji kapasitas. LibreOffice hanya perlu untuk ekstensi konversi DOCX. Flag UAT belum dilepas.',
        ],
    ],
];
