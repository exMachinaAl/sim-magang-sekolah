# Persiapan deployment staging SIMMAG — 4 November 2026

## Tujuan dan batas

Tahap ini menyiapkan konfigurasi staging yang dapat diperiksa secara berulang. Tidak ada server eksternal yang diubah, tidak ada database direset, dan tidak ada kredensial yang dibuat atau disimpan dalam dokumentasi. Aplikasi lokal tetap berjalan sebagai lingkungan demo.

HTTPS produksi/staging tidak membutuhkan API key Laravel. Domain diarahkan ke server, lalu sertifikat publik dan private key TLS dipasang pada web server/control panel. Private key TLS tidak dimasukkan ke repository atau `.env`; Laravel cukup menerima `APP_URL=https://...` dan request HTTPS langsung atau header dari proxy yang IP-nya dipercaya.

## Pemeriksaan otomatis

Jalankan pada server tujuan setelah `.env` dan database disiapkan:

```powershell
php artisan simmag:check-deployment --target=staging
```

Untuk laporan JSON yang tidak memuat APP_KEY, password, token, atau isi data:

```powershell
php artisan simmag:check-deployment --target=staging --json
```

Exit code `0` berarti seluruh pemeriksaan wajib lulus. Saran nonblokir tetap ditampilkan terpisah. Target produksi menggunakan `--target=production`; pada target tersebut akun `@simmag.test` menjadi kegagalan wajib.

Pemeriksaan mencakup lingkungan, debug, validitas APP_KEY tanpa menampilkan nilainya, HTTPS/domain, cookie aman, session/cache persisten, daftar proxy eksplisit, MySQL dan koneksinya, tabel inti, migrasi tertunda, akun demo, storage upload privat, izin tulis runtime, serta level log.

## Hasil komputer lokal saat ini

Perintah staging sengaja menghasilkan exit code `1`: **2 kegagalan wajib dan 2 saran**.

- Wajib: `APP_ENV` masih `local`, bukan `staging`.
- Wajib: `LOG_LEVEL` masih `debug`; staging memakai `info` atau lebih ketat.
- Saran: `APP_URL` memakai alamat IP uji, sehingga berubah ketika IP Wi-Fi berubah. Staging sebaiknya memakai domain/hostname tetap.
- Saran: akun demo masih tersedia. Boleh pada staging terbatas tanpa data asli, tetapi wajib dihapus sebelum produksi.

Pemeriksaan lain lulus pada komputer lokal: APP_DEBUG mati, APP_KEY valid, URL HTTPS, secure/HttpOnly/SameSite cookie, session/cache persisten, proxy eksplisit, koneksi MySQL, tabel inti, migrasi terkini, storage privat, dan izin tulis runtime.

## Pengujian pengembang

- Tes pemeriksaan deployment dan proxy HTTPS: **6 tes lulus / 27 assertion**.
- Regresi aplikasi lengkap setelah upgrade FAQ/petunjuk kontekstual: **172 tes lulus / 3.016 assertion**, 123,09 detik.
- Probe kapasitas lokal satu angkatan lulus tiga kali: 320 siswa, 256 penempatan, 5.120 jurnal, 128 penilaian dan 128 sertifikat. Waktu probe terlama 540,40 ms; database sementara dihapus dan database kerja terbukti tidak berubah. [Bukti dan cara mengulang](UPGRADE_KAPASITAS_20261105.md).
- Alur browser Sertifikat dan pemeriksaan geometri dua halaman A4 lulus untuk pratinjau serta dokumen terbit; seluruh pusat identitas berada tepat pada 297,64 pt. [Bukti dan cara koreksi](UPGRADE_SERTIFIKAT_LAYOUT_20261105.md).
- FAQ berbasis role dan tombol bantuan setiap halaman lulus tes browser untuk hover tertunda, klik, keyboard, pencarian, serta viewport ponsel. [Bukti dan cara uji](UPGRADE_FAQ_PANDUAN_20261106.md).
- `composer check-platform-reqs --no-dev`: seluruh kebutuhan PHP 8.2 dan ekstensi paket lulus pada komputer pengembangan.
- Cache konfigurasi dan kompilasi Blade berhasil dibuat lalu dibersihkan kembali; command staging tetap terdaftar.
- Pemeriksaan tautan dokumentasi dan `git diff --check` lulus.

