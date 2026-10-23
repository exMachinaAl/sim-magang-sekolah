# Audit keterhubungan PKL, nilai, sertifikat, dan daftar perbaikan UI

Tanggal: 22 Oktober 2026. Audit kode dan database lokal, bukan validasi data produksi.

## Pembaruan lanjutan 23 Oktober: tag dan penempatan

Periode kini hanya tag tahun ajaran database. Penempatan sudah memiliki FK roster dan checkbox multi-siswa/jurusan. Timeline, jurnal, laporan penempatan, dan tanggal PDF memakai jadwal roster tanpa fallback tanggal periode. Red flag Penempatan dan Periode sudah dilepas; red flag Laporan tetap karena belum mencakup seluruh siswa yang belum ditempatkan. Lihat [aturan terbaru dan akun demo](TAG_PKL_DAN_PENEMPATAN.md). Temuan historis di bawah tidak semuanya masih berlaku; snapshot sertifikat tetap belum selesai.

## Pembaruan 23 Oktober 2026 (menggantikan lokasi UI/flag yang dicatat sebelumnya)

- Impor siswa pindah ke **Data Siswa**, dengan endpoint `students.import` / `students.template`. Endpoint lama masih kompatibel untuk tautan/integrasi yang sudah ada, tetapi tidak ada formulir impor di Kontrol PKL.
- Template utama langsung berisi delapan contoh. Dua contoh juga terlihat di halaman; tersedia pilihan file kosong opsional. Template jadwal turut menyertakan contoh tanggal.
- Kontrol PKL memakai card delapan jurusan, termasuk jurusan kosong, bukan tabel semua siswa. Daftar siswa hanya dirender untuk jurusan terpilih, hasil pencarian, atau tautan siswa spesifik. Kandidat batch tidak dikirim pada halaman awal; buka Atur Jadwal Batch untuk memuatnya.
- Pencarian nama jurusan/singkatan, nama siswa, NIS/NISN bekerja dari halaman awal. Hasil siswa menyediakan tautan ke jurusan dan siswa yang tepat, tetap dibatasi tahun ajaran.
- **Red flag Data Siswa diperbaiki dan dilepas:** input jurusan manual, layanan pendaftaran, dan impor memakai daftar resmi bersama `InternshipRoster::officialMajors()`. Tidak ada lagi input jurusan bebas pada form profil. Ini menggunakan daftar canonical bersama, belum migrasi ke tabel jurusan ber-ID.
- Pendaftaran individual Data Siswa langsung membuat akun + profil + roster tahun ajaran. Kelas/jurusan profil mengikuti roster dengan tahun ajaran terbaru. Impor tahun lama tidak menurunkan profil; edit profil memperbarui kelas/jurusan roster terbaru saja. NIS/NISN/nama/email tetap identitas siswa yang dibagikan lintas tahun. Jadwal dan riwayat kelas/jurusan tahun lain tidak ditimpa.
- Data lama tidak dikonversi massal secara diam-diam; nilai jurusan lama yang tidak resmi perlu dikoreksi lewat pilihan jurusan resmi sebelum disimpan. Roster lama tanpa akun tetap memerlukan DOB asli.
- Red flag Penempatan, Periode PKL, dan Laporan & Audit masih berlaku. Snapshot penerbitan sertifikat juga masih pekerjaan terpisah.
- Seeder terpisah dan prompt CSV tersedia di [CONTOH_DATA_SISWA.md](CONTOH_DATA_SISWA.md). Seeder belum dijalankan pada database pengguna dan tidak dipanggil otomatis oleh DatabaseSeeder.

## Kesimpulan

Masalahnya bukan sekadar tampilan. Kontrol PKL memakai `internship_rosters`, sedangkan penilaian dan sertifikat memakai `placements`. Kedua jalur belum memiliki hubungan langsung yang konsisten.

Saat audit awal: 30 roster, 4 profil siswa, 26 roster tanpa `student_id`, 4 penempatan, 2 penilaian, dan 2 sertifikat. Maka muncul di Kontrol PKL belum berarti siswa tersedia di penilaian atau sertifikat. Angka ini adalah kondisi saat audit, bukan counter permanen.

## Temuan dan dampaknya

