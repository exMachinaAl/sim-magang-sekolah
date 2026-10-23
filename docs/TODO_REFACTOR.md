# Catatan Refactor SIMMAG

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
