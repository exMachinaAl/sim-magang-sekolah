<?php

// Status hasil audit kode, bukan health check otomatis. Hapus tag hanya setelah
// kriteria penerimaan di docs/AUDIT_MENU_UTAMA.md diuji kembali.
return [
    'reviewed_at' => '2026-10-24',
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
            'summary' => 'Alur jurnal, QR, dan Excel lulus tes otomatis serta smoke test browser terpisah. Menunggu UAT kamera HP dan cetak fisik, belum klaim stabil produksi.',
            'working' => 'Koordinator mengontrol pembimbing/perangkat, guru boleh meninjau siswa terkait, foto opsional, nilai persis di kolom predikat, dan validasi QR terikat versi serta waktu scan.',
            'issues' => [
                'QR pendaftaran adalah akses sekali pakai: siapa yang memakainya pertama dapat mendaftarkan browser. Berikan hanya kepada pembimbing; koordinator dapat mencabut perangkat.',
                'Kepercayaan perangkat berbasis cookie browser. Incognito, penghapusan cookie, atau browser kamera yang berbeda memerlukan pendaftaran ulang.',
                'PDF menolak hasil di atas 300 entri, bukan memotong data. Excel menyediakan enam entri per lembar dan Rincian untuk narasi panjang; hasil cetak fisik perlu diperiksa.',
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
            'status' => 'red', 'priority' => 'P0', 'order' => 1,
            'summary' => 'Dokumen baru sudah diarsip per versi. Sertifikat terbit sebelum upgrade harus diperiksa koordinator sebelum rekonstruksi arsip; tidak dibuat otomatis.',
            'working' => 'Arsip PDF per versi tetap utuh. Template cetak, pilihan penandatangan depan/belakang, identitas lengkap, format nilai bebas atau 80/20, dan unggah DOCX tersedia. Input/pengesahan tetap koordinator.',
            'issues' => [
                'PDF asli dokumen terbit lama tidak dapat dipulihkan dari data hidup. Unduhan/distribusi versi tanpa arsip ditahan; rekonstruksi harus eksplisit, disertai catatan pemeriksaan koordinator.',
                'Dokumen fisik lama yang diganti melalui revisi perlu penarikan/koordinasi offline. Tautan arsip versi lama tetap tersedia sebagai riwayat, bukan versi yang berlaku.',
                'Arsip PDF menambah ukuran database dan backup. Uji pemulihan arsip, MySQL konkuren, serta cetak bolak-balik template sekolah masih diperlukan.',
                'PDF bawaan dan salinan DOCX dari arsip lengkap sudah tersedia tanpa LibreOffice. Konversi unggahan DOCX menjadi PDF masih memerlukan LibreOffice. Tata letak/jumlah halaman Word dan cetak fisik perlu diperiksa pengguna.',
            ],
            'next' => 'Tinjau arsip lama, konfigurasi LibreOffice untuk DOCX, lalu uji format cetak dan tanda tangan sesuai dokumen sekolah. Flag belum dilepas.',
        ],
    ],
];
