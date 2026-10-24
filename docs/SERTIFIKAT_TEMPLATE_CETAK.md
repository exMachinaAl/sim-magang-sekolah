# Template cetak sertifikat — 24 Oktober 2026

Pembaruan: [salinan Word dari arsip lengkap yang sudah disahkan](SERTIFIKAT_WORD_ARSIP.md) kini dapat disiapkan dan diunduh tanpa LibreOffice. Ketergantungan LibreOffice di bawah khusus jalur **unggahan DOCX → PDF**, bukan pengisian/unduhan DOCX dari arsip.

## Cakupan yang diterapkan

Menu **Sertifikat → Template Cetak & Penandatangan** menghubungkan satu versi template penilaian jurusan dengan versi template cetak. Guru tidak memperoleh kewenangan input nilai hanya karena namanya dicetak sebagai penandatangan. Koordinator tetap mencatat nilai industri dan mengesahkan administrasi.

- Pilihan depan: kepala sekolah saja, atau kepala sekolah dan industri.
- Pilihan belakang independen: pembimbing industri, atau pembimbing sekolah (nama dan NIP wajib).
- Data bersama template: bidang/program keahlian, nama sekolah, kepala sekolah/NIP, kota penerbitan.
- Data penerbitan siswa: tempat lahir, bidang pekerjaan, tanggal pengesahan, NISN/NIPD yang diperiksa koordinator, pimpinan industri atau pembimbing sekolah sesuai pilihan. Tanggal lahir tetap dari Data Siswa; kelas/konsentrasi/jadwal dari roster tahun PKL. NIS/NIP tetap teks, termasuk nol di depan.
- Nomor resmi bisa diisi saat pembuatan; kosong memakai nomor SIMMAG. Nomor unik. Tanggal pengesahan yang dicetak terpisah dari timestamp persetujuan aplikasi.
- Format bebas menampilkan semua aspek angka/teks. Format paket 80/20 memerlukan pemetaan eksplisit empat aspek dan satu laporan ke lima field berbeda, masing-masing bobot 20%. Empat aspek wajib bilangan bulat; desimal tidak dibulatkan diam-diam. Rata-rata aspek, laporan, hasil hitung, dan nilai akhir resmi dicetak terpisah. Nilai resmi tidak ditimpa oleh hasil hitung.
- Revisi metadata/format dibuat lewat **Atur template / identitas cetak untuk versi baru** pada detail. Arsip/PDF versi lama tidak ditimpa; versi baru memerlukan persetujuan ulang. Proses ini tidak membuka kunci nilai disahkan.

## Cara mencoba

1. Buat/pilih ketentuan penilaian di Penilaian. Template demo lama berisi tiga aspek angka dan satu teks: gunakan format bebas, bukan memaksakan 80/20.
2. Buat template cetak untuk ketentuan tersebut; isi identitas pejabat yang benar. Tidak ada nama pejabat contoh yang otomatis dipakai sebagai data resmi.
3. Cari siswa di Sertifikat dengan nama/NIS → **Lengkapi & Ajukan Sertifikat** → pilih template cetak → isi data penerbitan.
4. Ajukan, periksa salinan isi, setujui sebagai koordinator, lalu unduh PDF arsip dua halaman.

PDF bawaan berupa teks sederhana, bukan tiruan persis desain DOCX. Pengunggahan desain baru tidak mengubah PDF yang sudah terbit. Template cetak tersimpan tidak dapat ditimpa/dihapus melalui model; buat versi baru.

## Unggah DOCX dan dependensi server

Koordinator mengunggah sepasang DOCX, depan dan belakang, maksimal 4 MB masing-masing. Contoh empat DOCX dari paket pengguna disediakan melalui tombol unduh. Token memakai `${nama_field}`. Untuk format bebas: `${aspek_1}`, `${deskripsi_1}`, `${hasil_1}`, `${bobot_1}` sampai seluruh aspek (maksimal 12), urutan mengikuti ketentuan. Token yang tidak dikenal atau hilang ditolak, semua aspek wajib tercakup.

Template paket asli menyimpan mail merge ke Excel asal. Saat impor/unduh contoh, sistem menghapus binding mail merge lama agar tidak menarik data dari Excel tersebut. Bentuk/tata letak/token tetap dipertahankan. Makro, embedded objects, XML entities, tautan eksternal lainnya, dan field aktif tertentu ditolak; batas ukuran ZIP terurai 40 MB/600 entri.

