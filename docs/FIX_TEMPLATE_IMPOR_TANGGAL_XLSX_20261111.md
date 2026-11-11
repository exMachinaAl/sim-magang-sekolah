# Perbaikan format tanggal template impor siswa — 11 November 2026

Status: **LULUS OTOMATIS**. Menunggu koreksi pengguna pada Microsoft Excel/LibreOffice yang digunakan sekolah.

## Masalah sebelumnya

Template menggunakan CSV dengan nilai contoh `YYYY-MM-DD`. Isi file sebenarnya benar, tetapi Excel dapat menafsirkan tanggal mengikuti locale komputer lalu menampilkan atau menyimpan ulang tanggal sebagai `DD/MM/YYYY` atau `MM/DD/YYYY`. Akibatnya contoh terlihat terbalik dan file hasil edit dapat ditolak importer.

## Perubahan

- Template siswa, template kosong, dan template siswa + jadwal sekarang diunduh sebagai XLSX.
- Kolom `tanggal_lahir`, `mulai_pkl`, dan `selesai_pkl` diformat sebagai teks dan berisi contoh `YYYY-MM-DD`.
- Kolom NIS/NISN juga berupa teks agar nol awal tidak hilang.
- File XLSX dapat langsung diunggah tanpa konversi ke CSV.
- Importer tetap menerima CSV lama untuk kompatibilitas.
- Sel tanggal Excel yang benar-benar tersimpan sebagai tipe tanggal/serial dinormalisasi ke `YYYY-MM-DD` saat dibaca.
- Workbook mempunyai sheet Petunjuk dan sheet data pertama tetap menjadi satu-satunya sumber impor.

Tidak ada perubahan database, migrasi, seeder, atau data siswa.

Pengujian akhir: **201 tes lulus dengan 3.414 assertion**. Pengujian mencakup round-trip template siswa, template jadwal, CSV lama, format teks `YYYY-MM-DD`, nol awal NIS, serta sel tanggal numerik asli dari Excel.

## Uji manual pengguna

1. Login koordinator dan buka **Data Siswa → Impor Data Siswa Massal**.
2. Unduh **Template XLSX + Contoh 8 Siswa**.
3. Buka file dan pastikan `tanggal_lahir` terlihat seperti `2008-11-07`, bukan `07/11/2008` atau `11/07/2008`.
4. Unduh **Template XLSX Jadwal + Contoh** dan pastikan `mulai_pkl`/`selesai_pkl` terlihat seperti `2026-07-01`/`2026-12-31` (tahun mengikuti tag yang dipilih).
5. Ganti satu baris contoh dengan data uji, simpan sebagai XLSX, lalu unggah file yang sama tanpa Save As CSV.
6. Pastikan siswa, tanggal lahir, dan jadwal tersimpan sesuai angka pada file.
7. Opsional: unggah CSV lama untuk memastikan kompatibilitas tetap berjalan.

## Format koreksi

```text
Upgrade: Template impor tanggal XLSX
Skenario: [nomor]
Status: LULUS / GAGAL
Hasil aktual: [tanggal yang terlihat/tersimpan]
Harapan: YYYY-MM-DD
Aplikasi: Microsoft Excel / LibreOffice + versinya
```
