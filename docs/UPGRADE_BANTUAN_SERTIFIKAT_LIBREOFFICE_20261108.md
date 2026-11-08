# Upgrade bantuan sertifikat dan aktivasi LibreOffice

Tanggal pengujian: 8 November 2026.

Status: **LULUS OTOMATIS**. Presisi visual template asli masih perlu diterima pengguna melalui pratinjau dan printer sekolah.

## Cakupan perubahan

Sebelum upgrade, FAQ dan tombol bantuan mengambang sudah tersedia, tetapi petunjuk sertifikat masih singkat. Field template dan penerbitan belum menjelaskan arti isian satu per satu. LibreOffice sudah dipasang pada Windows, tetapi aplikasi belum menunjuk ke executable tersebut.

Sesudah upgrade:

- FAQ role koordinator memiliki urutan lengkap dari demo, PDF fixed, dua keluaran, LibreOffice, template asli, penerbitan, koreksi, dan default jurusan;
- FAQ mendukung jawaban berbentuk langkah bernomor dan catatan penting;
- tautan dari tombol bantuan mengambang langsung mencari topik halaman, misalnya `template sertifikat`;
- tombol mini `?` tersedia pada field penting di pencarian, pengaturan desain, persiapan, serta detail sertifikat;
- hover selama satu detik, klik, fokus keyboard, Escape, klik luar, resize, scroll, dan layar sentuh ditangani;
- tooltip tidak ikut tercetak;
- halaman template menampilkan status **LibreOffice server aktif** tanpa mengekspos path server;
- `.env` lokal menunjuk ke executable LibreOffice Windows;
- template asli sekolah telah melewati sanitasi token dan konversi LibreOffice nyata.

Tombol bantuan mengambang tetap tersedia pada seluruh menu melalui layout global. Tombol mini per-field pada tahap ini dipasang pada menu Sertifikat, sesuai fokus upgrade. Menu lain dapat memakai komponen yang sama pada tahap berikutnya tanpa membuat sistem bantuan baru.

## Perubahan database

Tidak ada migrasi, `migrate:fresh`, seeder ulang, atau perubahan data bisnis. Aktivasi LibreOffice hanya menambah konfigurasi lokal `.env`. Pengujian template asli memakai proses sementara dan tidak membuat template/default baru di database kerja.

## Aktivasi LibreOffice

Instalasi terdeteksi:

- LibreOffice `26.8.2.2`;
- executable `C:/Program Files/LibreOffice/program/soffice.exe`;
- konfigurasi lokal `CERTIFICATE_LIBREOFFICE` sudah aktif;
- cache konfigurasi Laravel sudah dibersihkan.

Konversi dijalankan secara headless dengan profil LibreOffice sementara yang terpisah untuk setiap proses. Hal ini mencegah benturan antara permintaan aplikasi dan LibreOffice yang sedang dibuka pengguna.

## Pemeriksaan template asli sekolah

Berkas depan yang diperiksa:

`C:/Users/jorda/Downloads/sertifikat_kepala_sekolah_dan_industri.docx`

Hasil:

- DOCX valid dan dapat dibersihkan dari binding mail merge lama;
- 18 token aplikasi dikenali;
- token wajib nama siswa, NIS, nomor, periode, kepala sekolah, pimpinan industri, dan NIP tersedia;
- font yang benar-benar dipakai pada teks dokumen adalah Arial, Lucida Bright, dan Lucida Calligraphy; ketiganya tersedia di Windows;
- dipasangkan dengan DOCX contoh daftar nilai pembimbing industri, kedua sisi berhasil dikonversi;
- masing-masing sisi tepat satu halaman A4 portrait;
- hasil gabungan dua halaman valid, berukuran 153.484 byte, diawali `%PDF`, dengan SHA-256 `8f866699ad545cd05f07d2ef16c91b4c82858a42701e26388d4a1aeede0d80fc`.

Uji tersebut memakai data pendek sintetis dan tidak menyimpan desain baru ke database. Dengan demikian default jurusan, sertifikat, dan arsip pengguna tidak berubah.

## Pengujian pengembang

- Tes bantuan + sertifikat terarah: 23 tes / 373 assertion, lulus sebelum regresi penuh.
- Regresi penuh terakhir: 180 tes / 3.126 assertion, lulus dalam 123,17 detik.
- Smoke browser fresh database/storage: lulus, termasuk FAQ, hover satu detik, klik/Escape tooltip, mobile, kiosk, jurnal, timeline, monitoring, penilaian, template, dua pratinjau, penerbitan, dan unduhan dua PDF.
- Konversi LibreOffice asli melalui service aplikasi: lulus.
- Kompilasi seluruh Blade: lulus.

