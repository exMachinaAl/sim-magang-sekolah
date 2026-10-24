# Monitoring — penutupan temuan audit, 24 Oktober 2026

Tahap ini hanya melengkapi flag Monitoring: kategori, penanggung jawab, status tindak lanjut dan riwayat. Tidak mengubah jurnal, nilai, sertifikat, status PKL atau kewajiban kunjungan guru.

## Uji singkat

1. Masuk sebagai koordinator, buka **Monitoring**.
2. Cari **DEMO-SERT-001**, buka siswa, lalu **Detail kasus** pada catatan **DEMO MONITORING: kendala teknis untuk mencoba alur tindak lanjut.** Contoh menggunakan siswa demo yang sudah ada, tanpa menambah siswa baru.
3. Tentukan kategori dan koordinator penanggung jawab, pilih **Ditindaklanjuti**, isi catatan minimal 10 karakter, lalu **Simpan Tindak Lanjut**.
4. Ubah menjadi **Selesai** dan periksa riwayat, ID editor serta waktu. Kasus dapat dibuka kembali dengan catatan alasan; riwayat lama tetap ada.

## Aturan dan perlindungan

- Koordinator mengendalikan status, kategori, penanggung jawab dan guru pendamping. Penanggung jawab harus akun koordinator aktif; pencatat awal dan editor tetap dicatat terpisah.
- Guru boleh membuat catatan pada siswa bimbingannya dan membaca riwayat; tidak dapat mengubah status/penugasan. Catatan guru dimulai **Terbuka**, menunggu penetapan penanggung jawab koordinator. Admin hanya membaca; siswa tidak mendapat akses Monitoring.
- Guru pendamping per kasus opsional, dibatasi guru aktif pada penempatan siswa agar pilihan guru tidak memberikan akses lintas siswa. Memilih guru tidak mengubah penempatan. Jika guru penempatan berubah, pilih guru terkait yang baru atau kosongkan pada pembaruan kasus.
- Status: **Terbuka → Ditindaklanjuti → Selesai**; koordinator juga boleh langsung menyelesaikan kasus yang sudah ditangani atau membuka kembali kasus selesai. Setiap pembaruan wajib catatan, termasuk perubahan penanggung jawab saja.
- Catatan awal dan tindak lanjut awal tidak ditimpa. Setiap pembaruan menyimpan kondisi sebelum/sesudah, catatan, ID editor, waktu, dan nomor revisi. Riwayat tidak dapat ditimpa/dihapus melalui model aplikasi. Audit dan perubahan kasus berada dalam satu transaksi.
- Tab lama mendapat 409 dan diminta memuat ulang, bukan menimpa perubahan koordinator lain. Waktu selesai dikosongkan jika dibuka kembali, tetapi penyelesaian terdahulu tetap dapat ditelusuri melalui riwayat.
- Nama/NIS/tahun/jurusan menyaring kandidat dan riwayat. Status/kategori hanya menyaring riwayat. Daftar dan riwayat perubahan dipaginasi pada database.
- Tanggal kejadian boleh lampau, termasuk sebelum/sesudah jadwal PKL, karena pengantaran atau masalah susulan tidak selalu terjadi saat PKL aktif. Tidak boleh tanggal masa depan. Selesainya kasus bukan bukti siswa selesai PKL atau hadir di sekolah.

## Migrasi tanpa reset

`2026_10_24_000005_add_monitoring_case_workflow` menambah `case_status`, `category`, `coordinator_id`, `revision`, `resolved_at` serta tabel `monitoring_updates`. Catatan lama mendapat **Belum diklasifikasi**, bukan dianggap selesai/terbuka; kategori dan penanggung jawab tidak ditebak. Teks lama tetap utuh.

Migrasi lokal diterapkan setelah backup `storage/app/private/backups/before-pkl-tags-20261024-162914.sql` (1344709 byte). Tidak ada fresh/reset/reseed umum. Seeder khusus `MonitoringCaseDemoSeeder` menambahkan satu kasus fiktif pada siswa demo yang sudah ada; menjalankan ulang tidak mereset hasil evaluasi pengguna. Data siswa, nilai, PDF, Word, dan kasus lama tidak ditimpa. Rollback ditolak setelah status/riwayat baru dipakai agar tidak kehilangan tindak lanjut.

## Flag dan batas cakupan

Verifikasi tahap ini: **130 tes / 2.308 assertion lulus**. Termasuk pembatasan peran, guru opsional, penugasan koordinator aktif, selesai/buka kembali, tab lama, migrasi catatan lama, filter, serta rollback perubahan dan riwayat ketika audit gagal. Uji browser Edge headless lulus untuk detail kasus 390px, simpan status selesai, riwayat dan hilangnya flag; regresi kiosk/sertifikat/Timeline tetap lulus. Artefak: `C:/Users/jorda/AppData/Local/Temp/simmag-journal-smoke-pZmk6G`. Query relasi penanggung jawab/riwayat terbaru juga berhasil dibaca pada MySQL lokal. Ini tidak menggantikan uji beban atau UAT manual.

Flag Monitoring dilepas setelah temuan audit ditangani dan pengujian lulus. Siswa tanpa penempatan tetap dikelola di Kontrol PKL; tidak dibuatkan penempatan otomatis untuk membuka kasus. Batas ini bukan kerusakan relasi dan tidak mengharuskan kunjungan rutin guru.

Status STABLE berlaku pada temuan audit yang diuji, bukan jaminan tidak ada bug atau klaim UAT pengguna sudah selesai. Pemeriksaan pengguna, beban sekolah nyata dan operasi MySQL bersamaan tetap perlu dilakukan. Flag Sertifikat, Jurnal, Penilaian dan Dashboard tidak ikut dilepas.
