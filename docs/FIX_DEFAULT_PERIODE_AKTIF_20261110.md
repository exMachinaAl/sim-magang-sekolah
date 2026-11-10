# Perbaikan default tag periode aktif — 10 November 2026

## Masalah

Setelah tag periode diarsipkan, beberapa menu masih dapat memilihnya sebagai default karena memakai tahun kalender saat ini, record pertama, `latest()`, atau tidak memilih periode sama sekali. Akibatnya pengguna dapat mengira sedang mengelola tahun terbaru padahal halaman membuka data historis.

## Aturan baru

- Tanpa parameter pengguna, menu memilih tag **aktif** dengan `academic_year` tertinggi yang tersedia dalam ruang lingkup role tersebut.
- Tag arsip tetap ditampilkan dan diberi label `(arsip)`.
- Tag arsip hanya dibuka bila pengguna memilihnya secara eksplisit atau mengikuti tautan data historis yang memang membawa periode tersebut.
- Pilihan **Semua tahun/periode** tetap tersedia pada menu yang sebelumnya memilikinya. Pengguna harus memilih opsi itu secara eksplisit.
- Bila tidak ada tag aktif, sistem tidak diam-diam memilih arsip. Menu menampilkan petunjuk `Tidak ada tag aktif — pilih arsip bila diperlukan`.
- Pengarsipan tidak menghapus atau mengubah roster, jadwal, penempatan, jurnal, nilai, sertifikat, maupun audit lama.

## Menu yang diselaraskan

1. Dashboard.
2. Timeline PKL.
3. Kontrol PKL.
4. Penempatan.
5. Jurnal dan rekap PDF jurnal.
6. Monitoring.
7. Penilaian.
8. Sertifikat.
9. Laporan.
10. Data Siswa untuk tahun pendaftaran baru.

Pemilihan default menggunakan satu aturan pada model `InternshipPeriod`, sehingga controller tidak lagi menentukan default dengan cara berbeda-beda.

## Pengujian otomatis

- Tes khusus periode: **3 tes, 63 assertion lulus**.
- Regresi modul terdampak: **86 tes, 2.075 assertion lulus**.
- Regresi penuh aplikasi: **195 tes, 3.290 assertion lulus**.
- Tes khusus membuat tag arsip yang tahunnya lebih baru daripada tag aktif, lalu memastikan seluruh menu tetap memilih tag aktif.
- Tes juga memastikan pilihan arsip eksplisit dipertahankan dan tidak ada arsip yang terpilih otomatis ketika seluruh tag diarsipkan.

## Pengujian manual

1. Buat dua tag, misalnya `2026/2027` dan `2027/2028`, serta pastikan keduanya mempunyai contoh roster/penempatan.
2. Arsipkan `2027/2028` dan biarkan `2026/2027` aktif.
3. Buka setiap menu pada daftar di atas tanpa query/filter lama. Default harus `2026/2027`.
4. Buka dropdown dan pastikan `2027/2028 (arsip)` masih tersedia.
5. Pilih `2027/2028 (arsip)`. Data historis periode itu harus tampil dan pilihan tidak dialihkan kembali.
6. Kembali lewat menu sidebar atau tombol Reset. Default harus kembali ke tag aktif terbaru.
7. Pilih **Semua tahun/periode** pada Jurnal, Monitoring, Penilaian, atau Sertifikat. Pastikan gabungan tahun tampil hanya setelah pilihan eksplisit tersebut.
8. Arsipkan seluruh tag pada lingkungan uji. Menu tidak boleh otomatis memilih salah satu arsip dan harus menampilkan petunjuk tidak ada tag aktif.
9. Aktifkan kembali tag terbaru dan muat ulang; tag tersebut harus kembali menjadi default.

Tidak ada migration, `migrate:fresh`, seeder ulang, atau perubahan data pada perbaikan ini.

