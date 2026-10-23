# Tag tahun ajaran dan penempatan berbasis roster

Perubahan 23 Oktober 2026.

## Sumber data

- `internship_periods.academic_year` adalah tag unik, contoh `2026/2027`. Tag hanya mengelompokkan data. Tidak menentukan tanggal mulai/selesai siswa.
- `internship_rosters` tetap memiliki satu baris siswa per tahun ajaran, keikutsertaan, tanggal efektif, batch, dan pengecualian.
- Pendaftaran/import Data Siswa otomatis membuat atau memakai tag yang sama di database. Siswa tidak magang maupun belum dijadwalkan tetap memiliki tag.
- `placements.internship_roster_id` menghubungkan penempatan secara langsung ke roster. Siswa dan tag penempatan wajib cocok dengan roster tersebut. Satu penempatan per siswa per tag; perubahan industri yang sudah memiliki dokumen harus melalui proses koreksi terpisah.
- `teacher_id` boleh kosong. Koordinator tetap mengelola penempatan; guru pendamping hanya bila diperlukan. Timeline menampilkan kelompok Tanpa guru pendamping / Koordinator untuk siswa ini.
- Kolom tanggal periode dan status Aktif/Selesai penempatan lama tetap disimpan sebagai kolom arsip, tetapi bukan sumber status/tanggal di aplikasi. API pengelolaan periode/penempatan menolak input tanggal/status manual tersebut.

## Perilaku periode

- Menu Periode / Tag PKL menerima tahun ajaran berurutan (`2026/2027`, bukan `2026/2028`). Tidak ada input rentang tanggal.
- Tag yang telah dipakai tidak boleh diganti tahunnya. Buat tag baru untuk tahun lain.
- Arsipkan tag hanya menghentikan penempatan baru pada tag itu; tidak mengakhiri PKL, menghapus siswa, atau mengubah jadwal yang sudah ada.

## Penempatan multi-siswa

1. Pilih tag tahun ajaran.
2. Pilih industri dan guru pendamping opsional.
3. Centang jurusan untuk memilih seluruh kandidat yang tersedia atau centang siswa satu per satu. Lepas centang untuk pengecualian.
4. Cari nama/NIS untuk menyaring daftar. Pilihan tetap dipertahankan ketika pencarian berubah; jumlah pilihan tersembunyi dan daftar seluruh pilihan disediakan.
5. Simpan. Seluruh pilihan diproses dalam satu transaksi. Siswa beda tahun, akun nonaktif/belum terhubung, siswa tidak magang, dan penempatan duplikat ditolak tanpa menyimpan sebagian batch.

Penempatan tidak mengubah keikutsertaan, tanggal, atau pengecualian di Kontrol PKL. Siswa belum dijadwalkan boleh ditempatkan dahulu; fase tetap Belum dijadwalkan. Siswa yang eksplisit Tidak magang harus dikoreksi di Kontrol PKL terlebih dahulu.

## Konsumen tanggal/status

- Timeline, tampilan penempatan, jurnal, laporan penempatan, dan tanggal sertifikat membaca roster yang terhubung.
- Jurnal hanya dapat diisi saat fase Sedang PKL dan belum ada sertifikat; tanggal kegiatan harus berada dalam tanggal roster siswa. Tag lama atau status manual penempatan tidak dapat membypass aturan ini.
- Sertifikat yang roster/jadwalnya belum lengkap ditolak saat cetak, bukan mengambil tanggal dari tag.
- Pemilihan template penilaian memakai jurusan pada roster tahun penempatan, bukan jurusan profil terbaru jika siswa telah naik/pindah jurusan pada tahun berikutnya.
- Snapshot identitas/jadwal pada penerbitan sertifikat masih tugas terpisah. Pengalihan pemeriksaan rutin jurnal dari guru ke koordinator juga tidak termasuk perubahan ini.

## Data demo sesudah migrate:fresh --seed

DatabaseSeeder kini membuat 30 siswa pada delapan jurusan, semuanya memiliki akun dan roster terhubung ke satu tag tahun ajaran berjalan. Masing-masing enam siswa berada pada fase masuk sekolah, sedang PKL, terjadwal, belum dijadwalkan, dan tidak magang.

- 18 penempatan terhubung, termasuk contoh tanpa guru pendamping.
- Enam siswa belum dijadwalkan dapat digunakan untuk mencoba checkbox penempatan baru; enam Tidak magang sengaja tidak dapat dipilih.
- Tiga industri, delapan template ketentuan jurusan, 12 jurnal berfoto, enam nilai industri disahkan, dan dua sertifikat demo (satu menunggu persetujuan, satu siap cetak).
- Dua koordinator dan dua guru pendamping, serta satu admin.
- Tag tidak memiliki tanggal; jadwal batch dan pengecualian siswa memiliki tanggal berbeda relatif terhadap tanggal seeding.

Login demo lokal:

| Akun | Login | Password awal |
| --- | --- | --- |
| Koordinator 1 | koordinator@simmag.test | password123 |
| Koordinator 2 | koordinator2@simmag.test | password123 |
| Admin | admin@simmag.test | password123 |
| Guru | guru@simmag.test / guru2@simmag.test | password123 |
| Siswa sedang PKL | 2026002 | 07112008 |
| Siswa belum dijadwalkan / belum ditempatkan | 2026004 | 07112008 |

Semua siswa demo lahir 2008-11-07. NIS siswa 2026001 sampai 2026030. Email siswa demo masih tersedia untuk kompatibilitas, tetapi login utama memakai NIS. Ganti password awal sebelum digunakan di luar pengujian.

Seeder dibatasi lingkungan local/testing. Jangan menjalankan fresh di produksi. Migrasi skema tanpa fresh tidak menebak hubungan arsip penempatan lama; arsip tanpa hubungan roster harus direkonsiliasi sebelum kembali digunakan.

## Reset lokal yang dijalankan

Atas permintaan pengguna, `php artisan migrate:fresh --database=mysql --seed --no-interaction` berhasil dijalankan pada database `simmag` di `127.0.0.1` (lingkungan local), 23 Oktober 2026. Data lama database tersebut diganti data demo. Database lain tidak menjadi target.

Backup sebelum reset: `storage/app/private/backups/before-pkl-tags-20261023-050911.sql` (76.463 byte). File lampiran lama tidak dihapus. Backup mengandung data pribadi/hash akun; jangan dipublikasikan atau dimasukkan ke repository. Pemulihan belum diuji dalam proses ini dan akan mengganti data demo, sehingga harus dilakukan secara sengaja jika diperlukan.
