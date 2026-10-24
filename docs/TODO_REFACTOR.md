# Catatan Refactor SIMMAG

## Tahap aktif — 24 Oktober 2026

Laporan & Audit selesai untuk red flag terakhir di manajemen: ekspor seluruh roster termasuk belum ditempatkan, filter tahun/jurusan untuk semua ekspor, label historis dan fase WIB. Flag dicopot setelah 146 tes dan uji browser lulus, tanpa migrasi/reset. [Cara mencoba](LAPORAN_ROSTER.md). [Daftar kekurangan setelah upgrade](KEKURANGAN_SETELAH_UPGRADE.md) mencatat arsip sertifikat lama, konversi/cetak, kiosk HP, backup/konkurensi dan UAT yang masih tersisa; tidak ada penambahan di luar flag.

Dashboard selesai untuk tiga temuan flag: tag tahun menyaring seluruh ringkasan, siswa dihitung unik, dan fase dari roster direkap per jurusan/sekolah sesuai akses. Flag dicopot setelah pengujian; tanpa migrasi/reset. [Cara mencoba dan batas rekap](DASHBOARD_REKAP_TAHUN.md). Sisa flag menu utama adalah Sertifikat (arsip lama dan pengujian dokumen/cetak) serta Jurnal (kamera HP/perangkat dan cetak). Tidak menambah fitur di luar flag.

Penilaian selesai untuk tiga temuan flag: label historis roster, bukti industri privat/berversi unggah, pencarian dan filter rekap tahun/jurusan. Flag Penilaian dicopot setelah pengujian. [Uji contoh dan batas cakupan](PENILAIAN_BUKTI_DAN_REKAP.md). Prioritas kode berikutnya **Dashboard** sesuai flag; sertifikat/jurnal masih membutuhkan pemeriksaan dokumen dan UAT lapangan.

Monitoring selesai untuk temuan audit: kategori kasus, penanggung jawab koordinator, guru opsional, status selesai/buka kembali, riwayat dan deteksi tab lama. Flag Monitoring dicopot, bukan flag modul lain. [Uji satu contoh yang sudah disiapkan](MONITORING_TINDAK_LANJUT.md). Prioritas kode berikutnya **Penilaian** sesuai flag (identitas historis, bukti industri, filter rekap), tanpa penambahan di luar audit.

DOCX dari arsip disahkan kini tersedia tanpa LibreOffice, termasuk contoh siap unduh. Format 80/20 memakai berkas sumber asli; aspek bebas memakai tabel Word dinamis. PDF/versi/nilai lama tidak diubah. [Panduan Word dan batas presisi cetak](SERTIFIKAT_WORD_ARSIP.md). Pengguna belum menguji alur penuh secara manual; jangan menyamakan kelulusan tes otomatis dengan UAT selesai.

**Titik evaluasi pengguna:** satu sertifikat DEMO siap unduh telah ditambahkan tanpa reset data lama; ada tombol langsung pada menu Sertifikat dan revisi terisi. [Langkah uji singkat](UJI_SERTIFIKAT_CEPAT.md). Tunggu evaluasi alur ini sebelum memperluas pengembangan. Flag tetap menjelaskan batas yang nyata, bukan menghalangi PDF bawaan.

Sertifikat diperbarui sesuai paket pengguna: template cetak per ketentuan, variasi penandatangan independen, data depan lengkap, format belakang bebas/80-20, unggah DOCX dan arsip per versi. PDF bawaan siap; DOCX→PDF memerlukan instalasi/konfigurasi LibreOffice yang belum tersedia. Flag tetap karena keterbatasan tersebut dan arsip lama/UAT. [Petunjuk serta batas pengujian](SERTIFIKAT_TEMPLATE_CETAK.md).

Monitoring tahap 1 selesai: koordinator dapat mencatat tanpa guru, `created_by` terpisah dari guru terkait, pencarian nama/NIS + filter tag tahun/jurusan untuk kandidat dan riwayat, label historis roster, serta paginasi database. Migrasi tanpa reset sudah diterapkan setelah backup. Flag merah menjadi **UNSTABLE**, belum dilepas karena status tindak lanjut terstruktur masih tertunda. [Rincian tahap Monitoring](MONITORING_KOORDINATOR.md). Tidak ada fitur tambahan di luar perbaikan audit; saran tambahan harus dibahas dahulu.

