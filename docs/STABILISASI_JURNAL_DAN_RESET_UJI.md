# Stabilisasi jurnal dan reset database pengujian

Pemeriksaan: 23 Oktober 2026. Lingkup tahap ini adalah jurnal siswa, review sekolah, perangkat industri, QR, dan ekspor. Bukan klaim bahwa sertifikat/timeline/monitoring/dashboard sudah selesai refactor.

## Hasil dan perbaikan

- 90 tes / 1.724 assertion lulus pada SQLite in-memory. Tes bersifat regresi, bukan jaminan tidak akan ada bug baru.
- Smoke test browser Edge lulus dengan tiga konteks terpisah: koordinator, siswa, dan Dodi. Browser Dodi memakai viewport mobile; bukan HP/Google Camera nyata.
- `composer audit` tidak menemukan advisory yang diketahui pada dependency terpasang.
- Database kerja diperiksa read-only: 30 siswa, 30 roster, 18 penempatan; seluruh migrasi sudah diterapkan. Tidak ada fresh/reseed pada database kerja dalam tahap ini.

Yang diperbaiki:

1. GET/HEAD tidak bisa mengonsumsi QR atau membuat validasi. Perubahan hanya POST dengan CSRF; telah diuji juga di HTTP browser sungguhan.
2. Nonaktifkan pembimbing memakai status tujuan eksplisit. Klik/kirim ulang tidak mengaktifkan kembali; pencabutan perangkat berulang tidak menimpa waktu pencabutan pertama.
3. Kesalahan kiosk 403/404/409/410/419/429/500 memberi petunjuk, bukan layar SQL/debug. Respons tidak boleh dicache dan tidak mengirim referrer.
4. Filter tanggal satu sisi bekerja pada daftar, Excel, dan PDF. Tanggal tidak valid atau rentang terbalik ditolak.
5. Jadwal roster diperiksa ulang dalam transaksi setelah unggahan; perubahan jadwal saat pengisian tidak diabaikan.
6. Editor mempertahankan mode edit setelah gagal validasi; “Entri tanggal lain” membersihkan data dan versi lama. Pesan jam/nilai/foto utama memakai bahasa Indonesia.
7. PDF di atas 300 entri ditolak dengan petunjuk, bukan dipotong. Excel menyertakan Rincian cetak untuk narasi panjang; nilai asli tidak diganti. Hasil unduhan diuji dapat dibuka kembali sebagai XLSX.
8. Seeder dapat dijalankan ulang tanpa benturan tanggal jurnal di SQLite. Data baru memiliki contoh nilai, waktu, dan versi review konsisten. Seeder tidak membuat scan/pengesahan industri palsu.
9. Upgrade additive diuji pada data lama: siswa, roster, penempatan, jurnal, foto, dan sertifikat tetap utuh. Tidak memalsukan nilai/validasi industri untuk jurnal lama.

## Kapan memakai migrate:fresh?

| Keadaan | Keputusan |
| --- | --- |
| Memakai perbaikan tahap ini di database kerja | **Tidak perlu fresh.** Tidak ada migrasi baru pada tahap stabilisasi; migrasi jurnal sebelumnya sudah Ran. |
| Database lingkungan lain belum menerima migrasi jurnal | Backup, kemudian `php artisan migrate`; jangan fresh. |
| Mengulang tes otomatis | `php artisan test` menggunakan SQLite in-memory sesuai phpunit.xml; jangan arahkan variabel DB pengujian ke database kerja. |
| Ingin menguji alur demo dari kosong | Jalankan smoke test terisolasi di bawah; ia membuat database sementara baru, bukan menghapus yang lama. |
| Persiapan demonstrasi/UAT customer dari 30 siswa bersih | Fresh **boleh setelah kode diuji, backup dibuat dan diverifikasi, serta pemilik mengonfirmasi seluruh data demo boleh dihapus**. Waktu yang disarankan: tepat sebelum sesi UAT, bukan setiap perbaikan kode. |
| Sudah berisi data siswa/jurnal asli | Jangan fresh. Gunakan migrasi additive dan prosedur koreksi/backup. |

Reset kerja belum dijalankan. Backup lama bukan pengganti backup terbaru: masukan sejak backup itu akan hilang jika di-reset.

## Checklist reset demo yang disengaja

1. Pastikan hanya lingkungan local/testing, database tujuan benar, dan semua isinya boleh dibuang. Hentikan input sementara.
2. Backup database dengan `scripts/backup-database.php` dan cadangkan `storage/app/private` (foto/dokumen), template yang dipakai, serta `.env`/APP_KEY ke lokasi aman. Jangan publikasikan backup atau kunci. Uji pemulihan backup pada database terpisah sebelum reset yang bernilai penting.
3. Catat bahwa **fresh menghapus seluruh tabel database tujuan** termasuk perangkat, undangan, validasi, dan audit. Backup memungkinkan pemulihan, bukan undo otomatis.
4. Hanya setelah konfirmasi pemilik, jalankan pada database demo yang telah dicek:

   ```powershell
   php artisan migrate:fresh --seed
   php artisan db:seed --class=IndustryJournalDemoSeeder
   php artisan migrate:status
   php artisan simmag:audit-main-menu --year=2026/2027 --json
   php artisan test --compact
   ```

