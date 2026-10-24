# DOCX dari arsip sertifikat — 24 Oktober 2026

## Langsung mencoba

Masuk koordinator → Sertifikat → **Buka contoh & unduh PDF** → bagian **Word / DOCX** → **Unduh DOCX depan** dan **Unduh DOCX belakang**. Contoh `DEMO-SERT-001` sudah disiapkan di database lokal. Tidak perlu LibreOffice untuk mengunduh/mengisi Word.

Pada sertifikat lain yang sudah disahkan dan mempunyai identitas cetak lengkap, koordinator menekan **Siapkan DOCX dari arsip** terlebih dahulu. Penyiapan menggunakan POST ber-CSRF; GET unduhan hanya membaca arsip, tidak menerbitkan atau mengubah data.

## Isi dan layout

- Semua nilai, identitas, jadwal, serta penandatangan diambil dari snapshot versi yang dipilih, bukan profil/penilaian aktif. Nilai industri tidak diubah.
- Untuk format 80/20, kedua sisi memakai DOCX asli dari paket pengguna. Tautan mail merge lama dibersihkan; gaya, kotak foto, margin dan struktur sumber dipertahankan saat penggantian token.
- Untuk aspek bebas, depan memakai template asli; belakang dibuat sebagai tabel Word dinamis agar semua aspek angka/teks tercantum. Tidak memaksakan empat aspek atau membuang isian tambahan.
- Ini **salinan Word dengan layout Word**, bukan konversi PDF lama secara identik. PDF sebelumnya tetap utuh. Nama judul/narasi baku pada template asli mengikuti berkas asli; layout/narasi PDF bawaan tidak otomatis menjadi layout Word.
- Berkas depan/belakang terpisah. Buka di Word, periksa font tersedia, ukuran A4, margin, jumlah halaman dan arah balik kertas sebelum mencetak kedua sisi. Panjang teks dan font dapat menggeser halaman; jumlah halaman Word belum dapat dipastikan tanpa mesin render dan uji cetak.
- Mengedit DOCX di luar sistem tidak mengubah nilai/PDF arsip aplikasi. Jika isi resmi perlu dikoreksi, lakukan melalui revisi sumber dan versi sertifikat, bukan menganggap edit Word tersinkron otomatis.

## Arsip dan akses

Tabel baru `certificate_word_exports` menyimpan satu pasangan DOCX per `certificate_document_id`, hash kedua file, hash PDF sumber, hash template, jenis layout, ID koordinator, dan waktu penyiapan. Arsip sertifikat lama tidak diubah atau ditambah diam-diam. Pemanggilan ulang tidak meregenerasi pasangan Word yang sudah ada. Model menolak perubahan/penghapusan; unduhan memeriksa hash. Versi Word lama tetap dapat diunduh dari riwayat meskipun versi terbaru masih menunggu pengesahan.

Hanya koordinator dapat menyiapkan. Pengunduhan mengikuti hak akses sertifikat masing-masing pengguna. Dokumen belum disahkan ditolak; arsip lama tanpa `print` snapshot harus dilengkapi melalui revisi, bukan ditebak dari data hidup. Jika sudah ada DOCX asli dari proses konversi unggahan, berkas asli itu tetap digunakan—tidak ditimpa salinan baru.

## Perubahan lingkungan

Migrasi additive `2026_10_24_000004_create_certificate_word_exports` diterapkan setelah backup `storage/app/private/backups/before-pkl-tags-20261024-161736.sql` (1292444 byte). Tidak ada fresh/reseed umum. Seeder khusus `CertificateDemoWordSeeder` hanya menambahkan salinan Word pada contoh yang sudah ada dan sudah disahkan; tidak mengubah nilai, PDF, versi, status atau password. Pemanggilan ulang aman tanpa mereset hasil evaluasi.

LibreOffice tetap belum terpasang. **Mengisi/mengunduh DOCX dari arsip tidak memerlukannya**; **mengubah unggahan DOCX menjadi PDF dengan layout Word** tetap memerlukan LibreOffice dan pengujian terpisah. Flag sertifikat tetap menjelaskan pekerjaan tersebut, arsip lama, dan uji cetak fisik.

## Pengujian

Hasil: **125 tes / 2.244 assertion lulus**. Browser Edge headless dengan fixture terisolasi lulus mengunduh PDF dan kedua DOCX, termasuk pemeriksaan respons MIME/berkas ZIP Word. Regresi kiosk, sertifikat, Timeline, dan Monitoring tetap lulus. Artefak: `C:/Users/jorda/AppData/Local/Temp/simmag-journal-smoke-AgoFoK`. Penyiapan DOCX demo juga berhasil pada database kerja MySQL setelah migrasi tanpa reset.

Tes otomatis mencakup perubahan profil/nilai setelah terbit, arsip PDF tetap identik, salinan Word tidak berubah pada pemanggilan ulang, hash rusak, hak akses, penolakan dokumen belum disahkan/arsip tak lengkap, serta aspek teks bebas. Skenario HTTP dari input nilai draft → kirim nilai → pengesahan koordinator → buat sertifikat → pengesahan → unduh PDF/DOCX → revisi dengan versi Word lama tetap tersedia juga diuji.

Pengujian otomatis bukan pengganti evaluasi pengguna. Alur lengkap belum diuji manual oleh pengguna; layout Word dan printer nyata juga belum dinyatakan presisi/stabil.
