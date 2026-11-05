# Kekurangan setelah upgrade — 4 November 2026

Pembaruan tahap 2: **seluruh flag menu utama telah ditutup untuk cakupan audit/penggunaan lokal**. Jurnal Excel kini dipusatkan dengan margin cetak seimbang; Sertifikat memakai identitas PDF terpusat dan verifikasi arsip sebelum distribusi. Keterbatasan di bawah tetap menjadi checklist UAT/deployment, bukan alasan menampilkan tag UNSTABLE pada modul. [Bukti, file contoh dan roadmap](STABILISASI_20261104_TAHAP2.md).

UAT pengguna 4 November: **Excel Jurnal dinyatakan cukup bagus**. Koreksi Sertifikat belum diberikan. Persiapan staging sudah memiliki [pemeriksaan konfigurasi dan template aman](DEPLOYMENT_STAGING.md); penerapan menunggu domain/server staging.

Uji kapasitas data lokal setara satu angkatan lulus tiga kali pada 5 November: 320 siswa dan 5.120 jurnal, seluruh probe di bawah 541 ms, database sementara terhapus, dan database kerja tidak berubah. [Perubahan, bukti, dan cara mengulang](UPGRADE_KAPASITAS_20261105.md). Ini belum menggantikan load test HTTP pada server staging.

Render digital Sertifikat diperkuat dan lulus alur browser serta pemeriksaan geometri pada 5 November. Pratinjau dan dokumen terbit tepat dua halaman A4; pusat nama, kolon identitas depan, dan identitas belakang terukur 297,64 pt. [Bukti sebelum/sesudah dan cara uji](UPGRADE_SERTIFIKAT_LAYOUT_20261105.md). Penerimaan cetak fisik tetap bergantung pada printer sekolah.

FAQ berbasis role dan petunjuk kontekstual setiap halaman ditambahkan pada 6 November. Hover tertunda, klik, keyboard, pencarian, dan panel ponsel telah lulus otomatis; fitur berstatus stabil lokal tanpa flag baru. [Perubahan dan cara uji](UPGRADE_FAQ_PANDUAN_20261106.md).

**Pendaftaran kiosk dan validasi jurnal lewat HTTPS di HP sudah dikonfirmasi berhasil oleh pengguna.** Identitas penerima PDF telah dipusatkan dan distribusi menolak arsip PDF rusak. Pemulihan lokal database, arsip dan foto berhasil; tidak ada fresh/reset pada tahap ini. [Hasil dan pratinjau terbaru](STABILISASI_20261104.md). Database demo tetap memiliki **0 sertifikat terbit tanpa arsip aktif** pada pemeriksaan pemulihan 3 November.

PDF berdekorasi dan pratinjau sekarang tersedia tanpa LibreOffice. Konversi DOCX dipertahankan sebagai ekstensi opsional, bukan syarat penggunaan sertifikat. [Cara mencoba dan batas layout](SERTIFIKAT_PDF_DEKORASI.md).

Laporan & Audit selesai untuk flag cakupan roster. Dashboard, Timeline, Monitoring, dan Penilaian juga telah menutup temuan audit yang didokumentasikan. Status tersebut bukan jaminan tidak ada bug baru atau bahwa seluruh alur sudah diuji pengguna.

Jurnal telah lulus enam skenario transaksi bersamaan di MySQL lokal terpisah, termasuk koreksi jadwal saat siswa menyimpan. Tidak ditemukan bug logika pada skenario tersebut; uji ini tidak mencakup transaksi Sertifikat atau kapasitas server sekolah. [Bukti dan cara mengulang](UJI_MYSQL_JURNAL.md).

Sertifikat telah lulus pengujian terpisah sebanyak sepuluh skenario MySQL. Error 500 saat pengajuan ganda/benturan nomor diperbaiki; versi lama, perubahan sumber, arsip dan ID pelaku diperiksa saat transaksi bersamaan. [Rincian](UJI_MYSQL_SERTIFIKAT.md).

