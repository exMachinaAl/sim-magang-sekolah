# SIMMAG — Sistem Informasi Manajemen Magang

**SMKN 1 Cerme Gresik** · Prototipe fungsional Laravel 12 + Blade + CSS + MySQL.

Aplikasi ini mengikuti dokumen **PRD WEBSITE SIMMAG** yang dilampirkan. Stack awal React/Vite/Express/PostgreSQL dalam PRD diganti dengan Laravel/Blade/MySQL agar dapat dijalankan melalui Laragon. Tidak membutuhkan Node.js, npm, atau Vite untuk menjalankan tampilan dalam versi ini.

> **Status**: kode sumber MVP untuk uji coba lokal. Dependensi Composer (`vendor/`) tidak dibundel. Jalankan instalasi dan pengujian di komputer Windows/Laragon sebelum dipakai dengan data sekolah sungguhan. Belum diaudit untuk deployment produksi.

## Mulai cepat di Windows + Laragon

Lihat **[PANDUAN_LARAGON.md](PANDUAN_LARAGON.md)** untuk langkah instalasi yang lebih detail. Ringkasannya:

1. Ekstrak folder `simmag-laravel` ke `C:\laragon\www\simmag-laravel`, lalu buka **Terminal Laragon** pada folder tersebut.
2. Pastikan PHP 8.2+ dan Composer sudah tersedia. Nyalakan layanan MySQL di Laragon.
3. Jalankan SQL `CREATE DATABASE simmag CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` dari HeidiSQL/phpMyAdmin.
4. Jalankan perintah di bawah ini dari direktori `simmag-laravel`.

```bat
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Buka **http://127.0.0.1:8000**. Untuk menjalankan ulang esok hari cukup menyalakan MySQL, masuk ke folder proyek, lalu `php artisan serve`.

> `php artisan migrate:fresh --seed` **menghapus seluruh tabel** pada database yang dipilih! Gunakan hanya untuk reset data demo di database uji coba, jangan di database produksi.

## Akun demo

Semua akun demo memiliki kata sandi **`Demo123456!`** dan **hanya** dibuat oleh seeder ketika `APP_ENV=local` atau `testing`.

| Role | Email |
|---|---|
| Admin | `admin@simmag.test` |
| Koordinator PKL | `koordinator@simmag.test` |
| Guru pembimbing A | `guru@simmag.test` |
| Guru pembimbing B | `guru2@simmag.test` |
| Siswa A | `siswa@simmag.test` |
| Siswa B | `siswa2@simmag.test` |
| Siswa C | `siswa3@simmag.test` |
| Siswa D | `siswa4@simmag.test` |

Nama, perusahaan, dan foto dalam seeder seluruhnya adalah **data fiktif**. Password demo wajib diganti atau akun demo dihapus sebelum deployment ke lingkungan nyata.

## Fitur yang tersedia

| Area | Implementasi |
|---|---|
| Login | Autentikasi session Laravel, rate limit login, hashing password, ubah password, penonaktifan akun |
| Role | Admin sistem, koordinator PKL, guru pembimbing, dan siswa |
| Dashboard | Statistik dan aktivitas terbatas sesuai role |
| Master Data | Akun, data siswa, mitra perusahaan, periode, penempatan dan pembimbing |
| Kontrol PKL | Roster seluruh siswa, counter sekolah/per jurusan, batch tanggal lintas jurusan, pengecualian checkbox, serta impor/ekspor CSV |
| Timeline | Navigasi bertingkat pembimbing → siswa → detail, pencarian nama/NIP/NIS, dan fase terjadwal/PKL/masuk sekolah yang dihitung otomatis dari tanggal |
| Jurnal | Rekap satu baris per penempatan, detail kegiatan, timeline revisi/status, maksimal total 5 foto per jurnal, filter, dan ekspor PDF |
| Foto | Penyimpanan privat; endpoint foto memeriksa hak akses penempatan |
| Monitoring | Catatan kunjungan guru sesuai daftar siswa bimbingan |
| Penilaian | Koordinator membuat template aspek, uraian ketentuan, isian angka/teks dan bobot per jurusan; menginput nilai industri dan mengesahkan nilai akhir resmi |
| Sertifikat | Syarat jurnal dan penilaian, nomor unik, persetujuan koordinator, riwayat keputusan, PDF dua halaman dengan nilai di sisi belakang |
| Distribusi | Tracking cetak, pengiriman perusahaan, pengembalian, jadwal pengambilan, serah terima hingga selesai |
| Laporan | Unduh CSV penempatan/jurnal/penilaian/sertifikat, audit log aktivitas |
| Panduan | Tutorial pengisian jurnal dan penggunaan fitur umum |

**Akses sensitif diperiksa di backend** melalui middleware role dan `App\Support\Access`, bukan hanya dengan menyembunyikan menu. Admin sistem berfungsi sebagai pengelola akun dan viewer; kewenangan operasional PKL berada pada koordinator dan guru pembimbing.

### Contoh alur uji dari data demo

1. Masuk sebagai **`siswa3@simmag.test`** untuk melihat jurnal berstatus *menunggu review* dan *perlu revisi*. Edit jurnal revisi dengan mengisi tanggal yang sama, lalu kirim kembali.
2. Masuk sebagai **`guru@simmag.test`** untuk melihat siswa A dan C, meninjau jurnal C, dan membuat catatan monitoring. Guru B tidak dapat memeriksa siswa A/C.
3. Masuk sebagai **`koordinator@simmag.test`**, buka Penilaian → Kelola Template Ketentuan. Buat template jurusan, pilih siswa dan template, lalu salin nilai dari industri. Guru hanya melihat penilaian siswa bimbingannya.
4. Masuk sebagai **`koordinator@simmag.test`** untuk memvalidasi nilai, menyetujui sertifikat, mengelola master data, dan mengunduh output sertifikat.
5. Tetap sebagai **`koordinator@simmag.test`** untuk memproses cetak, distribusi, dan penyerahan sertifikat. Siswa B memiliki contoh sertifikat *siap diambil* dan dapat melihat jadwalnya.
6. Masuk sebagai **`admin@simmag.test`** untuk mengelola akun serta memantau data sebagai viewer.

### Timeline PKL berbasis tanggal

Koordinator menetapkan tanggal mulai dan selesai secara batch untuk satu, beberapa, atau seluruh jurusan. Siswa dapat dikecualikan dengan melepas checkbox. Tanggal selesai bersifat inklusif dan status berubah menjadi `masuk sekolah` pada hari berikutnya. Guru dan siswa hanya melihat informasi; tidak ada konfirmasi mulai atau selesai secara manual.

### Roster dan status magang

Menu **Kontrol PKL** menyimpan populasi seluruh siswa tanpa harus membuat akun login. Keputusan keikutsertaan (`belum ditentukan`, `magang`, atau `tidak magang`) dipisahkan dari fase berbasis tanggal (`belum dijadwalkan`, `terjadwal`, `sedang PKL`, atau `masuk sekolah`). Jadwal individual dilindungi dari penimpaan batch.

Template CSV dapat diunduh dari halaman fitur. Kolom wajibnya adalah `nis`, `nama`, `kelas`, dan `jurusan`; kolom tanggal opsional adalah `mulai_pkl` dan `selesai_pkl` dengan format `YYYY-MM-DD` serta wajib diisi berpasangan. Impor ulang kombinasi tahun ajaran dan NIS yang sama memperbarui record yang sudah ada.

## Struktur direktori penting

```text
app/
  Http/Controllers/       # Logika halaman & aksi
  Http/Middleware/        # Role dan pemeriksaan akun aktif
  Models/                 # Entity database
  Support/Access.php      # Scoping siswa/pembimbing/perusahaan
  Support/Audit.php       # Pencatatan aksi penting
