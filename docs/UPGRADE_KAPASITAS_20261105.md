# Upgrade uji kapasitas lokal — 5 November 2026

## Hasil

Upgrade ini menambahkan probe kapasitas yang dapat diulang untuk menguji volume setara satu angkatan tanpa menyentuh database kerja. Status tahap ini: **LULUS OTOMATIS**.

Probe membuat database MySQL bernama acak, mengisinya dengan data sintetis, menjalankan pembacaan menu utama dan pembuatan Excel Jurnal, lalu menghapus database tersebut. Sebelum dan sesudah pengujian, jumlah baris serta hash isi setiap tabel database kerja dibandingkan. Laporan tidak memuat data pribadi, password, token, atau isi jurnal.

## Sebelum dan sesudah

| Sebelum | Sesudah |
| --- | --- |
| Bukti utama memakai 31 data demo dan tes transaksi terpisah. | Ada dataset sintetis 320 siswa, 320 roster, 256 penempatan, 5.120 jurnal, 128 penilaian, dan 128 sertifikat. |
| Belum ada pengukuran berulang untuk seluruh menu pada volume satu angkatan. | Sepuluh probe mengukur waktu, jumlah query, waktu SQL, dan tambahan memori. |
| Risiko uji kapasitas mengenai database kerja bergantung pada disiplin operator. | Script menolak lingkungan selain `local`, menolak host database nonlokal, memakai nama database acak, memeriksa kepemilikan konteks sementara, dan membuktikan database kerja tidak berubah. |
| Pembersihan database sementara belum menjadi syarat kelulusan. | Penghapusan database sementara dan tidak berubahnya database kerja menjadi syarat wajib status `passed`. |

Upgrade ini tidak mengubah aturan bisnis, tampilan, migrasi, `.env`, maupun data demo. Tidak ada `migrate:fresh` atau seeding pada database kerja.

## Bukti pengujian pengembang

Tiga eksekusi final berurutan lulus:

| Eksekusi | Hasil | Waktu probe terlama | Memori puncak | Database sementara | Database kerja |
| --- | --- | ---: | ---: | --- | --- |
| 1 | Lulus | 214,00 ms | 66 MB | Dihapus | Tidak berubah |
| 2 | Lulus | 540,40 ms | 66 MB | Dihapus | Tidak berubah |
| 3 | Lulus | 215,94 ms | 66 MB | Dihapus | Tidak berubah |

Pada eksekusi pertama, Dashboard selesai dalam 214,00 ms dan workbook Excel Jurnal berisi 20 baris selesai dalam 108,52 ms. Semua probe berada di bawah ambang 3.000 ms dan batas query yang ditentukan. Pemeriksaan langsung ke `information_schema` menemukan **0** database dengan awalan `simmag_capacity_probe_` setelah pengujian.

Regresi aplikasi setelah upgrade: **168 tes lulus / 2.953 assertion** dalam 103,47 detik. `php -l scripts/capacity-probe.php` dan pemeriksaan whitespace juga lulus.

Catatan: dua percobaan pengembangan sebelum tiga hasil final sempat gagal karena kontrak hasil worker dan pemeriksaan hasil workbook belum tepat. Fail-closed bekerja: database sementara tetap dihapus dan database kerja tetap tidak berubah. Masalah tersebut diperbaiki sebelum tiga eksekusi final di atas.

## Cara uji manual opsional

Pengujian ini sudah dijalankan dan lulus; langkah berikut hanya untuk mengulang bila Anda sudah dapat melakukan tes.

1. Pastikan MySQL lokal berjalan dan `.env` tetap menggunakan `APP_ENV=local` serta host lokal (`127.0.0.1`, `localhost`, atau `::1`).
2. Dari root proyek jalankan:

   ```powershell
   php scripts/capacity-probe.php
   ```

3. Hasil yang benar berakhir dengan `PASS: capacity probe` dan menampilkan lokasi `result.json` di folder sementara Windows.
4. Buka laporan tersebut. Pastikan:
   - `status` bernilai `passed`;
   - `temporary_database_removed` bernilai `true`;
   - `working_database_unchanged` bernilai `true`;
   - seluruh `wall_ms` tidak melewati `threshold_ms`;
   - seluruh `queries` tidak melewati `threshold_queries`.
5. Bila script menolak lingkungan atau host, jangan melonggarkan pengaman. Probe memang hanya untuk MySQL lokal dan tidak boleh diarahkan ke staging/produksi.

## Batas bukti

Hasil ini membuktikan bentuk query dan render server pada satu komputer dengan volume data satu angkatan. Hasil ini belum membuktikan kapasitas jaringan, web server, disk, atau ratusan request HTTP bersamaan pada server produksi. Kualitas cetak fisik Sertifikat juga tetap memerlukan koreksi visual pengguna.

## Roadmap setelah tahap ini

1. Koreksi visual Sertifikat tetap menjadi UAT dokumen berikutnya ketika pengguna dapat menguji.
2. Tentukan server dan domain staging; terapkan konfigurasi HTTPS tetap.
3. Jalankan pemeriksaan deployment dan pemulihan pasangan backup SQL + ZIP foto di server staging terpisah.
4. Jalankan load test HTTP bersamaan pada staging; jangan memakai script lokal ini sebagai pengganti uji infrastruktur.
5. Susun keputusan produksi, jadwal backup, dan latihan pemulihan setelah seluruh pemeriksaan staging lulus.
