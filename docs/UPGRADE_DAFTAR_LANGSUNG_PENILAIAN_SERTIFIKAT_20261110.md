# Upgrade Daftar Langsung Penilaian dan Sertifikat

Tanggal: 10 November 2026

## Perubahan

### Sebelum

- Kandidat siswa pada menu Penilaian dan Sertifikat disembunyikan sampai koordinator mengetik nama atau NIS.
- Pengguna dapat mengira belum ada data ketika kolom pencarian masih kosong.

### Sesudah

- Kandidat langsung tampil maksimal 10 siswa per halaman ketika menu dibuka.
- Nama/NIS tetap tersedia sebagai pencarian lanjutan.
- Filter periode dan jurusan tetap membatasi kandidat serta rekap.
- Menu Sertifikat tetap hanya menampilkan penempatan yang belum memiliki sertifikat.
- Tidak ada dropdown massal siswa; koordinator membuka tindakan dari baris siswa yang dipilih.
- Role selain koordinator tetap tidak menerima daftar kandidat penginputan.

Tidak ada migrasi atau perubahan data pada upgrade ini.

## Pengujian otomatis

Jalankan:

```powershell
php artisan test --compact tests/Feature/AssessmentTemplateTest.php tests/Feature/AssessmentAuditFixTest.php tests/Feature/CertificateArchiveTest.php
php artisan test --compact
```

Pengujian memeriksa daftar awal, pencarian nama/NIS, paginasi 10 kandidat, filter, ketiadaan selector siswa massal, dan pembatasan akses role.

## Pengujian manual

1. Login sebagai koordinator PKL.
2. Buka **Penilaian** tanpa mengisi pencarian. Pastikan siswa periode aktif langsung terlihat.
3. Gunakan **Nama atau NIS**, lalu pastikan daftar kandidat dan rekap menyempit ke siswa yang sesuai.
4. Tekan **Reset** dan pastikan daftar awal muncul kembali.
5. Uji filter tahun dan jurusan, termasuk memilih periode arsip secara sengaja.
6. Buka **Sertifikat** tanpa mengisi pencarian. Pastikan kandidat tanpa sertifikat langsung terlihat.
7. Cari nama/NIS siswa dan pastikan hasil menyempit. Siswa yang sudah memiliki sertifikat tidak boleh muncul sebagai kandidat baru.
8. Login sebagai siswa atau guru. Pastikan daftar kandidat input Penilaian dan pengajuan Sertifikat tidak ditampilkan.

## Roadmap berikutnya

- Koreksi hasil uji tampilan dari koordinator, khususnya kepadatan daftar pada layar ponsel.
- Jika jumlah siswa nyata membutuhkan lebih banyak kontrol, evaluasi urutan kandidat tanpa mengubah aturan akses atau data.
