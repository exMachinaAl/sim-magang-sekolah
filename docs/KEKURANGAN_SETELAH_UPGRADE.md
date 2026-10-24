# Kekurangan setelah upgrade — 24 Oktober 2026

Laporan & Audit selesai untuk flag cakupan roster. Dashboard, Timeline, Monitoring, dan Penilaian juga telah menutup temuan audit yang didokumentasikan. Status tersebut bukan jaminan tidak ada bug baru atau bahwa seluruh alur sudah diuji pengguna.

| Urutan | Sisa kekurangan | Tindak lanjut dan batas saat ini |
| --- | --- | --- |
| 1 | Satu sertifikat lama terbit belum mempunyai arsip versi aktif pada database lokal. | Koordinator memeriksa sumber lalu memakai rekonstruksi arsip dengan catatan. PDF asli yang tidak pernah disimpan tidak dapat dipulihkan hanya dari data hidup. Tidak dilakukan otomatis; flag Sertifikat tetap merah. |
| 2 | Konversi template DOCX unggahan ke PDF belum tersedia di lingkungan lokal. | Memerlukan konfigurasi LibreOffice dan pengujian hasil nyata. PDF bawaan serta salinan DOCX depan/belakang dari arsip lengkap sudah bisa dipakai. |
| 3 | Presisi Word/PDF, posisi tanda tangan, dan cetak sertifikat bolak-balik belum disetujui melalui pengujian fisik. | Pengguna membandingkan hasil dengan template sekolah/printer. Penggantian sertifikat lama yang sudah beredar tetap memerlukan koordinasi offline. |
| 4 | Jurnal/kiosk belum diuji dengan Google Camera pada HP pembimbing dan host yang bisa diakses HP. | Uji pendaftaran browser Dodi, scan terlambat, perubahan nilai, dan pencabutan perangkat. Cookie/browser berbeda bisa memerlukan pendaftaran ulang. Flag Jurnal tetap UNSTABLE. |
| 5 | Cetak jurnal Excel enam entri per lembar belum diperiksa secara fisik. | Periksa tujuh entri (dua lembar), narasi panjang, nilai/predikat, dan waktu scan. PDF jurnal tetap menolak lebih dari 300 entri; gunakan rentang tanggal lebih kecil. |
| 6 | Pemulihan backup beserta arsip dan transaksi MySQL serentak belum diuji tuntas. | Lakukan simulasi pemulihan pada database terpisah dan pengujian dua koordinator secara bersamaan. Backup yang tersedia belum otomatis membuktikan dapat dipulihkan lengkap. |
| 7 | Alur penuh manual dari nilai industri sampai sertifikat dicetak/diserahkan belum dinyatakan selesai oleh pengguna. | Jalankan satu kasus utuh dengan dokumen sumber asli. Tes otomatis dan browser terisolasi sudah lulus, namun bukan pengesahan kelengkapan/kebenaran dokumen industri. |

Pemeriksaan baca-saja terakhir: 31 roster, 19 penempatan, 12 belum ditempatkan, 0 penempatan tanpa roster, 0 ketidakcocokan identitas penempatan-roster, dan 1 sertifikat terbit tanpa arsip aktif. Siswa belum ditempatkan dan guru opsional adalah keadaan bisnis yang sah, bukan error yang harus dihapus.

Tahap Laporan tidak membutuhkan migrasi. Jangan menjalankan `migrate:fresh` pada database kerja untuk mencoba CSV. Pengujian otomatis dan browser memakai database terpisah. Lihat [panduan laporan](LAPORAN_ROSTER.md) untuk cara mencoba dan batas format ekspor.