| Prioritas | Temuan kode | Dampak | Perbaikan berikutnya |
| --- | --- | --- | --- |
| P0 | Roster dapat berdiri tanpa profil/akun; nilai wajib punya penempatan. | Siswa terhitung PKL tetapi tidak ditemukan untuk dinilai. | Pendaftaran baru sudah membuat akun dan profil. Lengkapi DOB data lama, lalu hubungkan penempatan secara eksplisit ke pendaftaran PKL. |
| P0 | Timeline mencocokkan roster melalui siswa/NIS dan tahun yang dihitung dari awal periode; belum ada FK penempatan ke roster. | Salah asumsi pada periode lintas tahun atau beberapa penempatan dalam satu tahun. | `placements.internship_roster_id`, dengan rekonsiliasi dan daftar konflik sebelum backfill; jangan memilih roster secara tebakan. |
| P0 | Tanggal tersebar di periode, roster, dan batch. Jurnal masih membatasi tanggal berdasarkan periode dan status manual `active/finished`. | Counter mengatakan masuk sekolah tetapi jurnal masih memakai aturan aktif; tanggal jurnal dan sertifikat bisa berbeda. | Satu layanan jadwal efektif per pendaftaran PKL. Batch mengatur jadwal, pengecualian siswa mengubah jadwal efektif; konsumen hanya membaca layanan ini. |
| P0 | PDF sertifikat membaca nama, NIS, kelas, industri, dan tanggal aktif saat dicetak. Baru ketentuan penilaian yang memiliki snapshot. | Cetak ulang sertifikat lama dapat berubah setelah identitas/jadwal diperbarui. | Bekukan identitas, jadwal, nilai resmi, ketentuan, penandatangan, nomor, dan versi desain saat penerbitan; koreksi menjadi versi baru. |
| P1 | Sertifikat penilaian lama dan berbasis template memiliki dua jalur PDF dan persyaratan berbeda. | Skema lama mensyaratkan jurnal disetujui; skema baru cukup nilai disahkan. Format lama landscape, baru portrait. | Tampilkan label arsip lama; tetapkan satu proses bisnis baru tanpa mengubah arsip otomatis. |
| P1 | Form Data Siswa menerima jurusan bebas; roster dan template memakai jurusan resmi. Template dicocokkan ke jurusan profil siswa saat ini. | Salah ejaan/perubahan jurusan dapat menghilangkan pilihan template atau mengaitkan jurusan yang salah. | Master jurusan dengan ID bersama; jurusan pada pendaftaran sebagai riwayat, profil sebagai data terkini. |
| P1 | Isi depan sertifikat baru belum memiliki konfigurasi identitas sekolah dan penandatangan; tanda tangan masih placeholder. | Belum cukup untuk penerbitan dokumen resmi final. | Mapping seluruh field depan/belakang dari dokumen asli, pilihan tipe dokumen, identitas penandatangan, dan pratinjau wajib. |
| P1 | Jurnal masih menunggu pemeriksaan guru; formulir penempatan mewajibkan guru. | Tidak sesuai peran guru hanya untuk pendampingan khusus/darurat. | Alihkan pemeriksaan rutin ke koordinator dan evaluasi pembimbing opsional. Jangan mengubah hak akses separuh-separuh. |

Sumber: `InternshipRosterController`, `MasterDataController`, `TimelineController`, `JournalController`, `AssessmentController`, `CertificateController`, dan dua view PDF di `resources/views/certificates/`.

## Aturan data yang dituju

1. `users`: autentikasi dan status akun. Password disimpan sebagai hash, bukan teks asli.
2. `students`: satu identitas siswa, NIS unik sebagai login, tanggal lahir, hubungan satu akun. Tidak membuat ulang akun tiap tahun.
3. `internship_rosters`: pendaftaran siswa per tahun ajaran, kelas/jurusan pada tahun tersebut, keikutsertaan, jadwal efektif dan pengecualian.
4. `placements`: industri dan penugasan untuk pendaftaran PKL tertentu. Akun atau roster tidak otomatis berarti industri sudah ditentukan.
5. `assessments`: nilai dari industri yang dicatat koordinator, dengan salinan ketentuan template jurusan dan ID editor. Guru tidak menginput nilai.
6. `certificates`: dokumen resmi berdasarkan nilai disahkan, dengan salinan data penerbitan yang tidak berubah saat data master diedit.

