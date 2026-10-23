# Panduan menjalankan SIMMAG di Laragon (Windows)

## 1. Persiapan

Siapkan **Laragon** dengan PHP **8.2 atau lebih baru**, MySQL, Composer dan koneksi internet untuk mengunduh dependensi saat instalasi pertama. Ekstrak ZIP yang dikirim ke:

`C:\laragon\www\simmag-laravel`

Pastikan direktori tersebut berisi file `artisan` dan `composer.json`. **Jangan** menambahkan satu tingkat folder lagi tanpa sengaja (misalnya `simmag-laravel\simmag-laravel`).

Buka Laragon → **Start All** untuk menjalankan MySQL. Pilih **Terminal** di Laragon, kemudian:

```bat
cd C:\laragon\www\simmag-laravel
php -v
composer -V
```

Jika `composer` tidak dikenali, pasang Composer melalui installer resmi, lalu buka ulang terminal Laragon. Aktifkan ekstensi PHP standar yang diperlukan Laravel, termasuk `openssl`, `mbstring`, `pdo_mysql`, `fileinfo`, `xml`/`dom`, dan `zip` melalui konfigurasi PHP Laragon apabila installer Composer menyatakan ada ekstensi yang hilang.

## 2. Database MySQL

Buka Laragon → **Database** (HeidiSQL) atau phpMyAdmin. Jalankan SQL berikut:

```sql
CREATE DATABASE IF NOT EXISTS simmag
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Kredensial standar Laragon sering `root` dengan password kosong, namun gunakan yang sesuai instalasi Anda.

## 3. Instal dependensi dan atur `.env`

Di terminal folder proyek:

```bat
composer install
copy .env.example .env
php artisan key:generate
```

Buka `.env` dengan VS Code. Pastikan konfigurasi database sesuai:

```dotenv
APP_NAME=SIMMAG
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simmag
DB_USERNAME=root
DB_PASSWORD=
```

Jika MySQL berjalan pada port selain 3306, ubah `DB_PORT`.

## 4. Bangun tabel & isi contoh

```bat
php artisan config:clear
php artisan migrate --seed
```

Seeder membuat 12 akun dummy, 4 siswa, perusahaan fiktif, timeline, jurnal, penilaian, dan contoh sertifikat. Jika ingin membersihkan **database demo** dan membuat ulang dari nol, perintahnya `php artisan migrate:fresh --seed` (menghapus semua tabel dan data pada database tujuan!).

## 5. Jalankan

```bat
php artisan serve --host=127.0.0.1 --port=8000
```

Buka **http://127.0.0.1:8000** di browser. Login dengan `admin@simmag.test` dan password `Demo123456!`. Setelah itu masuk menggunakan role lain untuk menguji pembatasan menu dan data.

Untuk menghidupkan kembali di lain hari, cukup Start All di Laragon dan jalankan `php artisan serve` dari folder yang sama. Composer dan migrasi tidak perlu dijalankan ulang kecuali ada perubahan proyek.

## 6. Pengujian kode

```bat
php artisan route:list
php artisan test
```

Aktifkan ekstensi `pdo_sqlite` di PHP Laragon untuk menjalankan unit test berbasis database sementara. Test tidak menghapus database MySQL SIMMAG.

## 7. Jika muncul error

| Masalah | Penanganan |
|---|---|
| `vendor/autoload.php` not found | Pastikan `composer install` berhasil dan terminal berada di folder proyek. |
| `No application encryption key has been specified` | Jalankan `php artisan key:generate`. |
| `SQLSTATE... Unknown database 'simmag'` | Buat database MySQL `simmag`, cek `.env`, jalankan `php artisan config:clear`. |
| `could not find driver` | Aktifkan `pdo_mysql` pada PHP CLI Laragon (dan `pdo_sqlite` untuk test). |
| `Base table or view not found` | Jalankan `php artisan migrate --seed` pada database yang sesuai. |
| Error upload / PDF | Periksa izin folder `storage`, ekstensi `fileinfo`, `mbstring`, `dom`, serta keberhasilan instalasi `barryvdh/laravel-dompdf`. |
| Port 8000 sudah dipakai | Jalankan `php artisan serve --port=8001` dan sesuaikan `APP_URL`. |
| CSS tidak muncul | Akses alamat dari `php artisan serve`, pastikan file `public/css/simmag.css` ada. |

## 8. Catatan saat deployment sekolah

`php artisan serve` hanya untuk uji coba pengembangan. Bila akan dibuka dari jaringan sekolah/internet, gunakan Nginx atau Apache dengan **DocumentRoot mengarah ke folder `public`**, HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, akun asli dengan password kuat, penyimpanan privat, backup harian, dan pembatasan koneksi database. Jangan menaruh file `.env` dan kode backend di direktori yang dapat diakses langsung dari web.