Timeline kini **STABLE untuk cakupan temuan audit** dan flag UI dilepas: card jurusan → tabel siswa → timeline tanggal, siswa tanpa penempatan terlihat, kelas/jurusan historis, pembatasan akses, serta agregasi/pencarian/paginasi SQL. [Rincian pengujian Timeline](TIMELINE_ROSTER.md). Tahap ini tidak membutuhkan migrasi atau fresh. Stabil bukan jaminan tanpa bug baru; regresi tetap diuji.

Pengamanan P0 sertifikat baru sudah diterapkan: kesiapan sebelum pengajuan/pengesahan, snapshot + PDF immutable per versi + hash, penolakan tab lama, revisi resmi, dan pencarian nama/NIS dengan filter tahun/jurusan. [Rincian dan penanganan sertifikat lama](SERTIFIKAT_ARSIP_VERSI.md). Migrasi additive sudah dijalankan setelah backup, tanpa fresh/reseed.

Urutan kerja berikutnya:

1. **Sertifikat lama:** satu dokumen terbit pada database kerja belum memiliki arsip. Pemeriksaan/rekonstruksi memerlukan koordinator; tidak dilakukan diam-diam. Flag merah tetap menjelaskan kondisi ini dan kebutuhan UAT.
2. **Timeline — selesai:** daftar roster, card jurusan → tabel siswa, termasuk belum ditempatkan; flag dicopot setelah tes regresi/browser/MySQL lulus.
3. **Monitoring — selesai untuk temuan audit:** penanggung jawab, kategori, status, riwayat, pencarian/filter, guru opsional serta ID editor sudah diterapkan dan diuji; lanjut evaluasi pengguna.
4. **Penilaian — selesai untuk temuan audit:** label historis roster, bukti sumber industri privat, filter rekap tanpa selector siswa; lanjut evaluasi pengguna dan kelengkapan dokumen asli.
5. **Dashboard — selesai untuk temuan audit:** tag tahun, siswa unik, rekap PKL/sekolah per jurusan dan keseluruhan sesuai hak akses; lanjut evaluasi angka roster nyata.
6. **UAT paralel:** pengguna mencoba kiosk; uji HP, HTTPS, cetak fisik, backup/restore, dan beban transaksi tetap dicatat terpisah dari kelulusan tes kode.

Bagian di bawah adalah riwayat tahapan sebelumnya; jangan memakai daftar lama untuk menyimpulkan perbaikan di atas belum dikerjakan.

Pembaruan jurnal 23 Oktober 2026: review koordinator/guru opsional, foto opsional, nilai dicatat siswa, validasi industri melalui browser terpercaya tanpa PIN, snapshot/revisi/waktu scan, dan Excel template enam entri telah diterapkan. Lihat [panduan jurnal QR dan Excel](JURNAL_INDUSTRI_QR_EXCEL.md). Uji HP nyata/cetak fisik masih diperlukan. Prioritas P0 berikutnya adalah pengamanan sertifikat; urutan audit lama di bawah dipertahankan sebagai riwayat.

## Prioritas aktif menu utama — audit 23 Oktober 2026

Lihat [Audit Menu Utama](AUDIT_MENU_UTAMA.md) untuk bukti, keterbatasan, dan kriteria selesai. Status ini menggantikan urutan historis di bawah, bukan menyatakan alur bisnis sudah diperbaiki.

1. P0 — Jurnal & Foto: koordinator dapat review tanpa guru; bukti foto dan jalur koreksi setelah PKL.
2. P0 — Sertifikat: kesiapan sebelum pengesahan dan snapshot/arsip dokumen per versi.
3. P1 — Timeline: daftar dari roster, jurusan → siswa, termasuk belum ditempatkan.
4. P1 — Monitoring: penanganan kasus koordinator, guru opsional, actor ID.
5. P1 — Penilaian: identitas historis, bukti industri, filter rekap; tetap cari nama/NIS.
6. P2 — Dashboard: filter tag, hitung siswa unik, ringkasan fase sekolah/jurusan.

Tag RED FLAG/UNSTABLE dan penjelasan sudah ditambahkan. Cek ulang baca-saja: `php artisan simmag:audit-main-menu --year=2026/2027`. Belum ada scheduler otomatis. Tidak ada reset/migrasi database pada tahap audit ini.