Poin hubungan FK penempatan dan snapshot sertifikat di atas adalah target refactor, BELUM diterapkan dalam perubahan ini. Migrasi arsip perlu laporan konflik dan pengujian cetak ulang.

## Sudah diterapkan pada tahap ini

- Tambah siswa melalui Data Siswa maupun Kontrol PKL membuat profil dan akun otomatis, termasuk impor CSV.
- NIS adalah login siswa. Staf tetap login memakai email; login email siswa lama tetap didukung.
- Password awal akun baru adalah tanggal lahir `DDMMYYYY`: 7 November 2008 menjadi `07112008`. Email siswa opsional.
- Form menggunakan input tanggal; kolom CSV `tanggal_lahir` memakai `YYYY-MM-DD`. DOB wajib untuk akun baru dan tidak dikarang untuk data lama.
- NIS sama memakai profil yang sama lintas tahun; impor ulang tidak mereset password, nama akun, atau email. Koreksi identitas melalui Data Siswa. DOB yang bertentangan ditolak.
- Pembuatan akun dan roster dalam transaksi. Jika salah satu baris impor gagal, seluruh impor dibatalkan.
- Pembuatan profil menghubungkan roster lama dengan NIS yang sama; roster tanpa akun dapat dilengkapi DOB melalui Atur individual atau impor ulang.
- Dashboard siswa langsung menampilkan profil sendiri, status pendaftaran PKL, dan industri jika sudah ada penempatan.
- Penilaian memakai pencarian nama/NIS, hasil tabel berhalaman, konteks periode dan industri, lalu tombol Buka Penilaian. Tidak memuat dropdown seluruh siswa.
- Form, template CSV, dan ekspor sudah mendukung tanggal lahir. File ekspor berisi data pribadi: batasi akses dan distribusinya.

## Aturan UI yang harus dipertahankan

### Pembaruan input batch dan penanda menu

- Panel **Impor Data Siswa Massal** langsung terlihat di Kontrol PKL, sebelum panel jadwal batch (bukan tersembunyi di panel bawah).
- Tersedia tiga unduhan: **Template Siswa Kosong**, **Contoh Pengisian 8 Jurusan**, dan **Template Siswa + Jadwal PKL**. Template kosong tidak mengandung siswa contoh yang dapat terimpor tanpa sengaja. Tahun ajaran contoh mengikuti filter halaman.
- Template siswa: `tahun_ajaran, nis, nisn, nama, tanggal_lahir, kelas, jurusan, email, catatan`. Template jadwal menambahkan `status_magang, mulai_pkl, selesai_pkl`, tanpa mewajibkan periode lama.
- Impor siswa saja mempertahankan tanggal/status lama. Tanggal terisi pada template jadwal menjadi pengecualian individual; untuk jadwal serentak gunakan kontrol batch setelah impor. Status eksplisit tidak_magang/belum_ditentukan tanpa tanggal membersihkan jadwal sebelumnya.
- File kosong, header berulang/kosong, kolom berlebih, duplikat NIS dalam tahun ajaran sama, tanggal tidak valid, dan identitas melebihi batas ditolak. Pesan kesalahan baris membantu koreksi file; transaksi tetap membatalkan seluruh impor jika ada kegagalan.
- Menu **Data Siswa**, **Penempatan**, **Periode PKL**, serta **Laporan & Audit** memiliki tag merah **RED FLAG** dan penjelasan khusus pada halaman masing-masing. Ini menandai ketidaksinkronan yang diketahui, bukan menonaktifkan menu atau menyatakan semua fungsinya rusak. Perusahaan dan Kontrol PKL tidak diberi label lama tanpa dasar temuan.
- Daftar penanda dikelola di `config/legacy_modules.php`; hapus penanda per modul hanya setelah perbaikannya selesai dan diuji. Tag tidak mengubah hak akses, data, atau aturan bisnis lama.

### Pencarian siswa

**Jangan gunakan selector/dropdown seluruh siswa untuk input nilai. Gunakan nama atau NIS.** Nama tidak menjadi kunci penyimpanan: hasil pencarian dipilih sebagai penempatan dengan ID, NIS, periode, dan industri yang terlihat jelas. Nama sama tidak boleh langsung memilih siswa pertama. Selector tetap boleh untuk template ketentuan, jurusan, atau pilihan kecil yang relevan.