| Urutan | Sisa kekurangan | Tindak lanjut dan batas saat ini |
| --- | --- | --- |
| 1 | Arsip demo lama — selesai setelah fresh. | Database lokal dibangun ulang atas izin pengguna; semua sertifikat terbit demo memiliki arsip. Tidak merekonstruksi atau mengesahkan dokumen lama secara diam-diam. Database lain yang belum direset tetap perlu diaudit. |
| 2 | Konversi template DOCX unggahan ke PDF belum aktif; kini hanya ekstensi opsional. | PDF berdekorasi, PDF bawaan dan salinan DOCX arsip sudah bisa dipakai tanpa LibreOffice. Jika ekstensi konversi diperlukan kelak, konfigurasi LibreOffice dan uji hasilnya. Dekorasi PDF tidak otomatis ikut ke DOCX. |
| 3 | Presisi render digital selesai; cetak bolak-balik fisik belum diterima final. | Geometri pratinjau/terbit dan inspeksi visual otomatis lulus. Cetak pada A4 skala 100% tanpa `fit/shrink`; perbedaan printer dicatat sebagai temuan output khusus. Arsip terbit tetap utuh dan DOCX tidak diubah. |
| 4 | Pendaftaran kiosk dan scan jurnal HP lokal — selesai untuk skenario yang dikonfirmasi pengguna. | Kehilangan cookie/beda browser dan pencabutan perangkat sudah dicakup tes kode, belum dikonfirmasi seluruhnya lewat HP. Jangan mengulang status lama bahwa pendaftaran HP belum berhasil. |
| 5 | Pemeriksaan cetak Excel sudah dilakukan pengguna, tetapi penerimaan seluruh kasus belum dikonfirmasi. | Keluhan yang dirinci adalah identitas sertifikat. Selesaikan pemeriksaan tujuh entri (dua lembar), narasi panjang, nilai/predikat dan waktu scan. PDF jurnal tetap dibatasi 300 entri. |
| 6 | Pemulihan server baru dan beban HTTP staging belum diuji tuntas. | Pemulihan lokal serta kapasitas data satu angkatan sudah lulus pada lingkungan terpisah. SQL dan ZIP foto harus disimpan berpasangan. Lanjutkan pemulihan lintas server dan request HTTP bersamaan pada staging. [Bukti pemulihan](PEMULIHAN_ARSIP_DATABASE.md), [bukti kapasitas](UPGRADE_KAPASITAS_20261105.md). |
| 7 | Alur penuh manual dari nilai industri sampai sertifikat dicetak/diserahkan belum dinyatakan selesai oleh pengguna. | Jalankan satu kasus utuh dengan dokumen sumber asli. Tes otomatis dan browser terisolasi sudah lulus, namun bukan pengesahan kelengkapan/kebenaran dokumen industri. |
| 8 | Pengaturan PDF berdekorasi masih berupa kotak isi per sisi. | Desain harus menyediakan area kosong; belum ada editor bebas untuk menggeser setiap field. Satu halaman A4 portrait per sisi, maksimum 2 MB per file. PDF tertentu perlu diekspor ulang sebagai PDF 1.4. Isi yang tidak muat ditolak, bukan dipotong. |

Pemeriksaan setelah fresh: 31 roster, 19 penempatan, 12 belum ditempatkan, 0 penempatan tanpa roster, 0 ketidakcocokan identitas penempatan-roster, dan 0 sertifikat terbit tanpa arsip aktif. Siswa belum ditempatkan dan guru opsional adalah keadaan bisnis yang sah, bukan error yang harus dihapus.

Tahap Laporan tidak membutuhkan migrasi. Jangan menjalankan `migrate:fresh` pada database kerja untuk mencoba CSV. Pengujian otomatis dan browser memakai database terpisah. Lihat [panduan laporan](LAPORAN_ROSTER.md) untuk cara mencoba dan batas format ekspor.
