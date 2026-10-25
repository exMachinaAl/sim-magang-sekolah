# Kekurangan setelah upgrade — 25 Oktober 2026

Pembaruan setelah fresh yang diizinkan pengguna: **0 sertifikat terbit tanpa arsip aktif**. Flag Sertifikat kini UNSTABLE untuk UAT, bukan merah akibat data demo lama. HTTPS lokal sudah lulus uji komputer; pemasangan CA/firewall dan uji HP masih memerlukan tindakan pengguna. [Catatan reset](RESET_DEMO_20261025.md), [panduan HTTPS HP](HTTPS_KIOSK_LOKAL.md).

PDF berdekorasi dan pratinjau sekarang tersedia tanpa LibreOffice. Konversi DOCX dipertahankan sebagai ekstensi opsional, bukan syarat penggunaan sertifikat. [Cara mencoba dan batas layout](SERTIFIKAT_PDF_DEKORASI.md).

Laporan & Audit selesai untuk flag cakupan roster. Dashboard, Timeline, Monitoring, dan Penilaian juga telah menutup temuan audit yang didokumentasikan. Status tersebut bukan jaminan tidak ada bug baru atau bahwa seluruh alur sudah diuji pengguna.

Jurnal telah lulus enam skenario transaksi bersamaan di MySQL lokal terpisah, termasuk koreksi jadwal saat siswa menyimpan. Tidak ditemukan bug logika pada skenario tersebut; uji ini tidak mencakup transaksi Sertifikat atau kapasitas server sekolah. [Bukti dan cara mengulang](UJI_MYSQL_JURNAL.md).

Sertifikat telah lulus pengujian terpisah sebanyak sepuluh skenario MySQL. Error 500 saat pengajuan ganda/benturan nomor diperbaiki; versi lama, perubahan sumber, arsip dan ID pelaku diperiksa saat transaksi bersamaan. [Rincian](UJI_MYSQL_SERTIFIKAT.md).

| Urutan | Sisa kekurangan | Tindak lanjut dan batas saat ini |
| --- | --- | --- |
| 1 | Arsip demo lama — selesai setelah fresh. | Database lokal dibangun ulang atas izin pengguna; semua sertifikat terbit demo memiliki arsip. Tidak merekonstruksi atau mengesahkan dokumen lama secara diam-diam. Database lain yang belum direset tetap perlu diaudit. |
| 2 | Konversi template DOCX unggahan ke PDF belum aktif; kini hanya ekstensi opsional. | PDF berdekorasi, PDF bawaan dan salinan DOCX arsip sudah bisa dipakai tanpa LibreOffice. Jika ekstensi konversi diperlukan kelak, konfigurasi LibreOffice dan uji hasilnya. Dekorasi PDF tidak otomatis ikut ke DOCX. |
| 3 | Presisi Word/PDF, posisi tanda tangan, dan cetak sertifikat bolak-balik belum disetujui melalui pengujian fisik. | Pengguna membandingkan hasil dengan template sekolah/printer. Penggantian sertifikat lama yang sudah beredar tetap memerlukan koordinasi offline. |
| 4 | Jurnal/kiosk belum diuji dengan Google Camera pada HP pembimbing dan host yang bisa diakses HP. | Uji pendaftaran browser Dodi, scan terlambat, perubahan nilai, dan pencabutan perangkat. Cookie/browser berbeda bisa memerlukan pendaftaran ulang. Flag Jurnal tetap UNSTABLE. |
| 5 | Cetak jurnal Excel enam entri per lembar belum diperiksa secara fisik. | Periksa tujuh entri (dua lembar), narasi panjang, nilai/predikat, dan waktu scan. PDF jurnal tetap menolak lebih dari 300 entri; gunakan rentang tanggal lebih kecil. |
| 6 | Pemulihan server baru/foto filesystem dan kapasitas staging belum diuji tuntas. | Pemulihan database/enam berkas arsip, enam skenario Jurnal serta sepuluh skenario Sertifikat bersamaan sudah lulus pada MySQL lokal terpisah. Lanjutkan pemulihan lintas server beserta foto dan pengujian beban sekolah. [Bukti pemulihan](PEMULIHAN_ARSIP_DATABASE.md), [bukti transaksi Sertifikat](UJI_MYSQL_SERTIFIKAT.md). |
| 7 | Alur penuh manual dari nilai industri sampai sertifikat dicetak/diserahkan belum dinyatakan selesai oleh pengguna. | Jalankan satu kasus utuh dengan dokumen sumber asli. Tes otomatis dan browser terisolasi sudah lulus, namun bukan pengesahan kelengkapan/kebenaran dokumen industri. |
| 8 | Pengaturan PDF berdekorasi masih berupa kotak isi per sisi. | Desain harus menyediakan area kosong; belum ada editor bebas untuk menggeser setiap field. Satu halaman A4 portrait per sisi, maksimum 2 MB per file. PDF tertentu perlu diekspor ulang sebagai PDF 1.4. Isi yang tidak muat ditolak, bukan dipotong. |

Pemeriksaan setelah fresh: 31 roster, 19 penempatan, 12 belum ditempatkan, 0 penempatan tanpa roster, 0 ketidakcocokan identitas penempatan-roster, dan 0 sertifikat terbit tanpa arsip aktif. Siswa belum ditempatkan dan guru opsional adalah keadaan bisnis yang sah, bukan error yang harus dihapus.

Tahap Laporan tidak membutuhkan migrasi. Jangan menjalankan `migrate:fresh` pada database kerja untuk mencoba CSV. Pengujian otomatis dan browser memakai database terpisah. Lihat [panduan laporan](LAPORAN_ROSTER.md) untuk cara mencoba dan batas format ekspor.
