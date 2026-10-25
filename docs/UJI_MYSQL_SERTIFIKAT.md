# Stabilitas transaksi Sertifikat — 25 Oktober 2026

Dua kegagalan nyata ditemukan dan diperbaiki:

1. Dua koordinator mengajukan sertifikat siswa yang sama: permintaan kedua bisa error 500 karena pemeriksaan sertifikat membaca snapshot transaksi lama. Pencarian penempatan biasa dilakukan sebelum menunggu kunci roster, sehingga MySQL repeatable-read mempertahankan keadaan sebelum permintaan pertama selesai. Kini identitas penempatan diambil sebelum transaksi, lalu roster dan penempatan dikunci berurutan sebelum pembacaan sumber. Hubungan siswa/tahun/roster diperiksa lagi setelah memperoleh kunci. Pengajuan kedua ditolak sebagai validasi, tanpa dokumen ganda.
2. Dua siswa diajukan dengan nomor yang sama: validasi awal bisa sama-sama lolos, kemudian indeks unik database menolak salah satunya dengan error 500. Kini transaksi yang kalah dibatalkan dan pengguna mendapat pesan pada field nomor. Penanganan juga berlaku ketika nomor otomatis sudah dipakai manual; pelanggaran integritas lain tidak disembunyikan.

Tidak ada migrasi baru, perubahan desain sertifikat, atau rekonstruksi sertifikat lama pada database kerja. LibreOffice tetap ekstensi opsional.

## Mengulang uji lokal

```powershell
php scripts/certificate-mysql-races.php
```

Memerlukan lingkungan `local` dengan MySQL loopback dan hak membuat database uji. Script membuat `simmag_cert_probe_<acak>`, lalu menjalankan migrasi, seeder dan seluruh skenario di proses PHP baru yang sejak bootstrap memakai database tersebut. Penyimpanan file/cache/sesi uji berada di direktori OS sementara. Tidak menggunakan `migrate:fresh` pada database kerja.

Transaksi pengatur menahan baris sampai peserta benar-benar mencoba `SELECT ... FOR UPDATE`; kemudian kunci dilepas. Peserta memakai controller aplikasi dan dua akun koordinator fiktif melalui koneksi independen. Ini menguji benturan nyata, bukan permintaan berurutan. `certificate-race-scenarios.php` hanya runner internal yang dipanggil script utama.

| Skenario | Hasil |
| --- | --- |
| Pengajuan siswa yang sama | Satu sertifikat, satu arsip, satu audit; permintaan kedua mendapat validasi. |
| Pengesahan bersamaan | Satu penerbitan dan satu keputusan; ID koordinator pemenang tercatat. |
| Distribusi bersamaan | Hanya satu perpindahan tahap dan riwayat. |
| Revisi bersamaan | Versi naik sekali; arsip terbit lama tidak berubah. |
| Pengesahan menunggu revisi | Versi lama ditolak 409. |
| Rekonstruksi arsip lama bersamaan | Hanya satu arsip; permintaan kedua ditolak 409. Fixture lama dibuat khusus di database uji. |
| Pengesahan menunggu koreksi jadwal | Perubahan sumber terbaca; pengesahan ditolak sebagai validasi 422. |
| Nomor sama pada siswa berbeda | Satu pengajuan; lainnya mendapat validasi nomor dan tidak meninggalkan sertifikat parsial. |
| Distribusi menunggu revisi | Distribusi versi lama ditolak; arsip lama tetap utuh. |
| Penyiapan DOCX bersamaan | Satu salinan Word, hash depan/belakang sesuai, PDF sumber tidak berubah. |

Run akhir `c6a2faf138d08c4c`: **10 PASS**, `temporary_database_removed: true`, `working_database_rows_unchanged: true`. Laporan JSON tersedia di direktori `simmag-cert-race-*` pada OS temporary directory yang dicetak script. Fingerprint membandingkan seluruh isi tabel kerja sebelum/sesudah; perubahan eksternal selama tes juga menyebabkan pemeriksaan gagal. Script tidak menimpa perubahan tersebut.

Regresi aplikasi: `php artisan test --compact` — **156 tes, 2.908 assertion lulus**. Termasuk tes baru untuk nomor otomatis yang sudah ditempati nomor manual, pesan validasi dan pembatalan pengajuan tanpa sisa arsip.

## Batas dan sisa flag

Tes ini memanggil controller melalui proses CLI; tidak membuktikan HTTPS, kamera, middleware HTTP atau kapasitas server. Tes regresi HTTP tetap dijalankan terpisah. Sepuluh urutan transaksi tidak mencakup semua kemungkinan konflik atau konfigurasi deployment.

Satu sertifikat lama pada database kerja tetap tanpa arsip aktif: pemeriksaan koordinator dan rekonstruksi eksplisit masih diperlukan. Uji rekonstruksi di atas tidak mengesahkan dokumen nyata tersebut. Cetak bolak-balik, alur dokumen sumber sampai penyerahan, pemulihan server/foto dan kapasitas staging juga masih terbuka. Flag merah tetap menjelaskan kekurangan ini; HTTPS/kiosk ditunda sesuai arahan pengguna.
