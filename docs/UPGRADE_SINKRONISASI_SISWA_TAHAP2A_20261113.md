# Upgrade Sinkronisasi Siswa — Tahap 2A

## Perubahan sebelum dan sesudah

Sebelumnya, beberapa kolom kosong pada impor ulang dapat mengosongkan nilai roster dan setiap baris lama selalu dicatat sebagai pembaruan.

Sekarang:

- sel kosong mempertahankan data lama;
- siswa baru tetap wajib memiliki nama, kelas, jurusan, dan tanggal lahir;
- satu tanggal jadwal boleh diperbarui jika pasangan tanggalnya sudah tersedia di database;
- nama berbeda untuk NIS yang sama ditahan sebagai konflik identitas;
- NISN tetap opsional, tetapi menjadi unik ketika diisi;
- benturan email dan username normalisasi diperiksa sebelum transaksi;
- baris yang benar-benar identik tidak mengubah `updated_at` dan tidak membuat audit impor baru;
- hasil impor membedakan jumlah baru, diperbarui, dan tidak berubah;
- identitas siswa, roster, periode, dan email dipreload per kelompok agar file besar tidak menimbulkan query per baris.

## Pengujian otomatis

```powershell
php artisan test tests/Feature/RosterImportUiTest.php tests/Feature/StudentEnrollmentTest.php tests/Feature/StudentDirectoryTest.php
```

## Pengujian manual

1. Import satu siswa beserta NIS berformat, NISN, email, catatan, dan jadwal lengkap.
2. Import ulang NIS yang sama dengan kolom identitas, kelas, jurusan, dan catatan kosong; isi hanya `selesai_pkl` baru.
3. Pastikan data lama tetap ada dan hanya tanggal selesai yang berubah.
4. Import kembali baris berisi NIS saja; pesan harus menyebut satu baris tidak berubah.
5. Coba isi nama berbeda pada NIS yang sama; seluruh file harus ditolak tanpa perubahan parsial.

## Belum termasuk tahap ini

- pratinjau visual sebelum menerapkan file;
- penanda eksplisit untuk mengosongkan nilai;
- beberapa tahap PKL pada satu tahun ajaran;
- pembacaan workbook sekolah multi-sheet dan kolom nilai V–Y/AB.
