# Uji transaksi Jurnal — 25 Oktober 2026

Tahap ini menutup bagian pengujian benturan transaksi lokal pada flag Jurnal. Tidak mengubah alur bisnis, tidak menambah fitur pengguna, dan tidak membutuhkan migrasi database kerja.

Jalankan ketika MySQL lokal aktif dan aplikasi tidak sedang menerima perubahan data:

```powershell
php scripts/journal-mysql-races.php
```

Script hanya menerima lingkungan `local` dan MySQL loopback. Ia membuat database baru bernama acak `simmag_journal_probe_*`; migrasi, seeder dan skenario dijalankan dalam proses baru yang sejak awal memakai database tersebut. Tidak memakai `migrate:fresh`. Database sementara dihapus dalam blok pembersihan. Hasil JSON disimpan di direktori OS temporary `simmag-journal-race-*` yang dicetak oleh perintah.

Setiap skenario menahan baris pada transaksi pengatur sampai seluruh proses peserta benar-benar mencoba `SELECT ... FOR UPDATE`. Ini menguji penantian kunci nyata, bukan hanya memanggil controller berurutan. Sebelum dan sesudah pengujian, hash seluruh isi tabel database kerja dibandingkan. Perubahan dari pengguna lain selama pengujian juga membuat pemeriksaan ini gagal; script tidak membatalkan atau menimpa perubahan tersebut.

| Skenario | Hasil yang diverifikasi |
| --- | --- |
| Dua scan QR yang sama | Kedua permintaan selesai; hanya satu validasi dan satu audit. |
| Dua tab mengedit versi jurnal yang sama | Satu disimpan; tab kedaluwarsa ditolak 422, revisi hanya naik sekali. |
| Siswa mengedit saat scan menunggu | QR versi lama ditolak 409; tidak ada validasi. |
| Koordinator mencabut perangkat saat scan menunggu | Scan ditolak 403; tidak ada validasi. |
| Dua browser memakai undangan perangkat yang sama | Hanya satu perangkat baru; penggunaan kedua ditolak 410. |
| Koordinator mengoreksi jadwal saat siswa menyimpan | Tanggal di luar jadwal terbaru ditolak 422; isi/revisi jurnal dan riwayat tidak berubah. |

Run akhir `e6007ee756f038f8`: enam skenario PASS, `working_database_rows_unchanged: true`, `temporary_database_removed: true`. Skenario memanggil controller aplikasi melalui proses PHP terpisah. Bukti ini tidak mencakup middleware HTTP, kamera, cookie browser, HTTPS, kapasitas server, atau semua urutan transaksi. Pengujian HTTP/browser sebelumnya dan UAT HP tetap diperlukan.

Saat pengembangan alat uji, dua percobaan awal salah mengisolasi koneksi Eloquent dan sempat menambahkan fixture sintetis ke database kerja. Jurnal, mentor, perangkat dan relasi milik percobaan tersebut telah dihapus dengan pemeriksaan ID/isi/waktu dan dependensi, tanpa reset tabel. Nomor ID yang sempat terpakai tidak direset. Implementasi akhir memakai proses baru sejak bootstrap; hasil akhir memverifikasi isi database kerja tetap sama. Tidak ada fixture tersebut yang tersisa.

Flag Jurnal tetap UNSTABLE untuk pengujian HP Google Camera/Lens, konfigurasi HTTPS, cetak Excel fisik, pemulihan foto dan kapasitas staging. Konkurensi Sertifikat merupakan pekerjaan terpisah yang belum ditutup oleh tes ini.
