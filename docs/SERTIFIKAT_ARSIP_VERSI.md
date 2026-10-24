# Sertifikat: kesiapan, arsip utuh, dan revisi versi

Pembaruan lanjutan: [Template cetak dan variasi penandatangan](SERTIFIKAT_TEMPLATE_CETAK.md) sudah tersedia. PDF bawaan siap; DOCX unggahan memerlukan LibreOffice yang belum terpasang pada mesin kerja. Bagian berikut mencatat tahap pengamanan arsip sebelumnya.

Tahap P0 — 24 Oktober 2026. Perbaikan mengikuti flag integritas dokumen, sambil pengujian kiosk dilakukan pengguna secara terpisah.

## Perubahan alur

1. Koordinator mencari nama/NIS di Sertifikat; tersedia filter tag tahun dan jurusan roster. Kandidat tidak dimuat sebelum pencarian dan dipaginasi sepuluh hasil. Rekap sertifikat dipaginasi lima belas hasil. Tidak ada dropdown seluruh siswa.
2. Saat pengajuan, sistem mengecek identitas, kecocokan roster/penempatan, tanggal berurutan, keikutsertaan PKL, nilai disahkan, kelengkapan ketentuan, dan hasil PDF tepat dua halaman. Kegagalan membatalkan seluruh pengajuan dalam transaksi.
3. Proposal menyimpan snapshot identitas/tanggal/nilai, HTML yang sudah dirender, PDF utuh, SHA-256, versi, dan ID koordinator. Kelas/jurusan berasal dari roster tahun penempatan, bukan profil terkini.
4. Sebelum menyetujui, sistem memeriksa ulang kesiapan dan mencocokkan sumber dengan proposal. Jika berubah, koordinator harus memeriksa lalu membuat versi baru; tidak menyetujui data berbeda lewat tab lama.
5. Setelah disahkan, unduhan mengambil **byte PDF arsip yang sama**, bukan menjalankan template lagi. Perubahan data master, jadwal, penilaian, maupun kode template tidak mengubah unduhan versi tersebut. Jika hash tidak cocok, unduhan ditolak; tidak otomatis membangun pengganti dari data hidup.
6. Revisi memerlukan alasan dan versi yang sedang dibuka. Proposal versi baru dibuat, persetujuan/distribusi dimulai ulang, jadwal pengambilan lama dibersihkan. Dokumen/keputusan/serah terima sebelumnya tetap ada. Dua tab tidak dapat menaikkan atau menyetujui versi lama sebagai versi terbaru.
7. PDF versi lama yang pernah disahkan tetap dapat diakses melalui riwayat oleh pihak yang berhak atas siswa tersebut. Label UI menandainya sebagai arsip, bukan dokumen terbaru. Penggantian/penarikan dokumen fisik harus dikoordinasikan offline.

Nilai tidak diubah oleh fitur sertifikat. Nilai akhir yang sudah disahkan tetap terkunci pada modul Penilaian. Koreksi substansi nilai resmi memerlukan proses koreksi penilaian tersendiri; tahap ini tidak menambahkan pembukaan kunci nilai secara diam-diam. Foto/QR jurnal bukan syarat sertifikat template baru. Gate jurnal untuk skema nilai lama dipertahankan sebagai kompatibilitas.

## Dokumen yang terbit sebelum upgrade

Migrasi **tidak mengisi snapshot otomatis** dari data sekarang, sebab tidak ada bukti bahwa data sekarang sama dengan cetakan lama.

- Sertifikat lama terbit tanpa arsip: PDF dan distribusi ditahan. Koordinator membuka detail, membandingkan dengan dokumen asli, lalu mengisi catatan pemeriksaan pada **Periksa & arsipkan dokumen lama** jika rekonstruksi data sekarang memang layak disimpan.
- Sumber arsip diberi `legacy_reconstructed`, dengan catatan dan waktu rekonstruksi aktual. Tanggal penerbitan lama tetap terpisah. Tidak diklaim identik dengan PDF/cetakan asli. Jika sumber belum benar, jangan menekan arsip; selesaikan pemeriksaan/koreksi dahulu.
- Proposal lama yang belum terbit: ajukan versi baru setelah memeriksa sumber. Tombol setujui tidak aktif jika belum memiliki proposal arsip.
- Tidak ada overwrite arsip lama. Import PDF asli lama belum disediakan; ini tetap pilihan pengembangan jika sekolah memiliki berkas asli dan perlu pengarsipan persis.

