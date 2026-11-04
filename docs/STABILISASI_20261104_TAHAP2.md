# Stabilisasi Jurnal dan Sertifikat — tahap 2, 4 November 2026

## Hasil upgrade

Seluruh menu utama kini berstatus **STABLE untuk cakupan audit dan penggunaan lokal**. Flag UNSTABLE Jurnal serta Sertifikat dilepas karena temuan kode yang menjadi penyebab flag sudah ditutup. Status ini tidak menyatakan server produksi siap; kesiapan deployment dicatat terpisah pada roadmap.

Jurnal Excel kini menetapkan A4 landscape, satu lembar per maksimal enam entri, skala satu halaman, posisi cetak terpusat horizontal, margin kiri/kanan/atas/bawah 8 mm, dan gridline cetak dimatikan. Lembar Rincian tetap A4 portrait, terpusat, dengan margin 12 mm dan tinggi halaman tidak dipaksa agar narasi tidak dipotong.

Sertifikat menggunakan perbaikan tahap sebelumnya: nama dan blok identitas berada di tengah pada PDF bawaan/dekorasi, ringkasan halaman belakang terpusat, arsip versi lama tidak berubah, dan distribusi menolak PDF yang gagal pemeriksaan hash.

Tidak ada migrasi, fresh, reset, reseed, atau perubahan data kerja. Contoh Excel dibuat dalam transaksi sementara dan seluruh penambahan contoh di-rollback.

## Pengujian pengembang

- Tes terarah Jurnal dan audit menu setelah perubahan status: **22 tes lulus / 965 assertion**. Tes demo Sertifikat dan audit badge setelah koreksi ekspektasi: **7 tes lulus / 138 assertion**.
- Tes membuktikan pengaturan margin/pemusatan Excel tetap ada setelah file XLSX benar-benar disimpan dan dibuka ulang.
- Pengujian yang sudah lulus pada tahap sebelumnya tetap menjadi bukti: kiosk HTTPS di HP, validasi jurnal, pemulihan database/foto, 10 skenario transaksi Sertifikat MySQL, PDF dua halaman, serta 165 tes/2.934 assertion.
- Regresi lengkap tahap 2: **165 tes lulus / 2.932 assertion**, 217,52 detik. Log: `storage/logs/stabilization-stage2-full-20261104.log`.

## Yang perlu diuji pengguna

### A. Sertifikat

1. Buka [PDF bawaan](../storage/app/private/pratinjau-tengah-bawaan-20261103.pdf) pada zoom 100%.
2. Buka [PDF dekorasi](../storage/app/private/pratinjau-tengah-dekorasi-20261103.pdf) pada zoom 100%.
3. Pada halaman depan, nama penerima harus di tengah. Kolon identitas harus segaris di pusat; label berada di kiri dan nilai di kanan.
4. Pada halaman belakang, identitas penerima harus terpusat dan tabel nilai tidak melewati halaman.
5. Bila dicetak, gunakan A4 dan skala 100%. Periksa arah halaman belakang serta posisi tanda tangan.

### B. Jurnal Excel

**Status pengguna: LULUS / cukup bagus, 4 November 2026.** Checklist berikut dipertahankan sebagai rekam pengujian.

1. Buka [contoh Excel tujuh entri](../storage/app/private/pratinjau-jurnal-7-entri-20261104.xlsx).
2. `Jurnal 1` harus memuat enam entri dan `Jurnal 2` satu entri. Keduanya A4 landscape dan berada di tengah halaman cetak.
3. Nilai `88,25`, `0`, dan `74,99` harus tetap angka persis; nilai di bawah 75 diberi keterangan dan tidak dipindahkan ke predikat yang salah.
4. Entri pertama harus merujuk ke lembar `Rincian`; teks lengkap harus tersedia di lembar tersebut tanpa terpotong.
5. Pratinjau cetak harus menampilkan margin kiri/kanan seimbang dan tidak mencetak gridline Excel.

Koreksi cukup menyebut `A` atau `B`, nomor langkah, hasil aktual, dan hasil yang diharapkan. Untuk hasil cetak, sertakan aplikasi pembuka, ukuran kertas, serta opsi skala. Data contoh seluruhnya fiktif.

## Status flag

- Jurnal & Foto: **STABLE untuk audit/penggunaan lokal**; tag UI dilepas.
- Sertifikat: **STABLE untuk audit/penggunaan lokal**; tag UI dilepas.
- Dashboard, Timeline, Monitoring, dan Penilaian tetap STABLE.
- Kesiapan produksi belum disimpulkan dari status menu. README masih menyatakan proyek perlu uji deployment sebelum data sekolah asli digunakan.

## Roadmap setelah koreksi

1. Catat hasil koreksi PDF Sertifikat. Excel Jurnal sudah diterima pengguna; bila kelak ada ketidaktepatan printer tertentu, buka temuan khusus output tersebut.
2. Siapkan deployment staging dengan domain/HTTPS tetap, secure cookie, konfigurasi kunci dan penyimpanan privat. Pemeriksaan dan template tersedia pada [panduan staging](DEPLOYMENT_STAGING.md).
3. Pulihkan pasangan backup SQL + ZIP foto pada server staging baru, lalu cocokkan arsip sertifikat dan foto jurnal.
4. Uji kapasitas memakai volume setara satu angkatan: pencarian, ekspor jurnal, pembuatan sertifikat, dua koordinator, dan scan kiosk bersamaan.
5. Setelah staging dan UAT lulus, lakukan penilaian kesiapan produksi serta prosedur backup terjadwal. Fitur bisnis baru dibahas sebelum masuk roadmap.

## Hasil akhir regresi tahap 2

- `php artisan test --compact`: **LULUS**, 165 tes / 2.932 assertion.
- Audit baca-saja `2026/2027`: keenam modul `stable`; 31 roster, 19 penempatan, 12 tanpa penempatan yang sah, 0 penempatan tanpa roster, 0 ketidakcocokan identitas, 0 sertifikat kehilangan jadwal, dan 0 sertifikat terbit tanpa arsip aktif.
- Contoh Excel: empat sheet, `Jurnal 1` area cetak `A1:L23`, landscape, pusat horizontal, margin kiri/kanan 8 mm; entri ketujuh terdapat pada `Jurnal 2`; lembar Rincian tersedia.
- Pemeriksaan transaksi setelah contoh dibuat: 0 jurnal contoh tanggal 7–13 Desember tersisa pada database kerja.
- `git diff --check`, kompilasi Blade, dan pemeriksaan tautan dokumentasi lokal lulus. Tidak ada migrasi/reset.

