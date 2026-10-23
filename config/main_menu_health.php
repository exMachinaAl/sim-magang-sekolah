<?php

// Status hasil audit kode, bukan health check otomatis. Hapus tag hanya setelah
// kriteria penerimaan di docs/AUDIT_MENU_UTAMA.md diuji kembali.
return [
    'reviewed_at' => '2026-10-23',
    'modules' => [
        'dashboard' => [
            'name' => 'Dashboard', 'routes' => ['dashboard'],
            'status' => 'unstable', 'priority' => 'P2', 'order' => 6,
            'summary' => 'Ringkasan belum dipisahkan per tahun ajaran dan belum menjadi rekap status PKL seluruh sekolah.',
            'working' => 'Data diri dan status PKL siswa sudah membaca profil serta roster Kontrol PKL.',
            'issues' => [
                'Angka jurnal, sertifikat, dan penempatan mencampur seluruh tahun ajaran; belum ada filter tag tahun.',
                'Siswa Terpantau untuk guru/siswa menghitung penempatan, bukan siswa unik. Satu siswa lintas tahun dapat dihitung beberapa kali.',
                'Belum ada ringkasan Sedang PKL / Masuk sekolah per jurusan dari seluruh roster.',
            ],
            'next' => 'Pisahkan ringkasan per peran, filter tahun ajaran, dan gunakan siswa unik serta fase dari Kontrol PKL.',
        ],
        'timeline' => [
            'name' => 'Timeline PKL', 'routes' => ['timeline.*'],
            'status' => 'unstable', 'priority' => 'P1', 'order' => 3,
            'summary' => 'Tanggal sudah benar, tetapi cakupan timeline masih bergantung pada Penempatan dan pengelompokan guru.',
            'working' => 'Tanggal mulai, selesai, dan fase otomatis mengambil roster siswa; tanggal tag periode tidak digunakan.',
            'issues' => [
                'Siswa yang hanya terdaftar di Kontrol PKL belum muncul, termasuk siswa yang jadwalnya sudah diisi tetapi industrinya belum ditentukan.',
                'Navigasi koordinator masih berbasis guru, belum jurusan lalu siswa. Kelas/jurusan tampilan masih membaca profil terkini.',
                'Seluruh penempatan pada tag dimuat sebelum pencarian dan paginasi; ada risiko lambat pada data besar, belum diuji beban.',
            ],
            'next' => 'Jadikan roster sumber daftar, buka per jurusan/siswa, dan lakukan pencarian serta paginasi di database.',
        ],
        'journals' => [
            'name' => 'Jurnal & Foto', 'routes' => ['journals.*'],
            'status' => 'unstable', 'priority' => 'P1', 'order' => 2,
            'summary' => 'Alur jurnal, Excel, dan QR industri sudah diperbarui. Uji perangkat kamera/browser dan hasil cetak fisik masih diperlukan.',
            'working' => 'Koordinator mengontrol pembimbing/perangkat, guru boleh meninjau siswa terkait, foto opsional, nilai persis di kolom predikat, dan validasi QR terikat versi serta waktu scan.',
            'issues' => [
                'QR pendaftaran adalah akses sekali pakai: siapa yang memakainya pertama dapat mendaftarkan browser. Berikan hanya kepada pembimbing; koordinator dapat mencabut perangkat.',
                'Kepercayaan perangkat berbasis cookie browser. Incognito, penghapusan cookie, atau browser kamera yang berbeda memerlukan pendaftaran ulang.',
                'PDF rekap lama masih dibatasi 300 baris. Gunakan Excel enam entri per lembar untuk pembukuan dan periksa keterbacaan sebelum mencetak narasi panjang.',
            ],
            'next' => 'Lakukan uji Google Camera pada HP pembimbing melalui host yang dapat diakses, lalu evaluasi hasil cetak template bersama pengguna.',
        ],
        'assessments' => [
            'name' => 'Penilaian', 'routes' => ['assessments.*', 'assessment-templates.*'],
            'status' => 'unstable', 'priority' => 'P1', 'order' => 5,
            'summary' => 'Template dan nilai sudah terhubung, tetapi konteks jurusan historis serta penelusuran dokumen industri belum lengkap.',
            'working' => 'Input/validasi khusus koordinator, pencarian nama/NIS tersedia, template mengikuti jurusan roster dan nilai disahkan dikunci.',
            'issues' => [
                'Label kelas/jurusan pada hasil pencarian dan detail memakai profil siswa terkini, sedangkan template memakai jurusan roster tahun tersebut.',
                'Nama penilai dan tanggal industri dicatat, tetapi belum ada lampiran dokumen sumber nilai untuk penelusuran ulang.',
                'Pencarian nama/NIS berlaku untuk memilih penempatan, bukan menyaring tabel Rekap Penilaian; belum ada filter rekap per tahun/jurusan.',
            ],
            'next' => 'Samakan label dengan roster tahun terpilih, tambahkan bukti sumber nilai dan filter rekap; pertahankan pencarian nama/NIS, bukan selector siswa.',
        ],
        'monitoring' => [
            'name' => 'Monitoring', 'routes' => ['monitoring.*'],
            'status' => 'red', 'priority' => 'P1', 'order' => 4,
            'summary' => 'Pencatatan monitoring masih khusus guru, belum menjadi penanganan kasus oleh koordinator.',
            'working' => 'Riwayat sudah terhubung ke penempatan siswa dan dibatasi sesuai hak akses.',
            'issues' => [
                'Koordinator tidak mendapat form dan ditolak saat menyimpan (403); siswa tanpa guru tidak dapat ditangani melalui alur monitoring ini.',
                'Pencatat disimpan sebagai teacher_id. Belum ada penanggung jawab kasus, kategori kasus, dan status tindak lanjut terstruktur.',
                'Pemilihan siswa masih dropdown semua penempatan; belum pencarian nama/NIS dan filter jurusan/tahun.',
            ],
            'next' => 'Ubah menjadi catatan kasus koordinator dengan actor ID; guru dilibatkan hanya saat diperlukan.',
        ],
        'certificates' => [
            'name' => 'Sertifikat', 'routes' => ['certificates.*'],
            'status' => 'red', 'priority' => 'P0', 'order' => 1,
            'summary' => 'Dokumen belum dibekukan saat diterbitkan; perubahan data master atau jadwal dapat mengubah hasil cetak ulang.',
            'working' => 'Nilai mengambil penilaian disahkan, ketentuan template disalin saat penilaian, dan tanggal PDF mengambil roster, bukan tag periode.',
            'issues' => [
                'Identitas siswa, perusahaan, dan tanggal PKL pada PDF dibaca dari data hidup, belum snapshot dokumen per versi.',
                'Kelengkapan tanggal roster baru dicek saat cetak PDF. Pembuatan dan persetujuan dapat lolos, lalu cetak gagal (422).',
                'Kandidat sertifikat masih dropdown semua penempatan tanpa sertifikat; belum pencarian nama/NIS dan filter tahun/jurusan.',
            ],
            'next' => 'Periksa kesiapan sejak pengajuan, bekukan isi sertifikat per versi, dan sediakan revisi resmi tanpa mengubah dokumen terbit sebelumnya.',
        ],
    ],
];