Audit database kerja setelah migrasi: 30 siswa, 30 roster, 18 penempatan, 2 sertifikat; 1 sertifikat terbit belum berarsip. Tidak ada rekonstruksi, persetujuan, atau revisi otomatis pada data kerja. Flag RED tetap untuk pekerjaan ini, bukan karena dokumen baru masih memakai data hidup.

## Database dan backup

Migrasi `2026_10_24_000001_create_certificate_documents.php` menambah:

- `certificate_documents`: unik sertifikat + versi; snapshot JSON, HTML, PDF base64, hash, asal arsip, alasan, captured_by, issued_at, timestamps.
- `certificate_movements.version`: versi untuk aktivitas baru. Aktivitas lama tetap NULL dan ditampilkan “versi tidak tercatat”; tidak ditebak.

PDF disimpan di database agar record dan berkas dapat di-commit/rollback bersama. Konsekuensinya backup database lebih besar (base64 menambah ukuran dibanding byte PDF). Pantau kapasitas; pemindahan ke storage objek privat bisa dirancang nanti tanpa mengubah hash/identitas arsip. Model menolak overwrite/delete arsip; ini bukan perlindungan dari administrator yang mengubah SQL langsung.

Backup sebelum migrasi: `storage/app/private/backups/before-pkl-tags-20261024-050739.sql` (99.298 byte). Nama prefix masih mengikuti helper backup lama. Backup ini adalah keadaan **sebelum** tabel arsip ditambahkan; backup operasional berikutnya harus mencakup tabel arsip dan foto/dokumen privat.

Migrasi sudah diterapkan pada database lokal. Tidak perlu fresh migrate. Untuk lingkungan lain: backup, `php artisan migrate`, lalu jalankan tes pada database uji terpisah. Jangan jalankan `migrate:fresh` pada data nyata. Seeder utama hanya membuat arsip untuk sertifikat demo **yang baru dibuat**; seeding ulang tidak mengarsipkan dokumen lama secara diam-diam.

Perbandingan snapshot tidak bergantung pada urutan key JSON karena MySQL dapat menyusunnya ulang; NIS tetap string sehingga `001` tidak disamakan dengan `1`. Dasar: [MySQL 8.0 — normalisasi JSON](https://dev.mysql.com/doc/refman/8.0/en/json.html#json-normalization). Pemeriksaan native MySQL baca-saja tersedia melalui `php scripts/verify-certificate-json.php`; berhasil untuk dua sumber sertifikat lokal. Ini bukan pengujian transaksi konkuren.

## Bukti dan batas pengujian

- Suite: **99 tes / 1.790 assertion** lulus (SQLite in-memory).
- Regresi: tanggal/nilai kosong, layout melebihi dua halaman, rollback, perubahan sumber sebelum pengesahan, tab/versi lama, revisi dan PDF lama identik, akses lintas siswa, rekonstruksi eksplisit, hash rusak, model immutable, normalisasi JSON.
- Smoke test Edge dengan database/temp storage terpisah: kiosk tetap berjalan; pencarian nama/NIS sertifikat, pengajuan revisi, persetujuan, serta PDF arsip versi lama identik lulus. Script: `scripts/journal-browser-smoke.cjs`, prosedur dependency di STABILISASI_JURNAL_DAN_RESET_UJI.md.
- Belum: uji beban/kunci transaksi MySQL bersamaan, backup/restore operasional, cetak bolak-balik pada printer sekolah, serta template desain resmi customer.

Prioritas kode berikutnya: Timeline berbasis roster dan jurusan, kemudian Monitoring kasus koordinator, perapian Penilaian, dan Dashboard per tahun. Jurnal/kiosk tetap menjalani UAT paralel; kelulusan tes kode tidak dianggap pengesahan hasil lapangan.
