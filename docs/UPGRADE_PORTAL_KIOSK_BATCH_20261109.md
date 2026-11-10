# Upgrade Portal Kiosk Batch — 9 November 2026

## Tujuan

Mengganti validasi jurnal yang terjadi segera setelah link QR dibuka menjadi alur Portal Kiosk yang dapat menangani banyak siswa secara berurutan. Perangkat pembimbing tetap harus didaftarkan lebih dahulu melalui QR sekali pakai.

## Sebelum dan sesudah

| Sebelum | Sesudah |
| --- | --- |
| Google Camera membuka satu link QR dan halaman langsung mengirim validasi. | QR jurnal membuka Portal Kiosk dan hanya mengambil pratinjau. |
| Tidak ada pemeriksaan eksplisit sebelum penyimpanan. | Nama, NIS, tanggal, kegiatan, area/jam, dan nilai tampil sebelum penyimpanan. |
| Satu scan berakhir di halaman hasil. | Scanner portal tetap aktif untuk siswa berikutnya. |
| Scan ulang bergantung pada halaman redirect. | Scan ulang revisi yang sama memberi informasi singkat, tanpa konfirmasi dan tanpa record/audit kedua. |

## Alur operasional

1. Koordinator membuat pembimbing industri, menghubungkan siswa, dan menampilkan QR pendaftaran perangkat.
2. Pembimbing memindai QR pendaftaran menggunakan HP dan browser miliknya. Browser didaftarkan dan diarahkan ke Portal Kiosk.
3. Pada Portal Kiosk, pembimbing mengizinkan kamera dan mengarahkan scanner ke QR jurnal siswa.
4. Portal menampilkan ringkasan. **OK, Validasi** menyimpan validasi; **Batal** menutup ringkasan tanpa perubahan database.
5. Scanner dilanjutkan untuk siswa berikutnya.
6. QR dengan jurnal dan revisi yang sudah disahkan hanya menampilkan informasi bahwa scan pernah dilakukan. Waktu dan audit pertama tidak ditimpa.

Google Camera tetap menjadi fallback. Link jurnal yang dibuka melalui Google Camera diarahkan ke Portal Kiosk pada browser terdaftar. Scanner bawaan portal memakai API browser `BarcodeDetector`; tidak ada dependency JavaScript eksternal yang ditambahkan.

## Keamanan dan kompatibilitas gateway

- Portal tetap menggunakan cookie perangkat terenkripsi, HttpOnly, dan SameSite=Lax.
- Path cookie mengikuti base path gateway: `/industry` atau `/simmag/industry`.
- Endpoint pratinjau dan validasi membutuhkan cookie perangkat, CSRF, HTTPS di non-local, pembimbing aktif, penugasan siswa yang sesuai, token QR valid, serta revisi jurnal yang sama.
- Pratinjau tidak menulis validasi. Penyimpanan hanya terjadi melalui POST konfirmasi.
- Validasi transaksi tetap idempotent dan memakai lock database.
- URL dibentuk dari host/prefix request aktif; tidak ada hostname tunnel atau IP yang ditanam di source.

Tidak ada migration, perubahan schema, atau penghapusan data pada upgrade ini.

## Pengujian otomatis

Jalankan:

```powershell
php artisan test --filter="IndustryJournalTest|GatewayCompatibilityTest|HelpCenterTest"
php artisan test
node --check public/js/industry-kiosk.js
```

Smoke test browser terisolasi juga mencakup pendaftaran Dodi, cookie, CSRF, pratinjau, tombol OK, scan ulang tanpa dialog, pencabutan perangkat, serta alur jurnal lain. Test memakai database SQLite sementara dan tidak menyentuh database kerja.

Hasil implementasi 9 November 2026:

- Regresi penuh terbaru setelah pemeriksaan deployment LibreOffice dan default periode aktif: **195 tes, 3.290 assertion lulus**.
- Tes terarah jurnal, gateway, dan bantuan: **34 tes, 1.035 assertion lulus**.
- Smoke test Edge dengan konteks koordinator, siswa, dan Dodi: lulus, termasuk enrolment, konfirmasi OK, scan ulang idempotent, pencabutan perangkat, dan tidak ada error JavaScript yang tidak tertangani.
- Pemeriksaan sintaks `industry-kiosk.js`, kompilasi Blade, dan `git diff --check`: lulus.

## Pengujian manual di HP

1. Buka **Jurnal & Foto → Pembimbing Industri & Perangkat QR** sebagai koordinator.
2. Buat QR pendaftaran baru, scan dari HP Dodi, dan pastikan halaman **Portal Kiosk** tampil.
3. Izinkan kamera. Bila browser tidak mendukung scanner portal, scan QR jurnal dengan Google Camera lalu buka link menggunakan browser yang sama.
4. Tampilkan QR jurnal siswa yang belum tervalidasi. Pastikan ringkasan muncul dan belum ada status tervalidasi sebelum tombol OK ditekan.
5. Tekan **Batal**. Muat detail jurnal dari akun siswa/koordinator dan pastikan masih belum tervalidasi.
6. Scan lagi, periksa isi, lalu tekan **OK, Validasi**. Pastikan waktu scan tercatat.
7. Scan QR yang sama sekali lagi. Harus muncul informasi sudah dipindai, tanpa dialog konfirmasi dan tanpa validasi kedua.
8. Ubah isi jurnal siswa dan simpan. QR lama harus ditolak; QR revisi baru harus kembali meminta konfirmasi.
9. Cabut perangkat dari akun koordinator. Portal/scan berikutnya harus ditolak.

## Batas yang perlu diketahui

- Akses kamera web membutuhkan HTTPS (kecuali localhost) dan izin kamera pengguna.
- Dukungan scanner langsung bergantung pada `BarcodeDetector` browser. Chrome Android terbaru direkomendasikan; Google Camera adalah fallback resmi.
- Pendaftaran membuktikan browser yang dipercaya, bukan identitas biometrik pemegang HP. Perangkat yang hilang harus segera dicabut oleh koordinator.

