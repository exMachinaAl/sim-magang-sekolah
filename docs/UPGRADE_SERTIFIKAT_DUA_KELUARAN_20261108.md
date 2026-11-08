# Upgrade sertifikat dua keluaran: digital dan siap cetak

Tanggal pengujian: 8 November 2026.

Status: **LULUS OTOMATIS**. Uji presisi DOCX resmi pada LibreOffice server dan printer sekolah masih **TERTUNDA** karena LibreOffice belum terpasang pada komputer pengembangan.

## Tujuan dan hasil

Satu versi sertifikat sekarang dapat menghasilkan dua PDF dari data dan snapshot yang sama:

1. **PDF digital berdekorasi** untuk disimpan atau dibagikan secara digital.
2. **PDF cetak tanpa dekorasi** untuk dicetak pada kertas sertifikat yang dekorasinya sudah tersedia, sehingga bingkai/dekorasi tidak tercetak dua kali.

Ada dua jalur desain:

- **PDF aplikasi + PDF dekorasi**: aplikasi mengatur isi, posisi, dan font; tidak memerlukan LibreOffice.
- **DOCX teks + PDF dekorasi**: DOCX mengatur teks, font, ukuran, tabel, dan posisi secara persis; PDF hanya berisi dekorasi. Konversi DOCX memerlukan LibreOffice di server.

Pada jalur DOCX, aplikasi mengganti token tanpa membuat ulang gaya teks. Karena itu font dan posisi token mengikuti run/paragraf/tabel tempat token diletakkan pada DOCX. PDF dekorasi harus bersih dari teks data agar keluaran digital tidak menumpuk isi.

Dokumen contoh `01_SERTIFIKAT DARWATI.docx` dan `01_SERTIFIKAT_SEKOLAH DARWATI.docx` telah diperiksa sebagai acuan struktur. Isinya berupa lapisan teks/tabel tanpa media dekorasi tertanam. Berkas `sertifikat_kepala_sekolah_dan_industri.docx` diperlakukan sebagai template bertoken yang dapat diunggah melalui menu desain.

## Perubahan sebelum dan sesudah

Sebelum upgrade:

- PDF berdekorasi hanya menghasilkan satu arsip PDF;
- pengguna tidak memperoleh keluaran khusus untuk kertas yang sudah berdekorasi;
- DOCX dan dekorasi PDF tidak dapat dipakai bersama sebagai satu desain.

Sesudah upgrade:

- pratinjau menyediakan tombol **Digital Berdekorasi** dan **Cetak Tanpa Dekorasi** untuk desain dua keluaran;
- desain baru **DOCX teks asli + PDF dekorasi** tersedia;
- hasil DOCX tanpa dekorasi dijadikan PDF cetak, kemudian PDF yang sama ditumpuk ke dekorasi untuk versi digital;
- kedua PDF diarsipkan beserta SHA-256 dan diperiksa integritasnya saat diunduh atau dipulihkan;
- persetujuan, identitas, nilai, desain, dan nomor sertifikat tetap satu snapshot yang sama;
- revisi desain tidak menimpa PDF versi lama.

## Perubahan database

Migrasi additive `2026_11_08_000002_add_print_ready_certificate_pdf.php` menambahkan:

- `certificate_documents.print_pdf_base64`;
- `certificate_documents.print_pdf_sha256`.

Migrasi sudah diterapkan pada database lokal. Tidak dilakukan `migrate:fresh`, seeder ulang, atau rekonstruksi dokumen lama. Dokumen lama tetap sah dengan satu PDF; keluaran cetak terpisah tersedia mulai versi baru yang dibuat memakai desain dua keluaran.

Backup sebelum migrasi tersimpan sebagai `storage/app/private/backups/before-pkl-tags-20261108-111444.sql` dengan ukuran 3.815.206 byte.

Restore-probe pascamigrasi lulus:

- skema dan isi seluruh tabel hasil pemulihan identik dengan sumber;
- 11 berkas arsip diperiksa;
- 12 referensi lampiran jurnal / 1 foto unik dipulihkan dan hash cocok;
- database sementara dihapus dan database kerja tidak ditimpa.

Percobaan restore-probe pertama sebelum migrasi menemukan kode integritas baru belum kompatibel dengan skema lama. Pemeriksaan kemudian dibuat sadar-skema, diuji ulang, dan lulus sebelum migrasi diterapkan. Percobaan tersebut tidak mengubah database kerja.

## Pengujian pengembang

