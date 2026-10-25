# Pemulihan arsip database — 25 Oktober 2026

Temuan flag Sertifikat mengenai uji pemulihan ditangani secara bertahap: **backup baru berhasil dipulihkan ke database MySQL lokal sementara**, tanpa menimpa database kerja. Hasil ini bukan klaim seluruh pemulihan sistem atau transaksi serentak sudah diuji.

## Hasil nyata

- 35 tabel: jumlah dan isi setiap baris, struktur kolom/default/collation, indeks, relasi FK, CHECK dan atribut tabel cocok setelah pemulihan.
- 6 berkas arsip lolos pemeriksaan base64, hash SHA-256, pasangan depan/belakang, referensi Word ke PDF sumber, serta ukuran bukti nilai.
- Terdapat **1 sertifikat terbit lama tanpa arsip versi aktif**. Tetap dilaporkan; tidak dibuatkan PDF palsu dan tidak direkonstruksi otomatis.
- Database sementara dihapus setelah pengujian. Pemeriksaan ulang menemukan 0 database dengan prefix `simmag_restore_probe_` tersisa.
- Backup terakhir: `storage/app/private/backups/archive-probe-20261025-042256-9d0d5be6772021cd.sql` (1.354.438 byte).
- Laporan: `storage/app/private/backups/archive-probe-20261025-042256-9d0d5be6772021cd.json`. Memuat hasil, jumlah dan hash, tanpa nama siswa atau isi berkas.

Percobaan awal mendeteksi perbedaan teks SQL `SHOW CREATE TABLE`: MySQL menambahkan deklarasi `CHARACTER SET` yang sebelumnya implisit. Pembanding kini memeriksa struktur semantik, sehingga perubahan struktur nyata tetap terdeteksi tanpa menganggap variasi penulisan SQL sebagai kehilangan data. Laporan percobaan gagal tetap tersimpan; database sementaranya sudah dihapus.

## Menjalankan ulang

Pastikan MySQL lokal aktif. Jalankan ketika aplikasi tidak sedang dipakai agar perubahan data tidak membuat hasil perbandingan tidak konsisten:

```powershell
php artisan simmag:verify-archive-backup --mysqldump=C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe --mysql=C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe
```

Jika kedua binary sudah masuk PATH, cukup `php artisan simmag:verify-archive-backup`.

Perintah membuat dump baru dari database aktif. Tidak menerima SQL unggahan atau nama database tujuan dari pengguna. Tujuan dibuat acak dengan prefix `simmag_restore_probe_`, tidak menggunakan ulang database yang sudah ada, dan hanya database yang dibuat oleh proses tersebut yang boleh dihapus. Tidak menjalankan `migrate:fresh`, migrasi atau seeder.

Pemeriksaan isi sumber dilakukan sebelum dan sesudah dump serta sesudah pemulihan. Perubahan data selama proses, arsip rusak, atau perbedaan hasil membuat status gagal/exit code 1. Tujuan sementara tetap dibersihkan; dump/laporan dipertahankan untuk pemeriksaan. Jika pembersihan gagal, nama database sementara ditampilkan dan hasil tidak disebut lulus.

## Cakupan dan batas

- Mendukung lingkungan `local`, MySQL lokal, tabel InnoDB dengan primary key. Konfigurasi replica, trigger, routine, event, view dan relasi lintas database ditolak untuk menghindari klaim pemulihan yang tidak didukung.
- PDF sertifikat, DOCX asli/salinan, desain PDF dan bukti nilai berada di database dan ikut diperiksa. Konten yang belum pernah disimpan sebagai arsip tidak bisa dipulihkan.
- Foto jurnal yang disimpan di filesystem **tidak** tercakup dalam dump SQL ini. Backup direktori upload, konfigurasi kunci aplikasi, pemulihan di server baru dan prosedur operasional masih perlu diuji terpisah.
- Uji ini pada server MySQL yang sama dengan database terpisah. Bukan simulasi kehilangan server/disk, bukti jadwal backup otomatis, maupun uji dua koordinator melakukan transaksi bersamaan.
- Dump berisi data pribadi dan hash akun; simpan tetap di direktori privat, jangan pindahkan ke `public/` atau commit ke repository. Laporan sengaja hanya berisi identitas tabel/ID dan hash.
- Pemeriksaan hash menjamin konsistensi berkas tersimpan, bukan pengesahan kebenaran isi penilaian industri atau kesamaan dengan dokumen fisik lama.

Flag Sertifikat belum dicopot karena arsip lama, pemulihan server/foto dan UAT/cetak tetap terbuka. Pengujian konkurensi lokal sudah dilanjutkan terpisah dan lulus sepuluh skenario; kapasitas staging belum diuji. [Bukti transaksi](UJI_MYSQL_SERTIFIKAT.md), [daftar kekurangan setelah upgrade](KEKURANGAN_SETELAH_UPGRADE.md).

## Pengujian kode

**155 tes / 2.903 assertion lulus.** Empat tes baru mencakup pembacaan integritas tanpa penulisan, deteksi PDF rusak/pasangan tidak lengkap, referensi Word salah, ukuran bukti nilai tidak cocok, laporan tanpa isi pribadi, penolakan lingkungan selain lokal dan tujuan database yang tidak aman. Uji pemulihan MySQL nyata di atas melengkapi tes SQLite; tidak memakai mock untuk dump/import/perbandingan hasil pemulihan.