database/
  migrations/             # Relasi tabel dan indeks
  seeders/DatabaseSeeder.php
  seeders/assets/demo-jurnal.png
resources/views/          # HTML Blade semua halaman
public/css/simmag.css     # Styling responsif, tanpa npm
routes/web.php            # Semua route HTTP
tests/Feature/            # Pengujian izin akses dan alur sertifikat
```

## Keamanan dan batasan

- Semua unggahan foto pada disk `local` disimpan di `storage/app/private/`, **bukan** direktori publik.
- File foto hanya ditampilkan setelah login dan diperiksa apakah pengguna berhak mengakses penempatan terkait.
- Form mengaktifkan token CSRF, query menggunakan ORM, dan password di-hash via cast `hashed`.
- Perubahan penempatan yang memiliki jurnal/penilaian/sertifikat dibatasi agar dokumen lama tidak tiba-tiba menunjuk pembimbing atau perusahaan lain.
- Pemeriksaan status aktif dilakukan setiap mengakses halaman terotentikasi (bukan hanya saat login).
- Dokumen **PDF sertifikat bukan tanda tangan elektronik tersertifikasi**. Template sementara menyediakan posisi tanda tangan/stempel; template resmi dan metode TTE akan diterapkan setelah ditetapkan sekolah.
- Jurnal mensyaratkan minimal 1 foto untuk dikirim pada versi MVP; pengecualian foto per jenis kegiatan belum tersedia.
- Edit bobot penilaian hanya dapat dilakukan sebelum ada penilaian, agar hasil nilai lama tidak berubah makna.
- QR verifikasi sertifikat, template kustom, notifikasi email otomatis, SSO, cloud object storage/Google Drive, sistem kehadiran terpisah, tanda terima foto, dan backup terjadwal belum diimplementasikan.
- PDF jurnal dibatasi maksimal 300 baris per ekspor untuk menjaga memori; gunakan filter tanggal/status untuk dataset lebih besar.

## Rancangan data dan penelusuran

Inti relasi: `users` → `students` → `placements` → (`journals` + `journal_attachments` + `journal_events`, `assessments` + `assessment_scores`, `certificates` + `certificate_approvals` + `certificate_approval_events` + `certificate_movements`), dengan `companies`, `internship_periods`, `timeline_events`, `monitoring_records`, `audit_logs`, dan `assessment_criteria`.

Untuk mengembangkan aplikasi ke tahap produksi, validasi dahulu bersama sekolah: aturan periode per gelombang, bobot penilaian, template sertifikat resmi, metode tanda tangan/stempel, persyaratan sertifikat, masa retensi dokumen, penyedia storage dan backup, serta kebijakan privasi siswa.

## Pengujian

Jalankan `php artisan test` setelah Composer selesai diinstal dan ekstensi SQLite diaktifkan pada PHP yang dipakai Composer/Artisan. Berkas pengujian menggunakan SQLite in-memory (terpisah dari MySQL demo), menguji otorisasi antarguru, akses foto, siswa lintas penempatan, penonaktifan akun, validasi nilai, pembatasan admin, dan persetujuan sertifikat oleh koordinator.

Kriteria kelulusan juga perlu dites secara manual lewat seluruh akun demo dan beberapa berkas foto asli format JPG/PNG. Jangan unggah informasi nyata atau rahasia mitra sebelum kebijakan penyimpanan ditetapkan.
# sim-magang-sekolah