5. Seeder utama: 30 siswa pada delapan jurusan, 18 penempatan, variasi fase PKL, dua koordinator. Dodi (Demo) ditambahkan oleh seeder kedua. Jika tahun ajaran pengujian berubah, sesuaikan `--year`; tanggal fixture mengikuti hari seeding.
6. Akun staf demo: `koordinator@simmag.test`, `koordinator2@simmag.test`, password `password123`. Siswa NIS `2026001`–`2026030`, password awal `07112008`. Untuk alur Dodi yang sedang PKL, contoh NIS `2026007` pada PT Mitra Teknologi (Demo). Seluruhnya data fiktif, bukan kredensial produksi.
7. Daftarkan ulang browser Dodi. Cookie/QR sebelum reset tidak berlaku di database baru. Jangan membuat validasi palsu melalui seeder.
8. Fresh tidak menghapus foto di disk. Jangan menghapus seluruh storage untuk “membersihkan”; berkas lama mungkin masih diperlukan backup. Penanganan orphan harus menjadi operasi terpisah yang terukur.

## Smoke test browser tanpa menyentuh database kerja

Memerlukan PHP, Node, npm, dan Microsoft Edge. Dependency browser diinstal ke direktori OS sementara, bukan composer/npm aplikasi. Perintah pertama memerlukan koneksi internet.

```powershell
$journalTools = Join-Path ([System.IO.Path]::GetTempPath()) ('simmag-browser-tools-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $journalTools
npm.cmd install --prefix $journalTools --no-audit --no-fund playwright jsqr pngjs
node scripts/journal-browser-smoke.cjs $journalTools
```

Script membuat direktori `simmag-journal-smoke-*` baru di OS temporary directory, SQLite baru, storage/sesi/view cache terpisah, APP_KEY uji acak, dan server di port loopback acak. Fixture menolak reset jika file database sudah ada dan menolak direktori di luar pola/temp root. Server/browser dihentikan setelah tes; server aplikasi utama tidak dihentikan. Tersimpan `result.json` dan screenshot siswa sebagai bukti pada direktori yang dicetak di terminal. Data semuanya fiktif. Helper fixture hanya untuk CLI/development server pengujian, bukan route aplikasi produksi.

Skenario browser: login NIS dan koordinator; QR aktual dapat didekode; enrollment otomatis; HttpOnly cookie; POST tanpa CSRF ditolak; HEAD tidak memvalidasi; nilai desimal; validasi otomatis; edit membatalkan QR lama; perbaikan/reset form setelah validasi gagal; ekspor tanggal akhir saja; pencabutan perangkat. Tidak ada error JavaScript tak tertangani pada run yang lulus.

## Gerbang sebelum tag STABLE produksi

Tag jurnal tetap UNSTABLE dengan keterangan **tes otomatis lulus, menunggu UAT**. Jangan sekadar menghapus tanda untuk terlihat selesai.

- [x] Tes regresi, migrasi data lama, seeder berulang, dan fresh database uji lulus.
- [x] Browser terpisah + cookie + CSRF + QR aktual + unduhan Excel lulus.
- [ ] Uji HP siswa dan HP Dodi melalui domain yang benar, Google Camera/Lens membuka browser terdaftar, termasuk kehilangan cookie dan daftar ulang.
- [ ] Uji deployment HTTPS yang akan dipakai, proxy/secure cookie, akses jaringan, dan redaksi URL QR dari log server. HTTP hanya untuk dev local.
- [ ] Buka Excel di perangkat sekolah dan cetak enam/tujuh entri, nilai kosong/0/desimal, narasi panjang + lembar Rincian, serta nama panjang. Verifikasi keterbacaan dan tanda validasi bersama customer.
- [x] Uji transaksi MySQL lokal terpisah: dua tab siswa, dua scan bersamaan, perubahan isi saat scan, pencabutan saat scan, pendaftaran perangkat bersamaan, dan koreksi jadwal saat simpan. Enam skenario lulus pada 25 Oktober 2026. [Perintah dan bukti](UJI_MYSQL_JURNAL.md).
- [ ] Ulangi pengujian di staging dengan konfigurasi deployment dan beban sekolah. Uji lokal enam skenario bukan bukti kapasitas atau seluruh kemungkinan urutan transaksi.
- [ ] Uji backup/restore database dan foto di lingkungan terpisah.

Setelah semua gerbang lulus, catat tanggal, perangkat, versi aplikasi, dan hasil sebelum mengubah status. Sertifikat masih prioritas P0 (snapshot dokumen); bug modul lain tetap tercatat di AUDIT_MENU_UTAMA.md dan tidak disamarkan sebagai selesai.
