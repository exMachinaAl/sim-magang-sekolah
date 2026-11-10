# LibreOffice sertifikat pada Debian 13

## Kesimpulan audit

Konverter sertifikat sudah kompatibel dengan Linux. Aplikasi menjalankan LibreOffice secara headless melalui Symfony Process, memakai profil sementara unik untuk setiap render, memberi timeout 60 detik, memeriksa bahwa DOCX depan dan belakang masing-masing tepat satu halaman A4 portrait, lalu menggabungkannya menjadi PDF.

Instalasi LibreOffice pada Debian tidak otomatis mengaktifkan fitur aplikasi. SIMMAG tetap memakai path absolut dari `CERTIFICATE_LIBREOFFICE` agar binary yang dijalankan tidak bergantung pada `PATH` milik shell/AppCtl yang dapat berbeda dari akun PHP.

## Konfigurasi server

Jalankan di Debian:

```bash
command -v soffice
soffice --headless --version
```

Hasil `command -v` umumnya `/usr/bin/soffice`. Gunakan hasil aktual tersebut pada environment service AppCtl, bukan path Windows:

```dotenv
CERTIFICATE_LIBREOFFICE=/usr/bin/soffice
```

Kemudian dari release aktif:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan simmag:check-deployment --target=production
```

Restart service PHP/AppCtl setelah environment berubah. Jangan menyalin `.env` Windows ke Debian dan jangan mengubah `APP_KEY` instalasi yang sudah dipakai.

Pemeriksaan deployment menampilkan `libreoffice_converter` sebagai advisory. Nilai berikut yang benar:

- `Executable LibreOffice tersedia dan dapat dijalankan.` bila konfigurasi aktif;
- konverter opsional tidak aktif bila variabel kosong;
- peringatan path/izin bila variabel berisi path Windows lama atau file yang tidak executable.

Status halaman **Sertifikat → Desain Cetak & Penandatangan** juga hanya menyatakan aktif bila file ada dan executable bagi proses PHP.

## Izin dan folder sementara

Tidak perlu menjalankan service LibreOffice terpisah. Akun PHP/AppCtl harus dapat:

- menjalankan binary `soffice`;
- menulis `storage/app/private/certificate-render`;
- menulis direktori `storage/framework` dan log aplikasi.

Jangan memakai `chmod -R 777`. Berikan kepemilikan/izin hanya kepada akun service aktual. Setiap proses memakai direktori dan profil sementara sendiri, lalu dibersihkan setelah berhasil maupun gagal.

## Font wajib diperiksa

LibreOffice terpasang belum menjamin tata letak sama dengan Windows. Template asli yang pernah diperiksa memakai **Arial, Lucida Bright, dan Lucida Calligraphy**. Font tersebut belum tentu tersedia pada Debian. Bila font hilang, LibreOffice melakukan substitusi dan posisi teks dapat bergeser walaupun konversi berhasil.

Periksa sebagai akun service:

```bash
fc-match Arial
fc-match "Lucida Bright"
fc-match "Lucida Calligraphy"
```

Pasang hanya font yang sekolah miliki hak penggunaannya, lalu jalankan `fc-cache -f`. Alternatifnya, ubah template memakai font metrically compatible yang memang tersedia dan lakukan UAT ulang. Aplikasi tidak menanam atau mengunduh font secara otomatis.

## Uji setelah deployment

1. Login sebagai koordinator dan buka **Sertifikat → Desain Cetak & Penandatangan**.
2. Pastikan tampil `LibreOffice server aktif`.
3. Gunakan desain uji, jangan langsung mengubah default jurusan.
4. Pilih **DOCX teks saja → PDF lewat LibreOffice** atau **DOCX teks asli + PDF dekorasi**.
5. Unggah DOCX depan dan belakang, lalu jalankan **Pratinjau PDF**.
6. Periksa bahwa PDF memiliki dua halaman A4 portrait dan tidak ada teks terpotong.
7. Periksa posisi nama, NIS, nomor sertifikat, tanggal, nilai, serta tanda tangan pada skala 100%.
8. Cetak satu data demo pada printer sekolah sebelum memakai data asli.

PDF bawaan dan PDF aplikasi + dekorasi tetap berfungsi bila LibreOffice tidak aktif. Yang ditolak hanya renderer yang memang membutuhkan konversi DOCX.

## Troubleshooting singkat

- `LibreOffice server belum dikonfigurasi`: periksa environment AppCtl, path `command -v soffice`, config cache, dan restart service.
- `Konversi DOCX gagal`: jalankan `soffice --headless --version` sebagai akun service, periksa izin storage dan log Laravel.
- PDF lebih dari satu halaman per sisi: rapikan margin/isi DOCX; aplikasi sengaja menolak dan tidak memotong halaman.
- Layout berbeda dari Windows: periksa hasil `fc-match`; penyebab paling umum adalah font substitusi.
- Konversi melewati 60 detik: periksa template, beban server, proses LibreOffice yang macet, dan kapasitas disk; jangan menaikkan timeout sebelum penyebab diketahui.

