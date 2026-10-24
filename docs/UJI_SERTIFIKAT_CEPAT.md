# Uji sertifikat tanpa menyiapkan data ulang

Pembaruan: contoh sekarang memiliki **DOCX depan dan belakang** pada detail sertifikat, tanpa LibreOffice. [Cara cetak dan batas layout Word](SERTIFIKAT_WORD_ARSIP.md). PDF lama tidak berubah.

Contoh sudah dipasang pada database lokal, 24 Oktober 2026. Bukan data/dokumen resmi.

1. Masuk sebagai `koordinator@simmag.test` dengan password akun Anda.
2. Buka **Sertifikat**. Pada card **Contoh siap diuji**, klik **1. Buka contoh & unduh PDF**.
3. Klik **Unduh Sertifikat + Nilai PDF**. PDF langsung tersedia; tidak memerlukan LibreOffice atau persetujuan awal tambahan untuk contoh fiktif ini.
4. Periksa halaman depan (identitas, tanggal, dua penandatangan) dan belakang (aspek serta nilai). Nilai contoh: rata-rata 84,00; laporan 90,00; hasil hitung 85,20; nilai resmi 85,00.

Untuk mencoba revisi: kembali ke Sertifikat → **2. Coba revisi contoh**. Data sudah terisi. Ubah tempat lahir atau bidang pekerjaan, isi alasan, ajukan versi baru. Klik **Buka detail & persetujuan**, periksa lalu setujui. Unduh versi terbaru dan bandingkan dengan arsip versi lama. Jangan mengubah nilai disahkan melalui formulir sertifikat.

Jika ada masalah, cukup kirim **langkah terakhir + hasil yang diharapkan + screenshot/pesan error**. Evaluasi contoh ini dahulu sebelum melanjutkan pengembangan.

## Data dan batas

- Nama: **Siswa Uji Sertifikat (DEMO)**; NIS: **DEMO-SERT-001**; jurusan Kuliner.
- Satu siswa, akun, roster, penempatan tanpa guru, ketentuan, nilai, template PDF bawaan, dan sertifikat fiktif siap cetak ditambahkan. Jumlah siswa lokal menjadi 31, termasuk contoh ini. Tidak ada data lama yang ditimpa.
- Seeder khusus: `php artisan db:seed --class=CertificatePreviewDemoSeeder`. Hanya local/testing. Jika contoh sudah ada, tidak mereset password, perubahan, status, atau arsip. Benturan identitas dengan data lain ditolak.
- Seeder utama tidak otomatis memanggil seeder ini. Tidak ada migrasi baru atau fresh pada tahap ini.
- Backup sebelum penambahan: `storage/app/private/backups/before-pkl-tags-20261024-111642.sql` (104882 byte).
- RED FLAG tetap menyimpan catatan masalah yang belum selesai, tetapi tidak mengunci PDF bawaan. Pesan halaman kini membedakan PDF bawaan yang tersedia, DOCX yang menunggu LibreOffice, dan arsip lama yang perlu pemeriksaan. Tidak ada bypass validasi untuk dokumen nyata.
- Tombol contoh hanya tampil bagi koordinator pada local/testing. Data contoh tetap berlabel DEMO; jangan bawa database demo ke produksi.

Verifikasi: 121 tes / 2.196 assertion lulus. Browser Edge dengan database terisolasi lulus membuka contoh dari menu utama Sertifikat, mengunduh PDF langsung, dan membuka revisi dengan data terisi; regresi kiosk/sertifikat/Timeline/Monitoring juga lulus. Artefak browser: `C:/Users/jorda/AppData/Local/Temp/simmag-journal-smoke-YSRj4i`. Pemeriksaan JSON baca-saja pada MySQL lokal lulus untuk tiga sumber sertifikat termasuk demo baru. Tes memastikan data lama tidak berubah dan seeder ulang tidak mereset hasil pengujian pengguna.