Pembaruan terbaru 23 Oktober: Periode menjadi tag tahun ajaran, penempatan terhubung langsung ke roster dan mendukung checkbox multi-siswa/jurusan. Sumber tanggal/status konsumen dialihkan ke Kontrol PKL. Detail: [Tag PKL dan Penempatan](TAG_PKL_DAN_PENEMPATAN.md). Snapshot penerbitan sertifikat dan hak pemeriksaan jurnal masih perlu tahap lanjutan.

## Audit PKL dan sertifikat, akun siswa, serta UI

Lihat [audit dan daftar perbaikan UI](AUDIT_PKL_SERTIFIKAT_UI.md). Aturan tetap: input nilai mencari siswa melalui **nama atau NIS**, bukan dropdown seluruh siswa. Akun baru otomatis memakai NIS dan password awal tanggal lahir DDMMYYYY; akun lama tidak direset. Penyatuan relasi penempatan-roster dan snapshot penerbitan sertifikat masih menjadi prioritas berikutnya.

Pembaruan 23 Oktober: impor kini di Data Siswa, template berisi contoh, Kontrol PKL memakai card jurusan dan pencarian siswa dari halaman awal. Red flag Data Siswa dilepas setelah validasi jurusan bersama dan sinkronisasi profil-roster terbaru diterapkan. Seeder terpisah/prompt tersedia di [panduan contoh](CONTOH_DATA_SISWA.md).

## Template ketentuan penilaian per jurusan

- Koordinator membuat template berisi aspek, uraian kompetensi, jenis isian angka/teks, bobot, judul dan keterangan sertifikat depan.
- Template dapat dipakai ulang untuk seluruh siswa pada jurusan yang sama. Salin/revisi membuat versi baru, bukan mengubah penilaian sebelumnya.
- Ketentuan disalin ke penilaian siswa; nilai industri dan ID koordinator penginput dicatat terpisah. Guru tidak memiliki hak input nilai.
- Total bobot 100%. Contoh 80/20 dapat dinyatakan sebagai empat aspek masing-masing 20% dan laporan 20%.
- Nilai akhir resmi dicatat dari dokumen sumber; bila berbeda dari hasil hitung, alasan harus dicatat. Tidak ada asumsi pembulatan otomatis.
- PDF baru mencetak keterangan depan serta aspek, uraian dan hasil di belakang. PDF lebih dari dua halaman ditolak dengan penjelasan tata letak.
- Penilaian dan sertifikat skema lama tetap dapat dibaca dengan format asalnya.

## Lanjutan

- Tampilan card jurusan dengan daftar siswa di detail: selesai 23 Oktober 2026, termasuk pencarian lintas jurusan dari halaman awal.
- Unggah template DOCX dan pilihan penandatangan mengikuti paket dokumen, beserta desain resmi yang akan diberikan.
- Jurnal siswa menjadi unggahan foto kegiatan dan halaman penting; pemeriksaan oleh koordinator. Tahap ini baru melepaskan syarat review jurnal guru bagi sertifikat penilaian berbasis template.

## Timeline PKL berbasis tanggal — diterapkan 22 Oktober 2026

- Fase siswa dihitung otomatis: belum dijadwalkan, terjadwal, sedang PKL, lalu masuk sekolah.
- Tanggal selesai masih termasuk hari PKL; siswa masuk sekolah pada hari kalender berikutnya.
- Tidak ada konfirmasi mulai/selesai oleh siswa atau guru.
- Koordinator dapat menerapkan tanggal ke satu, beberapa, atau seluruh jurusan, kemudian mengecualikan siswa lewat checkbox.
- Checkbox yang dilepas hanya mengecualikan siswa dari batch dan tidak mengubahnya menjadi tidak magang.
- Jadwal individual dipakai untuk kasus khusus dan dilindungi dari penimpaan batch berikutnya.
- Pembuat batch dan editor terakhir dicatat berdasarkan ID akun koordinator.
- Endpoint tahapan manual lama mengembalikan status 410. Tabel lama sementara dipertahankan untuk keamanan migrasi data.

## Selesai

- Roster seluruh siswa per tahun ajaran.
- Delapan jurusan resmi sekolah.
- Counter fase PKL tingkat sekolah dan per jurusan.
- Batch jadwal tanggal, pengecualian individual, impor CSV, dan ekspor CSV kompatibel Excel.
