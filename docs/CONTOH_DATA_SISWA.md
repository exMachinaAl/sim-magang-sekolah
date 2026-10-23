# Contoh Data Siswa dan Kontrol PKL

Pembaruan: `DatabaseSeeder` utama kini menyediakan 30 siswa dengan akun, tag, jadwal, 18 penempatan, dan contoh penilaian/sertifikat. Lihat [data demo terintegrasi](TAG_PKL_DAN_PENEMPATAN.md). Seeder 32 siswa di bawah tetap opsional untuk menambah data uji terpisah, tidak diperlukan setelah fresh seed utama.

## Seeder terpisah (disarankan untuk pengujian lokal)

Jalankan dari direktori aplikasi:

```powershell
php artisan db:seed --class=StudentPklDemoSeeder
```

- Menambah maksimal 32 siswa fiktif: empat siswa pada masing-masing delapan jurusan resmi.
- NIS `DEMO-PKL-001` sampai `DEMO-PKL-032`; login memakai NIS, password awal `07112008` (tanggal lahir contoh 2008-11-07).
- Setiap siswa memiliki akun, profil, dan roster tahun ajaran berjalan. Contoh meliputi sedang PKL, terjadwal, masuk sekolah, dan belum dijadwalkan.
- Tidak menambah industri, penempatan lama, nilai, atau sertifikat palsu.
- Hanya boleh berjalan pada `APP_ENV=local/testing`. Menjalankan ulang melewati NIS yang sudah ada, tanpa mengubah password atau jadwalnya.
- Tidak dipanggil otomatis oleh DatabaseSeeder. Tidak perlu `migrate:fresh`, dan jangan jalankan ulang DatabaseSeeder lama untuk contoh ini.
- Seeder belum otomatis dijalankan ke database pengguna. Jalankan perintah di atas hanya jika ingin menambahkan contoh di lingkungan lokal.

## Template CSV

Buka **Data Siswa → Impor Data Siswa Massal**. Unduhan utama langsung memuat delapan baris contoh (satu per jurusan); dua baris ditampilkan di halaman sebagai panduan. Tersedia juga template kosong opsional dan template jadwal dengan contoh. Ganti data contoh sebelum dipakai untuk data asli.

## Prompt alternatif untuk ChatGPT

```text
Buat 30 baris data siswa FIKTIF untuk menguji aplikasi PKL, bukan identitas orang sungguhan.
Keluarkan hanya CSV valid (pemisah koma, kutip field yang mengandung koma), tanpa markdown.
Header persis:
tahun_ajaran,nis,nisn,nama,tanggal_lahir,kelas,jurusan,email,catatan

Aturan:
- Tahun ajaran 2026/2027 (ganti bila diperlukan).
- NIS unik TEST-PKL-001 sampai TEST-PKL-030. NISN dan email kosong.
- Nama fiktif, tanggal lahir valid 2008-2009 format YYYY-MM-DD.
- Kelas XII sesuai singkatan jurusan. Catatan selalu DATA UJI FIKTIF.
- Sebarkan siswa ke semua delapan jurusan berikut, ejaan persis:
Teknik Instalasi Tenaga Listrik
Teknik Kimia Industri
Desain Komunikasi Visual
Analisis Pengujian Laboratorium
Kuliner
Teknik Komputer dan Jaringan
Teknik Pemanasan, Tata Udara, dan Pendinginan
Teknik Otomasi Industri
- Jangan menambahkan kolom password, status, atau tanggal PKL. Jadwal akan diatur koordinator lewat batch.
- Kombinasi NIS dan tahun ajaran tidak boleh duplikat.
```

CSV ini diimpor dari Data Siswa. Setelah berhasil, buka Kontrol PKL: pilih card jurusan atau cari nama/NIS dari halaman awal. Pengaturan batch hanya memuat daftar siswa setelah tombol Atur Jadwal Batch dibuka.