## Daftar perbaikan UI lanjutan

- [x] Pencarian nama/NIS di penilaian; daftar hasil ringkas, bukan selector seluruh siswa.
- [x] Hasil kosong menjelaskan perbedaan sudah masuk Kontrol PKL dan sudah memiliki penempatan.
- [x] Form DOB dan petunjuk akun NIS otomatis di pendaftaran individual maupun CSV.
- [x] Penanda akun belum terhubung untuk roster lama dan akses pengisian DOB.
- [x] Dashboard siswa menampilkan data diri meskipun belum ditempatkan.
- [ ] **P0 — halaman detail siswa terpadu:** identitas, tahun ajaran, jadwal, industri, nilai, sertifikat dalam tab. Hilangkan kebutuhan mencari siswa yang sama berulang kali di menu terpisah.
- [ ] **P0 — indikator kesiapan sertifikat:** identitas lengkap, jadwal lengkap, industri ada, nilai disahkan, penandatangan lengkap. Tampilkan sebab belum siap dan tautan memperbaikinya.
- [ ] **P0 — pratinjau depan/belakang:** field resmi ditampilkan sebelum penerbitan, termasuk nama/NIS/jurusan/jadwal/nilai/penandatangan. Bedakan Draf, Disahkan, dan Arsip versi lama.
- [ ] **P0 — keamanan password awal:** tanggal lahir mudah ditebak. Sebelum penggunaan produksi, wajibkan penggantian pada login pertama dan siapkan reset oleh petugas berwenang. Saat ini tersedia penggantian di Profil serta pengingat dashboard, belum paksaan.
- [ ] **P1 — card per jurusan:** ringkasan sekolah → jurusan → tabel siswa → detail. Jangan membuka semua timeline di halaman awal.
- [ ] **P1 — pencarian konsisten:** nama/NIS di penempatan dan sertifikat juga, jurusan/tahun sebagai filter; template selector dipisahkan dari pencarian siswa.
- [ ] **P1 — rekap nilai ringkas:** tabel aspek dan nilai, bukan card per field. Pertahankan konteks siswa dan versi template saat menyimpan.
- [ ] **P1 — wizard impor:** pratinjau akun baru/dipakai ulang, validasi baris dan NIS duplikat, ringkasan perubahan jadwal, serta laporan error yang bisa diunduh.
- [ ] **P1 — konsistensi status:** pisahkan status jadwal otomatis, kelengkapan data, status nilai, dan status distribusi sertifikat. Jangan memakai satu label Aktif/Selesai untuk semuanya.
- [ ] **P1 — editor dan riwayat:** ID/nama koordinator, waktu perubahan, konflik edit, serta versi dokumen di tempat yang konsisten.
- [ ] **P2 — tampilan siswa:** fokus foto kegiatan, foto jurnal penting, status PKL, nilai disahkan, dan sertifikatnya; singkirkan metrik pengelolaan guru dari dashboard siswa.

## Penanganan data lama dan pengoperasian

- Migrasi menambah DOB nullable dan membuat email opsional. Tidak menghapus data, mereset password, menjalankan seeder, atau mengarang tanggal lahir.
- Roster lama yang belum terhubung tetap dapat terlihat. Lengkapi tanggal lahir asli lewat impor atau Atur individual; jadwal batch lama bukan sumber tanggal lahir dan tidak mengarang akun.
- DOB profil lama boleh dilengkapi tanpa mengubah password lama. Jangan menyampaikan password DDMMYYYY kepada pemilik akun lama seolah password telah direset.
- Akun baru tidak otomatis mendapatkan industri, nilai, atau sertifikat. Penempatan tetap perlu disiapkan koordinator.
- Simpan NIS/NISN sebagai teks saat mengolah di Excel; jika nol awal sudah hilang sebelum impor, sistem tidak dapat merekonstruksinya dengan aman.
- Impor yang didukung saat ini CSV, bukan unggah `.xlsx` langsung. Gunakan Save As CSV dari Excel.
- Sebelum refactor hubungan PKL/sertifikat berikutnya: backup database dan lampiran, uji pemulihan, buat laporan ketidakcocokan, lalu migrasi bertahap. Jangan `migrate:fresh` pada data pengguna.
