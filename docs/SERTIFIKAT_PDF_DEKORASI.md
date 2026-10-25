# Sertifikat PDF berdekorasi tanpa LibreOffice

Pembaruan 25 Oktober 2026 menambah jalur cetak PHP mandiri. Kode DOCX/LibreOffice, konfigurasi `CERTIFICATE_LIBREOFFICE`, template DOCX lama dan unduh Word tetap dipertahankan. Ini ekstensi internal yang opsional, bukan pemasangan aplikasi atau plugin baru pada komputer pengguna.

## Coba contoh yang sudah disiapkan

1. Login koordinator, buka **Sertifikat**, cari **DEMO-SERT-001** atau gunakan pintasan sertifikat demo.
2. Buka detail, pilih **Atur template / identitas cetak untuk versi baru**.
3. Pilih **DEMO — PDF berdekorasi tanpa LibreOffice**, klik **Pilih Format**. Isian cetak dari sertifikat demo sebelumnya tetap terisi.
4. Klik **Pratinjau PDF**. Tab baru menampilkan dua halaman dengan dekorasi dan tanda **PRATINJAU - BELUM DITERBITKAN**. Tidak perlu mengajukan versi baru hanya untuk melihat hasil.

Template demo ditambahkan oleh `CertificatePdfTemplateDemoSeeder`. Tidak menambah siswa, mengubah nilai, mengesahkan, atau mengganti arsip sertifikat yang sudah terbit. Seeder dapat dipanggil ulang tanpa menggandakan template.

## Desain sendiri

1. Buka **Template Cetak Sertifikat**, pilih ketentuan penilaian jurusan.
2. Pilih penandatangan depan/belakang dan isi identitas sekolah. Nilai tetap mengikuti ketentuan industri yang diinput/disahkan koordinator.
3. Pilih **PDF berdekorasi (tanpa LibreOffice)**. Unggah PDF depan dan belakang, masing-masing satu halaman A4 portrait, tanpa sandi, maksimal 2 MB. Contoh dekorasi dapat diunduh pada formulir.
4. Sisakan area kosong untuk konten. Masukkan jarak kiri/atas, lebar dan tinggi kotak isi dalam **mm** untuk setiap sisi. Default: kiri 20, atas 25, lebar 170, tinggi 247 mm.
5. Simpan sebagai template baru. Pilih template pada siswa, lengkapi data, lalu gunakan **Pratinjau PDF** sebelum mengajukan/pengesahan.

Posisi diatur untuk **seluruh blok isi per halaman**, belum merupakan editor posisi setiap nama/kolom secara bebas. Susunan identitas, tabel nilai dan tanda tangan mengikuti format sistem. Dekorasi harus disesuaikan dengan area tersebut. Template yang sudah disimpan tidak ditimpa; koreksi melalui template versi baru.

## Presisi dan batas

- Ukuran tetap 210 × 297 mm. Satu halaman depan dan satu halaman belakang. Desain diimpor sebagai halaman PDF, kemudian isi teks/tabel Unicode digambar di atasnya; tidak melalui konversi Word.
- Area isi harus di dalam halaman, tepi minimal 5 mm, lebar minimal 120 mm dan tinggi minimal 140 mm. Nilai koordinat tidak bisa menjadi kode/CSS bebas.
- Teks membungkus sesuai lebar area. Label aspek maksimum 100 karakter dan uraian 600 karakter mengikuti batas ketentuan. Isian cetak panjang dibatasi sebelum render; halaman berlebih ditolak, tanpa menghapus aspek/nilai atau memperkecil huruf otomatis.
- Parser tidak mendukung semua PDF modern/terenkripsi. Jika ditolak, ekspor ulang PDF standar 1.4 tanpa sandi. Impor mempertahankan artwork halaman, bukan tautan aktif, formulir interaktif atau anotasi. Tidak otomatis menghapus nama/nilai yang terlanjur tercetak pada desain unggahan.
- Pratinjau memakai sumber/validasi renderer yang sama, tetapi tidak menyimpan nomor resmi, pengajuan, approval atau versi. Nomor baru yang masih kosong memakai teks contoh; periksa lagi bila nomor resmi panjang. Data dapat berubah setelah pratinjau; pengajuan dan pengesahan tetap memeriksa sumber.
- Hasil terbit dibekukan sebagai PDF beserta snapshot, hash desain, dan area isi per versi. Template baru tidak mengubah arsip lama. Tanda pratinjau tidak dimasukkan ke PDF terbit.
- Salinan DOCX dari arsip tetap berfungsi; tata letaknya adalah susunan Word terpisah dan tidak otomatis memuat dekorasi PDF. Konversi DOCX unggahan ke PDF masih tersedia bila ekstensi LibreOffice dikonfigurasi kelak.
- Cetak dengan ukuran aktual/100%, cek margin printer dan pilihan bolak-balik. Hasil browser bukan jaminan presisi printer atau tanda tangan fisik sekolah.

## Migrasi

Migrasi `2026_10_24_000007_add_pdf_certificate_backgrounds` menambah kolom nullable pada template cetak. Tabel/dokumen lama tidak dikonversi. Sudah diterapkan pada MySQL lokal setelah backup `storage/app/private/backups/before-pkl-tags-20261024-215612.sql` (1.351.092 byte). Tidak memakai `migrate:fresh` atau reseed umum. Rollback ditolak jika template PDF sudah tersimpan, agar desain yang dipakai arsip tidak hilang.

Flag Sertifikat tetap merah karena arsip lama dan pengujian lapangan yang belum selesai. LibreOffice tidak lagi menjadi syarat untuk PDF berdekorasi. [Daftar kekurangan terkini](KEKURANGAN_SETELAH_UPGRADE.md).

## Verifikasi

**151 tes / 2.880 assertion lulus** pada suite penuh. Cakupan tambahan: PDF tanpa LibreOffice, pratinjau tanpa penulisan data, penilaian bebas/80-20, peran, penolakan desain rusak/ukuran berlebih/halaman salah, batas area dan isi berlebih, hash desain, arsip versi lama tetap identik, serta demo idempotent. Tes DOCX lama tetap lulus, termasuk jalur kegagalan saat konverter tidak tersedia. Tidak mengklaim konversi LibreOffice nyata sudah diuji.

PDF baru hanya menyematkan bagian font yang dipakai, untuk mengurangi ukuran arsip dan penggunaan memori. Uji browser memakai server/database SQLite terpisah; tidak mereset data kerja. Render ulang dua halaman PDF dengan PDF.js digunakan untuk memeriksa artwork, nilai dan pratinjau. Alat inspeksi Node hanya dipasang pada direktori sementara pengujian, bukan dependency aplikasi; penerbitan PDF tetap PHP saja.

Artefak terakhir: `C:/Users/jorda/AppData/Local/Temp/simmag-journal-smoke-jM9uzU`, berisi `certificate-decorated-preview.pdf`, `certificate-decorated-issued.pdf`, render PNG tiap halaman, dan tangkapan formulir mobile. Inspeksi memastikan watermark ada pada kedua halaman pratinjau dan tidak ada pada PDF terbit; masing-masing tepat dua halaman A4.

Migrasi dan seeder contoh sudah sukses sebelum sesi terputus. Pada cek ulang 25 Oktober, koneksi MySQL lokal ditolak (port 3306 tidak melayani); aktifkan MySQL/Laragon sebelum mencoba aplikasi. Tidak menjalankan ulang migrasi/reset untuk mengatasi server database yang mati.
