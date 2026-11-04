# Perbaikan sesuai flag — 4 November 2026

> Tahap ini sudah ditutup oleh [stabilisasi tahap 2](STABILISASI_20261104_TAHAP2.md). Jurnal dan Sertifikat kini STABLE untuk cakupan audit/penggunaan lokal. Daftar flag dan roadmap di bawah adalah status sebelum penutupan tahap 2.

Keluhan pengguna: isi identitas penerima sertifikat terlalu ke kiri. Pendaftaran kiosk Dodi dan validasi jurnal melalui HTTPS HP sudah dikonfirmasi berhasil; pemeriksaan cetak belum diterima final.

## Perubahan

- PDF bawaan dan PDF dengan dekorasi: blok identitas dipusatkan, label/kolon/nilai dipisah agar rapi, ringkasan identitas halaman belakang juga di tengah. Nilai dan aturan penilaian tidak diubah.
- Distribusi sertifikat memeriksa byte/hash PDF versi yang sudah disahkan sebelum perubahan status. Arsip rusak menghasilkan HTTP 409 tanpa menambahkan riwayat distribusi atau audit sukses.
- Uji backup mencakup foto jurnal privat melalui ZIP dan manifest. Foto dipulihkan ke folder baru yang terpisah; referensi database, ukuran dan hash diperiksa. SQL saja tidak berisi foto.
- Pesan flag diperbarui: tidak lagi menganggap pendaftaran/scan HP atau pemulihan foto lokal sebagai pekerjaan yang belum dilakukan.

Tidak ada migrasi, fresh, reseed atau perubahan arsip sertifikat terbit. DOCX dan ekstensi LibreOffice tetap tersedia; tata letak DOCX tidak diubah pada tahap ini.

## Pratinjau untuk koreksi

- [PDF bawaan](../storage/app/private/pratinjau-tengah-bawaan-20261103.pdf)
- [PDF dekorasi](../storage/app/private/pratinjau-tengah-dekorasi-20261103.pdf)

Keduanya memakai siswa fiktif `DEMO-SERT-001`, terdiri dari dua halaman, dan bertanda **PRATINJAU - BELUM DITERBITKAN**. Dibuat melalui controller pratinjau tanpa menerbitkan versi baru. Periksa nama, NIS, tempat/tanggal lahir dan identitas belakang; cetak skala 100% untuk evaluasi fisik. Di aplikasi gunakan pratinjau pada pengaturan/revisi sertifikat untuk melihat layout terbaru. Unduhan dokumen yang sudah terbit tetap menampilkan arsip aslinya; perubahan resmi harus melalui revisi.

Render PDF telah diperiksa secara visual pada depan dekorasi dan belakang bawaan. Pengukuran kedua PDF: pusat halaman 297,64 pt; pusat nama dan kolon identitas juga 297,64 pt (selisih kurang dari 0,001 pt). Ini menguji contoh demo, bukan jaminan semua template atau printer.

## Yang perlu diuji pengguna

Gunakan dua PDF pratinjau di atas. Pengujian ini tidak mengubah database.

1. **Halaman depan PDF bawaan**: buka pada zoom 100%. Nama penerima harus berada di tengah. Kolon pada baris NIS, tempat/tanggal lahir, bidang, program dan konsentrasi harus segaris di tengah; label berada di kiri kolon dan nilai di kanannya.
2. **Halaman depan PDF dekorasi**: ulangi pemeriksaan yang sama. Isi tidak boleh menabrak garis/bingkai dekorasi dan nama panjang tidak boleh terpotong.
3. **Halaman belakang keduanya**: identitas penerima di atas tabel nilai harus berada di tengah. Tabel nilai harus tetap utuh, terbaca, dan tidak melewati halaman.
4. **Cetak bolak-balik**: cetak skala 100% tanpa opsi `fit/shrink`. Periksa orientasi depan/belakang, margin, posisi tanda tangan, dan apakah area tengah terlihat benar pada kertas yang dipakai sekolah.
5. **Jurnal Excel**: ekspor rentang dengan tujuh kegiatan agar menjadi lebih dari satu lembar. Periksa enam entri pertama, kegiatan ketujuh, narasi panjang, nilai/predikat dan tanggal validasi. Catat bila ada kolom terpotong atau pindah halaman.