- Tes regresi penuh: **178 tes / 3.103 assertion**, seluruhnya lulus dalam 71,29 detik.
- Tes sertifikat dan pemulihan mencakup dua pratinjau berbeda, dua hash arsip, larangan unduh sebelum persetujuan, unduhan setelah persetujuan, integritas kedua PDF, serta PDF versi lama yang tetap identik.
- Smoke browser fresh database/storage terisolasi: lulus untuk kiosk, jurnal, FAQ, dashboard, timeline, monitoring, penilaian, pembuatan/persetujuan sertifikat, dua keluaran PDF, arsip versi lama, desktop/mobile, dan tidak ada error JavaScript.
- Uji kapasitas MySQL terisolasi: lulus.
- Restore-probe pascamigrasi: lulus.
- Pemeriksaan whitespace/diff: dilakukan pada penutupan tahap ini.

Pengujian DOCX memakai konverter terisolasi untuk memverifikasi alur data, penggabungan dua halaman, dekorasi, arsip, izin, dan hash. Pengujian itu tidak membuktikan bahwa font serta hasil rasterisasi LibreOffice di server akan sama persis dengan Microsoft Word atau printer sekolah.

## Cara uji manual

Gunakan akun koordinator dan sebuah siswa yang penilaiannya sudah disahkan.

1. Buka **Sertifikat → Desain Cetak & Penandatangan**.
2. Buat desain dengan format **DOCX teks asli + PDF dekorasi (dua keluaran)**.
3. Unggah DOCX depan dan belakang yang telah memakai token aplikasi. Letakkan token pada posisi dan font final; jangan menambahkan dekorasi pada DOCX bila dekorasi akan berasal dari PDF.
4. Unggah PDF dekorasi depan dan belakang berukuran A4 portrait, masing-masing satu halaman, hanya berisi bingkai/latar/logo yang memang diinginkan.
5. Simpan desain, cari siswa, pilih desain tersebut, lalu buka dua pratinjau.
6. Pada **Digital Berdekorasi**, pastikan teks dan dekorasi menyatu serta tidak berganda.
7. Pada **Cetak Tanpa Dekorasi**, pastikan data yang sama tampil tanpa bingkai/dekorasi.
8. Ajukan dan setujui sertifikat. Halaman detail harus menyediakan dua tombol unduh dengan isi dan hash berbeda.
9. Cetak PDF tanpa dekorasi pada kertas sertifikat asli dengan skala **Actual size / 100%**, bukan `Fit to page`. Periksa pusat nama, NIS, nomor sertifikat, tabel nilai, dan tanda tangan.
10. Buka versi sertifikat lama. PDF lamanya harus tetap dapat diunduh dan tidak berubah.

Tanpa LibreOffice, jalur **PDF aplikasi + PDF dekorasi** tetap dapat diuji sekarang dan juga menghasilkan dua keluaran. Jalur DOCX akan menampilkan kegagalan konversi yang jelas sampai binary LibreOffice tersedia dan `CERTIFICATE_LIBREOFFICE` menunjuk ke executable yang benar.

## Format koreksi

```text
Upgrade: Sertifikat dua keluaran
Skenario: 1-10
Status: LULUS / GAGAL
Hasil aktual: ...
Harapan: ...
Dokumen/perangkat: nama DOCX, PDF dekorasi, printer, dan skala cetak
```

Untuk masalah presisi, sertakan elemen yang bergeser (misalnya nama penerima), arah/perkiraan jarak pergeseran, serta apakah masalah terlihat di PDF atau baru setelah dicetak.

## Status flag

Menu Sertifikat tetap berstatus `stable` untuk alur aplikasi dan integritas arsip yang sudah diuji. Tidak ada flag bug baru. Uji LibreOffice/font/printer dicatat sebagai UAT lingkungan, bukan dianggap lulus otomatis.

## Roadmap lanjutan

1. Pasang LibreOffice dan font resmi pada server/staging, lalu jalankan skenario manual di atas menggunakan template sekolah yang sebenarnya.
2. Koreksi hanya template DOCX atau PDF dekorasi bila data sudah benar tetapi posisi visual belum presisi; kode generator tidak perlu diubah untuk variasi dekorasi.
3. Setelah UAT dua keluaran diterima, lanjutkan Tahap 3 yang sudah disepakati: pusat identitas penandatangan dan pola kepala sekolah / kepala sekolah + industri dengan snapshot per versi.

Fitur di luar roadmap tersebut dibicarakan terlebih dahulu sebelum diimplementasikan.