Pengujian tidak mengubah `.env` aktif, tidak menjalankan migrasi pada database kerja, dan tidak membuat/reset data. Database SQLite sementara pada tes otomatis dihapus setelah pemeriksaan.

## Berkas konfigurasi

Salin [template staging](../.env.staging.example) menjadi `.env` **di server staging**, lalu isi domain dan kredensial database di server tersebut. Jangan commit `.env`.

Untuk instalasi baru, buat APP_KEY sekali melalui `php artisan key:generate`. Setelah sistem menyimpan data, jangan mengganti key tanpa prosedur rotasi karena cookie dan data terenkripsi lama dapat menjadi tidak terbaca. Staging dan produksi sebaiknya memakai key berbeda.

`TRUSTED_PROXIES` boleh kosong bila PHP menerima HTTPS langsung. Bila memakai Nginx/Apache/load balancer sebagai reverse proxy, isi hanya IP proxy sebenarnya, misalnya `127.0.0.1,::1` bila proxy berada pada server yang sama. Wildcard tidak diterima.

## Urutan pemasangan staging

1. Siapkan domain staging dan TLS pada web server. Arahkan document root ke folder `public/`, bukan root repository.
2. Instal PHP 8.2+, ekstensi yang diminta Composer, MySQL, serta Composer. Jalankan `composer install --no-dev --optimize-autoloader`.
3. Salin `.env.staging.example` menjadi `.env`, isi APP_URL, APP_KEY dan kredensial database langsung di server.
4. Pastikan `storage/` dan `bootstrap/cache/` dapat ditulis oleh akun proses web. Upload jurnal tetap berada di `storage/app/private`.
5. Buat backup bila database staging sudah berisi data, lalu jalankan `php artisan migrate --force`. Jangan menjalankan `migrate:fresh` atau seeder demo pada database yang hendak dipertahankan.
6. Jalankan `php artisan optimize`, lalu `php artisan simmag:check-deployment --target=staging`.
7. Uji `/up`, login tiap role, unduh foto privat, pratinjau sertifikat, ekspor jurnal, enrollment kiosk, scan QR, dan logout melalui domain HTTPS.
8. Periksa log web server agar query/token QR tidak direkam. Simpan backup SQL, ZIP foto, dan laporan hash di storage privat di luar web root.

## Yang perlu diuji pengguna

Tahap konfigurasi baru benar-benar dapat diuji setelah domain dan server staging tersedia. Catat:

1. Domain staging yang dibuka tanpa peringatan sertifikat pada komputer dan HP.
2. Hasil command deployment: harus `0 kegagalan wajib`.
3. Cookie login beratribut Secure dan sesi tetap bekerja setelah browser ditutup/dibuka sesuai kebijakan.
4. Google Camera/Lens membuka domain yang sama dan validasi jurnal berhasil pada browser yang sudah didaftarkan.
5. File privat tidak dapat dibuka tanpa login atau oleh siswa lain.

Jangan mengirim APP_KEY, password database, private key TLS, cookie, atau QR aktif saat melaporkan hasil.

## Roadmap lanjutan

1. Render digital Sertifikat sudah lulus geometri dan inspeksi visual otomatis; penerimaan cetak fisik tetap menunggu kesempatan UAT. Excel Jurnal sudah dinyatakan cukup bagus pada 4 November 2026.
2. Tentukan domain, server, web server/reverse proxy dan lokasi penyimpanan staging.
3. Terapkan template konfigurasi di server staging dan selesaikan semua pemeriksaan wajib.
4. Uji pemulihan SQL + ZIP foto pada lingkungan staging terpisah.
5. Uji kapasitas data lokal setara satu angkatan sudah lulus. Jalankan load test HTTP bersamaan pada infrastruktur staging, lalu susun keputusan kesiapan produksi dan backup terjadwal.