LibreOffice **belum terpasang pada mesin kerja saat implementasi**. Unggah/validasi/penyimpanan DOCX tersedia, tetapi pembuatan PDF dari DOCX ditahan sampai administrator mengonfigurasi mesin konversi. Tidak ada fallback diam-diam ke layout bawaan yang berbeda.

Setelah memasang LibreOffice pada server, atur `.env`, misalnya:

```dotenv
CERTIFICATE_LIBREOFFICE="C:/Program Files/LibreOffice/program/soffice.exe"
```

Kemudian `php artisan config:clear`, pastikan akun proses PHP dapat menjalankan executable dan menulis direktori privat. Pada Linux isi path absolut `soffice` yang benar. Proses memakai profil sementara terpisah dan batas 60 detik; hasil setiap sisi harus satu halaman A4 portrait, digabung depan lalu belakang. Isi berlebih ditolak, bukan dipotong. File sementara dibersihkan setelah berhasil maupun gagal. Gunakan server/akun proses terbatas dan LibreOffice yang dipelihara keamanannya; pemeriksaan ZIP bukan pengganti sandbox OS.

DOCX yang sudah terisi ikut disimpan dan diverifikasi hash pada arsip versi. Unduhan DOCX hanya tersedia setelah disahkan dan sesuai hak akses. DOCX bisa diedit di luar sistem; PDF arsip tetap acuan dokumen versi tersebut.

## Kompatibilitas dan database

Migrasi additive `2026_10_24_000003_add_certificate_print_templates` menambah template cetak, pilihan penerbitan, dan arsip DOCX. Tidak memodifikasi nilai, identitas, atau arsip dokumen yang sudah terbit. Tautan/endpoint penerbitan skema lama masih dipertahankan untuk kompatibilitas; UI baru mengarahkan pengguna melalui kelengkapan cetak. Sertifikat skema nilai lama tanpa template ketentuan belum otomatis dikonversi.

Backup sebelum migrasi lokal: `storage/app/private/backups/before-pkl-tags-20261024-104738.sql` (101401 byte). Tidak ada fresh/reseed database kerja. Lampiran DOCX disimpan base64 dalam DB agar ikut backup; ukuran backup bertambah. Rollback ditolak jika template baru sudah dipakai sertifikat.

Migrasi tersebut sudah berhasil diterapkan pada MySQL lokal (batch 5). Composer memvalidasi dependensi; tidak ada advisory keamanan yang dilaporkan saat pemasangan PHPWord 1.4.0, FPDI 2.6.9, dan FPDF 1.9.0.

## Bukti dan batas pengujian

Suite penuh: **119 tes / 2.164 assertion lulus**. Pemeriksaan baca-saja `scripts/verify-certificate-json.php` juga lulus untuk dua sumber sertifikat lama pada MySQL; ini tidak dianggap pengujian konversi DOCX atau penerbitan format baru di MySQL secara konkuren.

Tes fitur meliputi empat kombinasi penandatangan, nilai resmi 85 versus hitung 85,20, field bebas/teks, akses peran, placeholder/XML escaping, penghapusan mail merge, validasi berkas, mesin konversi tidak tersedia, penggabungan PDF, snapshot/revisi, dan arsip tetap. Konversi LibreOffice pada tes DOCX disimulasikan; validasi/isi DOCX dan merger PDF sungguhan. Ini **bukan** bukti hasil tata letak LibreOffice sudah diuji.

Uji Edge headless memakai fixture terisolasi lulus untuk pembuatan template, formulir siswa desktop/390px, pengesahan, dan unduh PDF. Regresi kiosk, sertifikat lama, Timeline, dan Monitoring juga lulus. Artefak: `C:/Users/jorda/AppData/Local/Temp/simmag-journal-smoke-YjxbSj`.

Flag sertifikat belum dicopot: arsip lama perlu pemeriksaan koordinator, konversi DOCX nyata, cetak bolak-balik, uji pemulihan backup dan konkurensi MySQL masih perlu verifikasi.

Referensi implementasi: [PHPWord TemplateProcessor](https://phpoffice.github.io/PHPWord/usage/template.html), [XML output escaping](https://phpoffice.github.io/PHPWord/usage/introduction.html), [FPDI import dan penggabungan halaman](https://manuals.setasign.com/fpdi-manual/v2/the-fpdi-class/examples/).
