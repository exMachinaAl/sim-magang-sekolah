# Fresh database demo — 25 Oktober 2026

Dilakukan setelah pengguna menyatakan seluruh data fiktif dan mengizinkan fresh. Target diverifikasi: MySQL lokal `127.0.0.1`, database `simmag`, lingkungan `local`.

- Backup sebelum reset: `storage/app/private/backups/archive-probe-20261025-142649-e7dcb4ccfefc799b.sql`, dengan laporan JSON bernama sama. Pemulihan terpisah lulus: struktur/isi semua tabel cocok dan enam berkas arsip konsisten. Database uji dihapus.
- Pemeriksaan backup semula mendeteksi perbedaan penghitung ID pada tiga tabel. Metadata `INFORMATION_SCHEMA` masih menyimpan nilai lama setelah insert/delete; isi data cocok. Pemeriksa diperbaiki agar membaca penghitung aktual dari `SHOW CREATE TABLE`, lalu backup/pemulihan diulang dan lulus sebelum fresh.
- Aplikasi ditempatkan sementara dalam maintenance. `migrate:fresh --seed` dijalankan, lalu seeder Dodi, preview sertifikat, DOCX, template PDF berdekorasi, monitoring, dan bukti penilaian. Maintenance dinonaktifkan kembali.
- Hasil: **31 siswa/roster, 19 penempatan, 3 sertifikat, 0 sertifikat terbit tanpa arsip aktif**, 0 ketidakcocokan identitas penempatan–roster. Pemeriksaan integritas: delapan berkas, tidak ada kegagalan.
- Perangkat, undangan dan validasi industri kosong. Tidak dibuat scan palsu; browser Dodi harus didaftarkan ulang. HTTPS memakai nama cookie sesi baru sehingga pengguna login ulang.
- Flag Sertifikat menjadi **UNSTABLE** untuk cetak/UAT, pemulihan server dan kapasitas staging; alasan arsip lama merah sudah selesai pada database demo ini. Database lain yang masih berisi dokumen lama tetap membutuhkan pemeriksaan tersendiri.
- Setelah penyesuaian verifikasi backup dan HTTPS, regresi aplikasi lulus **159 tes / 2.914 assertion**. Pemeriksaan HTTP lokal melalui TLS juga lulus; perangkat HP masih menunggu pemasangan CA/firewall dan uji pengguna.

## Residu data versus struktur kompatibilitas

Fresh menghapus data lama, lalu membangun kembali struktur dari seluruh migrasi. Ini tidak menghapus tabel yang masih didefinisikan migrasi.

| Struktur yang masih ada | Kondisi setelah reset | Alasan belum dihapus |
| --- | --- | --- |
| `timeline_events`, `timeline_event_completions` | Keduanya kosong | Endpoint timeline manual sudah dihentikan (410), tetapi model/binding/migrasi historis masih ada. Timeline aktif memakai roster/tanggal. |
| `assessment_criteria`, `assessment_scores` | Keduanya kosong | Model dan pembacaan sertifikat masih mendukung penilaian skema lama; seeder baru memakai template/field penilaian. |

Penghapusan struktur ini memerlukan penghapusan dependensi kode, jalur migrasi dan penyesuaian tes secara terpadu. Tidak dilakukan dengan sekadar DROP tabel pada database kerja. Berkas upload lama di filesystem tidak otomatis dihapus oleh fresh; backup dan file privat dibiarkan utuh.

Panduan HTTPS/HP berikutnya: [HTTPS kiosk lokal](HTTPS_KIOSK_LOKAL.md).