Laporkan koreksi dengan nomor skenario, status `LULUS` atau `GAGAL`, hasil yang terlihat dan hasil yang diharapkan. Untuk masalah cetak, sebutkan aplikasi pembuka PDF/Excel, ukuran kertas dan opsi skala. Tidak perlu mengirim data siswa asli.

## Bukti pemulihan dan transaksi

- Regresi lengkap 4 November: `php artisan test --compact` **165 tes lulus / 2.934 assertion**, 291,87 detik. Log: `storage/logs/stabilization-tests-20261104.log`. Termasuk penolakan distribusi PDF rusak, backup foto hilang/rusak/path tidak aman, serta pemeriksaan bahwa pemulihan tidak mengubah sumber.
- Audit baca-saja 4 November: 31 roster, 19 penempatan, nol penempatan tanpa roster, nol ketidakcocokan identitas, nol sertifikat kehilangan jadwal dan nol sertifikat terbit tanpa arsip aktif.
- Laporan pemulihan nyata 3 November: `storage/app/private/backups/archive-probe-20261103-081550-e4ffd11da2edc8b4.json`, status `passed`.
- 35 tabel cocok, 10 berkas arsip lolos integritas, 12 referensi foto / 1 berkas unik berhasil dipulihkan. Tidak ada sertifikat terbit tanpa arsip aktif. Database sementara dibersihkan dan sumber tidak berubah.
- Simpan `.sql`, `-photos.zip` dan `.json` dengan nama dasar yang sama bersama-sama secara privat. Hash foto merupakan pembanding pada waktu backup, bukan bukti keaslian sebelum backup.
- Sepuluh skenario transaksi sertifikat MySQL lulus pada proses uji terpisah; hasil run ada pada direktori sementara `simmag-cert-race-3efc9c6e0e4e59a4/result.json`.

## Sisa sebelum menutup flag

1. Koreksi pengguna terhadap posisi identitas PDF baru dan cetak bolak-balik.
2. Penerimaan hasil Excel jurnal, termasuk tujuh entri, narasi panjang dan nilai/predikat. Pengguna sudah memeriksa cetak, tetapi keluhan spesifik yang dijelaskan adalah posisi identitas sertifikat.
3. Pengujian pemulihan pada server tujuan, konfigurasi HTTPS produksi dan kapasitas sekolah. Uji lokal bukan bukti deployment produksi siap.

Pada penutupan tahap pertama ini, flag Jurnal dan Sertifikat masih UNSTABLE. Keduanya kemudian ditutup pada [tahap 2](STABILISASI_20261104_TAHAP2.md) setelah penguatan tata cetak dan regresi lanjutan.

## Roadmap setelah koreksi pengguna

1. Perbaiki temuan dari lima skenario di atas dan ulangi tes yang berhubungan langsung.
2. Layout sertifikat diperkuat dan flag audit lokal sudah ditutup pada tahap 2; arsip lama tetap tidak ditimpa.
3. Tata cetak Excel jurnal diperkuat dan flag audit lokal sudah ditutup pada tahap 2.
4. Setelah kedua alur lokal diterima, susun dan jalankan uji server tujuan: HTTPS/domain, pemulihan SQL + ZIP foto, konfigurasi kunci, serta beban data sekolah.
5. Baru setelah uji server tujuan lulus, lakukan penilaian kesiapan produksi. Fitur baru di luar flag dibahas sebelum dikerjakan.

Format dokumentasi tahap ini dan tahap berikutnya mengikuti [pola penyerahan upgrade](POLA_PENYERAHAN_UPGRADE.md).

