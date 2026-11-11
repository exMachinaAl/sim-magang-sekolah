# Upgrade Setoran Nilai Siswa dan Daftar Sertifikat

Tanggal: 11 November 2026

## Prinsip kompatibilitas

Kriteria setoran siswa berasal dari template penilaian resmi untuk jurusan dan penempatan tersebut. Sistem menyimpan snapshot kriteria bersama setoran. Sertifikat tidak membaca setoran siswa secara langsung; sertifikat hanya membaca penilaian resmi yang telah diterapkan dan disahkan koordinator.

Jika template setoran sudah tidak aktif atau berbeda dari penilaian yang telah dibuat, sistem tidak menerapkannya otomatis. Koordinator tetap dapat membaca foto/jurnal fisik dan memasukkan nilai secara manual.

## Alur baru

1. Siswa membuka **Penilaian**.
2. Sistem menampilkan kriteria yang sesuai dengan jurusan/penempatan siswa.
3. Siswa menyalin nilai dari lembar industri, mengisi nama penilai, tanggal, nilai akhir, catatan, dan foto JPG/PNG opsional.
4. Setoran disimpan sebagai **Menunggu diterapkan** dan belum menjadi nilai resmi.
5. Koordinator membuka Penilaian dan melihat antrean **Setoran nilai dari siswa**.
6. Koordinator memeriksa seluruh kriteria dan foto sumber, lalu memilih **Terapkan ke form penilaian**.
7. Form resmi diprefill. Koordinator boleh mengoreksi nilainya berdasarkan bukti atau jurnal fisik.
8. Saat form disimpan, setoran ditandai telah diterapkan dalam transaksi yang sama. ID siswa, koordinator, waktu, dan audit dipertahankan.
9. Pengesahan nilai dan pembuatan sertifikat tetap menggunakan alur resmi sebelumnya.

Setoran baru menggantikan status setoran lama yang belum diterapkan, tetapi setoran lama tidak dihapus. Foto tidak dimuat pada halaman daftar sehingga daftar tidak membawa data gambar besar.

## Perapian daftar sertifikat

- Kandidat pengajuan ditampilkan sebagai tabel siswa/NIS, kelas/jurusan, tahun/industri, kesiapan nilai, dan tindakan.
- Siswa dengan nilai disahkan memperoleh tombol **Siapkan Sertifikat**.
- Siswa yang belum siap diarahkan kembali ke **Penilaian**.
- Style card, tabel, pill, dan tombol lama tetap digunakan.

## Uji manual siswa

1. Login sebagai siswa yang sudah memiliki penempatan.
2. Buka **Penilaian** dan periksa kriteria sesuai jurusan.
3. Isi semua kriteria, nama penilai, tanggal dan nilai akhir; unggah foto opsional.
4. Kirim dan pastikan status menjadi **Menunggu diterapkan**.
5. Coba login siswa lain dan pastikan foto tersebut tidak dapat diakses.

## Uji manual koordinator

1. Buka **Penilaian** dan cari bagian **Setoran nilai dari siswa**.
2. Buka detail setoran dan foto sumber.
3. Tekan **Terapkan ke form penilaian**; pastikan semua field terisi sesuai setoran.
4. Koreksi bila diperlukan, lalu simpan draf atau ajukan validasi.
5. Buka halaman Bukti dan pastikan setoran yang diterapkan serta foto sumber masih dapat ditelusuri.
6. Sahkan nilai, lalu buka **Sertifikat** dan pastikan tabel kandidat menampilkan kesiapan nilai dengan jelas.

## Pengujian otomatis

```powershell
php artisan test --compact tests/Feature/StudentAssessmentSubmissionTest.php
php artisan test --compact
```

Migrasi yang diperlukan bersifat aditif: `2026_11_11_000001_create_assessment_submissions`. Jangan memakai `migrate:fresh` pada database berisi data asli.