Smoke browser pertama menemukan klik tooltip langsung tertutup karena event fokus terjadi lebih dahulu. Toggle diperbaiki agar fokus membuka panel sementara, klik menguncinya terbuka, dan klik kedua menutupnya. Smoke browser kemudian diulang dari awal dan lulus.

## Cara menguji bantuan

1. Login sebagai koordinator.
2. Buka **FAQ & Panduan**, cari `template sertifikat`.
3. Buka pertanyaan **Apa contoh isian untuk menguji template asli sekolah?**.
4. Buka **Sertifikat → Desain Cetak & Penandatangan**.
5. Arahkan mouse ke tombol `?` di samping **Format cetak** selama satu detik.
6. Pastikan tooltip muncul; gerakkan mouse keluar dan pastikan menutup.
7. Klik tombol yang sama dan pastikan tooltip tetap terbuka; tekan Escape untuk menutup.
8. Ulangi pada HP dengan mengetuk tombol.
9. Pastikan halaman menampilkan `LibreOffice server aktif`.

## Cara menguji template asli sekolah

Gunakan data demo dan jangan jadikan desain default.

1. Pilih sumber **DEMO siap uji — Kuliner 80/20**.
2. Nama desain: `UJI 03 - Template Asli Sekolah`.
3. Jurusan asal: `Kuliner`.
4. Depan: **Kepala sekolah dan industri**.
5. Belakang: **Pembimbing industri**.
6. Isi identitas sekolah sesuai data uji.
7. Pilih format nilai **80/20** dan petakan Soft skills, Hard skills, Pengembangan hard skills, Kewirausahaan, serta Laporan PKL ke field dengan nama yang sama.
8. Pilih **DOCX teks saja → PDF lewat LibreOffice**.
9. DOCX depan: `sertifikat_kepala_sekolah_dan_industri.docx` dari Downloads.
10. DOCX belakang: unduh **Pembimbing industri — DOCX** dari contoh pada halaman.
11. Lepaskan centang default, lalu simpan.
12. Buka **Coba revisi contoh**, pilih desain tersebut, isi data cetak, dan klik **Pratinjau PDF**. Jangan ajukan dahulu.
13. Periksa pusat nama, NIS, nomor, periode, bidang pekerjaan, dan area tanda tangan pada dua halaman.

Setelah versi teks diterima, buat desain baru memakai **DOCX teks asli + PDF dekorasi**. PDF dekorasi depan/belakang harus hanya berisi ornamen. Sistem kemudian menghasilkan versi digital berdekorasi dan versi cetak bersih dari data yang sama.

## Format koreksi

```text
Upgrade: Bantuan sertifikat + LibreOffice
Bagian: FAQ / tombol ? / upload DOCX / pratinjau / cetak
Langkah yang gagal: ...
Pesan error lengkap: ...
Hasil aktual: ...
Harapan: ...
Nama file, browser, dan printer: ...
```

Untuk masalah tata letak, sebutkan elemen yang bergeser, arah/perkiraan jarak, serta apakah pergeseran sudah terlihat di PDF atau hanya muncul pada hasil printer.

## Status flag dan batas

Menu Sertifikat tetap `stable`; tidak ada flag bug baru. Konfigurasi serta konversi LibreOffice nyata sudah lulus. Penerimaan presisi visual template asli dan kertas fisik tetap merupakan UAT pengguna.

Belum ada PDF dekorasi asli sekolah yang ditetapkan pada upgrade ini. Sistem tidak membuat dekorasi fiktif dan tidak mengubah default jurusan secara otomatis.

## Roadmap lanjutan

1. Pengguna menguji pratinjau template asli dengan langkah di atas.
2. Bila posisi salah, koreksi DOCX pada elemen yang disebutkan lalu buat versi desain baru.
3. Setelah PDF teks diterima, masukkan PDF dekorasi resmi depan dan belakang.
4. Setelah kedua keluaran diterima, lanjutkan pemusatan identitas dan pola penandatangan Tahap 3.

Penambahan tombol mini pada menu selain Sertifikat dilakukan bertahap setelah alur sertifikat diterima, menggunakan komponen yang sama.
