# Monitoring koordinator — tahap 1, 24 Oktober 2026

Pembaruan tahap 2: kategori, penanggung jawab, status, riwayat dan perlindungan tab lama sudah diterapkan. Flag Monitoring dilepas untuk cakupan audit yang diuji. [Panduan dan uji singkat terbaru](MONITORING_TINDAK_LANJUT.md). Keterangan UNSTABLE di bawah adalah riwayat tahap 1.

Perbaikan ini menutup penolakan 403 koordinator dan pencampuran identitas pencatat dengan guru. Bukan penambahan kewajiban monitoring rutin bagi guru.

## Perilaku sekarang

- Koordinator dapat mencatat seluruh penempatan, termasuk yang tidak memiliki guru. Guru tetap boleh mencatat hanya siswa pada penempatannya; admin hanya membaca, siswa tidak dapat mengakses.
- Cari nama/NIS atau filter tag tahun/jurusan, lalu **Buka siswa** untuk mengisi tanggal, hasil/kendala, dan tindak lanjut. Tidak lagi memuat dropdown seluruh siswa.
- Pencarian/filter juga menyaring riwayat. Kandidat 10 per halaman, riwayat 15 per halaman, dengan paginasi SQL terpisah.
- Kelas/jurusan berasal dari roster tahun penempatan, bukan profil terbaru. Penempatan lama tanpa roster ditandai, tidak ditebak jurusannya.
- `created_by` adalah ID akun yang menyimpan. Input ID pencatat/guru dari klien diabaikan; guru terkait diambil dari penempatan, boleh kosong. Penyimpanan catatan dan audit berada dalam satu transaksi.
- Tanggal kejadian boleh lampau, tidak boleh melebihi hari ini WIB. Waktu pencatatan asli tetap tersedia dalam `created_at` dan audit.
- Siswa tanpa penempatan tetap dikelola melalui Kontrol PKL. Tahap ini tidak membuat penempatan otomatis atau memindahkan sumber status PKL.

## Migrasi tanpa fresh

Migrasi `2026_10_24_000002_add_monitoring_author` sudah diterapkan pada database lokal setelah backup. Data siswa, kiosk, jurnal, nilai, dan sertifikat tidak direset/reseed.

Backup lokal: `storage/app/private/backups/before-pkl-tags-20261024-101007.sql` (101115 byte).

Catatan lama tetap dipertahankan; identitas pencatat diisi dari `teacher_id` karena alur lama hanya mengizinkan guru tersebut mencatat. `teacher_id` sekarang nullable, foreign key tetap dipertahankan. Rollback ditolak jika skema lama tidak dapat menyimpan identitas pencatat baru; jangan memaksa rollback yang menghilangkan informasi.

## Pengujian dan batas status

Tes regresi meliputi kedua akun koordinator, manipulasi ID pencatat, penempatan tanpa guru, penolakan akses lintas guru/siswa/admin, pencarian/filter historis, paginasi, validasi tanggal/isian, dan migrasi catatan lama. Pengujian aplikasi memakai SQLite terisolasi; migrasi lokal juga dijalankan pada MySQL setelah backup.

Hasil: `php artisan test --compact` **112 tes / 2.033 assertion lulus**. Audit baca-saja MySQL tetap menunjukkan 30 siswa, 30 roster, 18 penempatan; tidak ada reset. Pada database kerja belum ada catatan monitoring, sehingga pengujian backfill catatan lama menggunakan fixture sintetis, bukan klaim data lapangan telah diuji.

Uji browser Edge headless lulus untuk pencarian, pembukaan siswa, penyimpanan catatan koordinator, riwayat, larangan akses siswa, serta viewport ponsel 390px tanpa overflow halaman. Regresi kiosk, sertifikat, dan Timeline dalam skrip yang sama juga lulus. Fixture memakai database SQLite baru terpisah, bukan database kerja. Artefak lokal: `C:/Users/jorda/AppData/Local/Temp/simmag-journal-smoke-QX31lY`. Ini bukan pengujian perangkat HP fisik.

Flag **UNSTABLE** sengaja tetap ditampilkan. Penanggung jawab kasus, kategori, dan status tindak lanjut terstruktur dari audit awal belum dikerjakan; tindak lanjut saat ini tetap berupa teks. Tidak ada klaim seluruh modul Monitoring stabil. Timeline yang telah menutup temuan audit tetap tanpa flag. Saran fitur baru di luar daftar perbaikan harus dibicarakan dahulu.
