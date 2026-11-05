# Upgrade FAQ dan petunjuk kontekstual — 6 November 2026

## Hasil

Menu **FAQ & Panduan** dan tombol bantuan bulat `?` sudah tersedia pada seluruh halaman interaktif yang memakai layout akun SIMMAG. Konten menyesuaikan role siswa, guru, koordinator, dan admin serta halaman yang sedang dibuka. Status fitur: **STABLE untuk penggunaan lokal**.

Dokumen unduhan seperti PDF, DOCX, Excel, CSV, foto privat, serta halaman kiosk industri tidak diberi tombol mengambang karena bukan halaman menu akun. Petunjuk untuk alur tersebut tetap tersedia pada menu asal dan FAQ role terkait.

Tidak ada migrasi, `migrate:fresh`, reseed, perubahan akun, atau perubahan data kerja.

## Sebelum dan sesudah

| Sebelum | Sesudah |
| --- | --- |
| Menu Panduan berisi satu halaman statis yang terutama membahas Jurnal dan Sertifikat. | Menu menjadi **FAQ & Panduan** dengan bagian umum dan bagian khusus role. |
| Pengguna harus keluar dari halaman kerja untuk mencari petunjuk. | Setiap halaman akun memiliki tombol bulat `?` dengan penjelasan halaman, langkah singkat, batas role, dan tautan menuju FAQ. |
| Tidak ada bantuan melalui hover. | Pada komputer, hover selama 1 detik membuka panel; klik juga membuka/menutup panel. |
| Belum ada interaksi keyboard yang khusus. | Tombol memiliki label aksesibel, status `aria-expanded`, panel dialog nonmodal, tombol tutup, dan dukungan Escape/klik luar. |
| FAQ tidak dapat dicari. | Pencarian langsung menyaring pertanyaan berdasarkan kata kunci tanpa request tambahan. |
| Petunjuk sama untuk semua orang. | Siswa, guru, koordinator, dan admin mendapat penekanan alur dan batas wewenang yang berbeda. |

## Cakupan role

| Role | Fokus FAQ |
| --- | --- |
| Siswa | Jadwal otomatis, pengisian Jurnal, QR validasi industri, nilai, dan Sertifikat. |
| Guru pembimbing | Siswa yang ditugaskan, pendampingan kasus, review opsional, Monitoring, serta larangan input nilai industri. |
| Koordinator PKL | Data siswa, batch jadwal, Penempatan, review, kiosk industri, template/nilai, Sertifikat, distribusi, dan audit ID editor. |
| Admin sistem | Akun, role, status aktif, keamanan akses, serta batas antara administrasi sistem dan proses bisnis koordinator. |

Petunjuk halaman tersedia untuk Dashboard, Timeline, Jurnal, pembimbing industri/kiosk, Monitoring, Penilaian, bukti dan template penilaian, Sertifikat, template/persiapan/detail Sertifikat, Data Siswa, Kontrol PKL, Penempatan, Perusahaan, Periode/tag, Laporan, Pengguna, profil keamanan, dan FAQ. Route baru yang belum memiliki isi khusus tetap memperoleh petunjuk umum sehingga tombol tidak hilang.

## Pengujian pengembang

- Tes fitur bantuan: **4 tes lulus / 54 assertion**.
- Regresi lengkap: **172 tes lulus / 3.016 assertion**, 123,09 detik.
- Smoke browser pada database SQLite baru dan terisolasi: lulus untuk hover tertunda, klik, Escape, pencarian FAQ, konten koordinator, panel terbuka pada viewport 390×844, dan seluruh alur lama termasuk kiosk serta Sertifikat.
- Inspeksi visual mobile: panel bantuan terbuka berada seluruhnya di dalam layar, dapat digulir, dan tidak membuat horizontal overflow.
- Probe kapasitas MySQL lokal: lulus; waktu probe terlama 895,57 ms, database sementara dihapus, database kerja tidak berubah, dan memori puncak 66 MB.
- Sintaks PHP, sintaks JavaScript, route `guide`, tautan dokumentasi lokal, dan whitespace diperiksa.

Artefak browser sintetis terakhir berada di `C:\Users\jorda\AppData\Local\Temp\simmag-journal-smoke-mDiiL3`. Tangkapan panel terbuka bernama `faq-context-help-open-mobile.png`. Artefak sementara ini tidak berisi data sekolah asli.

## Cara uji manual

### A. Menu FAQ per role

1. Login memakai satu role.
2. Pastikan sidebar menampilkan **FAQ & Panduan**.
3. Buka menu tersebut dan periksa judul bagian role:
   - siswa: **Panduan siswa**;
   - guru: **Panduan guru pembimbing**;
   - koordinator: **Panduan koordinator PKL**;
   - admin: **Panduan admin sistem**.
4. Ketik `sertifikat`, `jurnal`, atau `kata sandi` pada pencarian. Hanya pertanyaan yang cocok yang boleh terlihat.
5. Klik **Bersihkan** untuk menampilkan seluruh pertanyaan kembali.

### B. Tombol bantuan pada setiap menu

1. Buka Dashboard, lalu beberapa menu lain yang tersedia bagi role tersebut.
2. Pastikan tombol bulat `?` terlihat di kanan bawah.
3. Pada komputer, arahkan kursor ke tombol selama sekitar satu detik. Panel harus terbuka tanpa klik.
4. Jauhkan kursor; panel hover harus tertutup. Klik tombol agar panel tetap terbuka.
5. Tekan Escape, tombol `×`, atau klik di luar panel untuk menutup.
6. Pastikan judul dan isi berubah mengikuti halaman. Contoh: Kontrol PKL menjelaskan tanggal roster, sedangkan Penilaian menjelaskan nilai dari industri.

### C. Ponsel

1. Buka aplikasi pada lebar layar ponsel.
2. Ketuk tombol `?`; panel harus berada di dalam layar dan dapat digulir.
3. Pastikan tombol tidak menutupi tombol simpan yang sedang digunakan. Tutup panel sebelum melanjutkan formulir bila area bawah diperlukan.

## Batas saat ini

Isi FAQ dikelola melalui kode agar konsisten dengan aturan bisnis dan ikut diuji. Belum ada editor FAQ bebas di aplikasi; perubahan isi dilakukan sebagai upgrade terkontrol agar petunjuk tidak bertentangan dengan hak akses atau alur yang berlaku.

## Roadmap setelah koreksi pengguna

1. Catat koreksi istilah atau langkah bantuan dari pengguna tanpa mengubah aturan bisnis diam-diam.
2. Pertahankan pengujian bantuan pada setiap penambahan menu/route baru.
3. Lanjutkan roadmap deployment: domain HTTPS tetap, pemulihan SQL + ZIP foto, dan load test HTTP staging.
4. UAT cetak fisik Sertifikat tetap menjadi pengujian output terpisah ketika pengguna dapat melakukannya.
