# Laporan seluruh roster — 24 Oktober 2026

Red flag Laporan & Audit ditutup: ekspor sekolah kini mencakup seluruh roster tahun terpilih, termasuk siswa tanpa penempatan dan yang tidak magang. Ekspor penempatan tetap berisi penempatan saja; tidak membuat penempatan fiktif untuk melengkapi laporan.

## Coba langsung

1. Login koordinator, buka **Laporan & Audit**.
2. Pilih tag tahun ajaran dan jurusan (atau seluruh jurusan), lalu **Terapkan filter**.
3. Klik **Seluruh Siswa / Roster**. CSV memuat NIS/NISN, nama, kelas/jurusan historis, tanggal mulai/selesai, fase PKL, status penempatan, industri, dan guru opsional.
4. Coba jurusan lain. Angka roster/penempatan di halaman serta lima tombol ekspor mengikuti filter yang telah diterapkan.

Pada database kerja saat pemeriksaan: tahun 2026/2027 memiliki **31 roster dan 19 penempatan**, dengan 12 siswa belum ditempatkan. Tidak ada penambahan siswa atau perubahan jadwal pada tahap ini.

## Aturan ekspor

- Satu baris per roster siswa/tahun. Tidak membutuhkan akun atau industri untuk muncul di ekspor roster.
- Default tag tahun berjalan WIB bila tersedia, jika tidak tahun terbaru. Tahun kosong menghasilkan CSV dengan header, bukan data dari tahun lain. Filter jurusan yang tidak cocok menghasilkan data kosong.
- Semua ekspor mengikuti tag tahun/jurusan. Jurnal, nilai, dan sertifikat tetap berisi catatan yang benar-benar tersedia pada penempatan. CSV nilai merupakan rekap nilai akhir/status, bukan pengganti arsip sertifikat atau ekspor semua aspek penilaian.
- Kelas/jurusan berasal dari roster historis, tidak memakai profil terkini sebagai pengganti saat relasi historis hilang. Tautan rusak diberi keterangan; data industri dari penempatan siswa lain tidak disalin ke roster yang salah.
- Fase dihitung dari tanggal siswa pada hari ekspor dalam WIB, tanggal akhir masih termasuk PKL. Masuk sekolah adalah fase jadwal, bukan absensi. Tanggal rekap disertakan pada CSV roster/penempatan.
- NIS/NISN diberi awalan apostrof sebagai penanda teks. Untuk Excel, impor kolom tersebut sebagai **Text** jika aplikasi menampilkan penanda atau melakukan konversi otomatis. Jangan gunakan berkas ini sebagai template impor siswa.
- CSV ber-BOM UTF-8, nilai berpotensi formula dinetralkan, koma/kutip/baris baru di-escape, respons privat tanpa cache. Ekspor dibaca per 250 baris agar tidak memuat seluruh sekolah sekaligus. Batas ini bukan batas jumlah baris ekspor.
- Riwayat audit tetap seluruh tahun/jurusan, dengan paginasi yang mempertahankan filter ekspor. Akses laporan tetap khusus koordinator.
- Rekap adalah pembacaan data operasional saat ekspor, bukan snapshot transaksi untuk audit perubahan serentak. Uji beban/konkurensi sekolah nyata belum selesai.

## Verifikasi

**146 tes / 2.815 assertion lulus**, termasuk enam tes laporan baru: semua siswa, filter tiap jenis laporan lintas tahun/jurusan, identitas historis, fase WIB, akses peran, CSV multiline/formula/NIS nol awal, data kosong, tautan lama rusak, dan ekspor melewati batas chunk 250 baris.

Uji Edge headless lulus untuk unduh CSV seluruh siswa dan jurusan Kuliner, konsistensi filter, flag hilang, dan lebar HP 390px. Regresi Dashboard, QR, Monitoring, Penilaian dan Sertifikat juga lulus. Artefak data fiktif: `C:/Users/jorda/AppData/Local/Temp/simmag-journal-smoke-PqPrhD`, termasuk `reports-roster-mobile.png` dan `report-kuliner.csv`.

Pemeriksaan MySQL baca-saja mengonfirmasi jumlah pada halaman dan audit. Tidak ada migrasi, fresh/reset, atau reseed database kerja. Bukti browser memakai SQLite terisolasi, bukan bukti UAT kamera/cetak fisik.

Sisa kekurangan lintas modul ada pada [daftar setelah upgrade](KEKURANGAN_SETELAH_UPGRADE.md).
