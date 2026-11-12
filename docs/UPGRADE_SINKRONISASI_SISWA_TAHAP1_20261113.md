# Upgrade Sinkronisasi Siswa — Tahap 1

## Perubahan

- NIS asli tetap disimpan utuh pada data siswa, roster, sertifikat, import, dan export.
- Akun siswa memiliki `username` terpisah yang dibentuk dari seluruh angka NIS.
- Contoh: `12816/596.030` menghasilkan username `12816596030`.
- NIS berformat lama tetap dapat diketik pada halaman login karena input dinormalisasi sebelum autentikasi.
- Dua NIS berbeda yang menghasilkan username sama ditolak agar akun tidak tertukar.
- Migrasi melakukan pemeriksaan konflik sebelum menambahkan dan mengisi username akun lama.

## Pengujian otomatis

Jalankan:

```powershell
php artisan test tests/Feature/StudentEnrollmentTest.php tests/Feature/StudentDirectoryTest.php
```

## Pengujian manual

1. Masuk sebagai koordinator dan buka **Data Siswa**.
2. Tambahkan siswa dengan NIS `12816/596.030` dan tanggal lahir yang valid.
3. Pastikan daftar siswa menampilkan NIS asli serta username `12816596030`.
4. Keluar dan login sebagai siswa memakai username `12816596030`.
5. Pastikan profil, export, dan sertifikat tetap menampilkan NIS `12816/596.030`.
6. Coba membuat siswa lain dengan NIS yang normalisasinya juga `12816596030`; sistem harus menolak tanpa membuat akun atau roster.

## Tahap berikutnya

- Mengubah import menjadi merge hanya untuk sel berisi dan mempertahankan nilai lama ketika sel kosong.
- Menambahkan pratinjau konflik sebelum data diterapkan.
- Setelah fondasi merge stabil, menambahkan beberapa tahap/riwayat PKL untuk satu siswa.
