# Upgrade filter periode siswa dan aksi massal akun — 11 November 2026

Status: **LULUS OTOMATIS** setelah suite pengujian akhir. Menunggu koreksi pengguna.

## Data Siswa

- Daftar default mengikuti periode/tag aktif terbaru.
- Koordinator dapat memilih periode aktif maupun periode arsip.
- Opsi **Semua Periode** menampilkan setiap akun siswa satu kali, bukan menggandakan siswa yang terdaftar pada beberapa tahun.
- Pada periode tertentu, kelas dan jurusan tabel berasal dari roster periode tersebut.
- Pada Semua Periode, tabel memakai roster terbaru dan menyediakan rincian riwayat periode.
- Parameter lama `academic_year` tetap diterima agar tautan lama tidak rusak.
- Form tambah/impor pada tampilan Semua Periode tetap memakai periode aktif terbaru sebagai default.
- Pencarian nama/NIS tetap mengikuti periode yang dipilih.

## Kelola Akun Admin

- Admin dapat memilih banyak akun menggunakan checkbox.
- Aksi massal pada daftar utama: Aktifkan, Nonaktifkan, dan Arsipkan/Sembunyikan.
- Aksi massal pada Arsip Akun: Pulihkan sebagai nonaktif.
- Akun Admin yang sedang login tidak dapat dicentang atau dimasukkan melalui request manual.
- Aksi massal bersifat atomik: bila satu pilihan tidak kompatibel, tidak ada akun yang berubah.
- Role lama tidak dapat diaktifkan/dinonaktifkan, tetapi tetap dapat diarsipkan.
- Koordinator tidak mendapat akses aksi massal akun.
- Setiap akun yang berubah menghasilkan audit tersendiri.

Tidak ada migrasi database baru untuk tahap ini. Mekanisme menggunakan kolom arsip akun yang sudah tersedia.

## Hasil pengujian otomatis

- `php artisan view:cache`: lulus.
- Pengujian terarah Data Siswa, periode, impor, arsip akun, dan pusat bantuan: **28 tes, 395 assertion, lulus**.
- Seluruh aplikasi dengan `php artisan test --compact`: **205 tes, 3.500 assertion, lulus**.

## Uji manual

### Filter siswa

1. Login Koordinator dan buka Data Siswa.
2. Pastikan periode aktif terbaru dipilih otomatis.
3. Pilih periode arsip dan pastikan hanya siswa roster periode itu yang tampil.
4. Pilih Semua Periode dan pastikan siswa lintas tahun tampil tanpa duplikasi akun.
5. Cari nama/NIS pada masing-masing pilihan periode.

### Aksi massal akun

1. Login Admin dan buka Pengguna & Role.
2. Centang dua akun uji, pilih Nonaktifkan, lalu terapkan.
3. Pilih Aktifkan dan pastikan keduanya aktif kembali.
4. Pilih Arsipkan/Sembunyikan dan pastikan keduanya pindah ke Arsip Akun.
5. Di Arsip Akun, centang keduanya dan pilih Pulihkan.
6. Pastikan keduanya kembali dalam status Nonaktif.
7. Pastikan checkbox akun Admin yang sedang login tidak dapat dipilih.

## Format koreksi

```text
Upgrade: Filter periode siswa / aksi massal akun
Skenario: [nomor]
Status: LULUS / GAGAL
Hasil aktual: [yang terlihat]
Harapan: [yang diharapkan]
```
