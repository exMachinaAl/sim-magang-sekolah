# Timeline dari roster — 24 Oktober 2026

## Hasil

Temuan flag Timeline telah ditutup. Status audit `stable` menghilangkan badge/peringatan Timeline dari UI, bukan menghapus rekam audit. Modul lain tetap mempertahankan flag masing-masing. Tidak ada migrasi, reseed, fresh, atau perubahan jadwal database kerja pada tahap ini.

- Koordinator/admin: card jurusan pada tag tahun terpilih → tabel siswa (20 baris per halaman) → satu timeline siswa.
- Guru: langsung tabel siswa penugasannya; bukan seluruh siswa sekolah. Tidak perlu guru sebagai pengendali rutin.
- Siswa: otomatis roster miliknya, termasuk ketika industri belum ditentukan. Tahun berjalan dipilih jika tersedia; tahun lain dapat dipilih dari roster yang memang dapat diakses.
- Roster tanpa akun terkait masih terlihat oleh koordinator berdasarkan identitas roster; tidak diklaim dapat login sebelum akunnya terhubung.
- Penempatan bukan syarat tampil. Tautan jurnal hanya muncul jika sudah ada penempatan. Riwayat jurnal/sertifikat tidak diubah.
- Identitas nama/NIS memakai profil terhubung (fallback roster). Kelas/jurusan selalu roster tahun yang dilihat, bukan kelas/jurusan profil terbaru.
- Search dari root: jurusan, nama, NIS, NISN, nama/NIP guru. Hasil siswa langsung menuju detail. Query dibatasi hak akses sebelum pencarian, bukan disaring setelah seluruh sekolah dimuat.
- Link lama `placement_id` dan `teacher_id` tetap dilayani dengan pemeriksaan cakupan; link baru memakai `roster_id`. Kombinasi ID/tahun tidak cocok ditolak.

## Tanggal dan status

- Acuan satu tanggal WIB (Asia/Jakarta) untuk ringkasan dan baris pada request yang sama.
- Tanggal mulai: masuk PKL. Tanggal selesai: masih PKL. Sehari sesudah selesai: masuk sekolah.
- Tidak magang mengalahkan tanggal lama yang mungkin masih tersimpan; tidak ditampilkan sebagai PKL selesai/masuk sekolah.
- Jadwal kosong, tidak lengkap, atau selesai sebelum mulai: belum dijadwalkan, dengan petunjuk pemeriksaan koordinator. Data tidak diperbaiki diam-diam oleh GET.
- Fase masuk sekolah adalah kalkulasi jadwal, bukan bukti absensi. Halaman perlu dimuat ulang bila dibiarkan terbuka melewati tengah malam; tidak ada tombol konfirmasi manual.
- Editor jadwal ditampilkan dengan ID bila tersedia. Sumber batch/individual membaca Kontrol PKL; tanggal tag tahun tidak dipakai.

## Efisiensi dan pengujian

Hasil suite akhir tahap ini: **106 tes / 1.949 assertion lulus**, termasuk tujuh tes khusus Timeline dan regresi pelepasan flag hanya untuk modul yang selesai.

Ringkasan memakai `GROUP BY major` + agregasi fase di SQL. Tidak lagi memuat seluruh penempatan/roster lalu memfilter dan memotong koleksi di PHP. Card dipaginasi 12 jurusan; tabel siswa 20 entri. Detail hanya memuat satu roster beserta relasinya.

Regresi khusus mencakup 30 roster/8 jurusan (12 tanpa penempatan), pencarian, akses guru/siswa lintas roster/tahun, tanggal batas dan tengah malam WIB, pengecualian tidak magang, tanggal terbalik, konteks tahun historis, link lama, validasi parameter, dan GET read-only. Data sintetis tambahan 240 roster membuktikan SQL `LIMIT 20 OFFSET 20`/jumlah hasil, bukan benchmark beban produksi.

Pemeriksaan MySQL lokal baca-saja: `php scripts/verify-timeline.php`. Hasil: 30 roster, 8 jurusan; hitungan fase SQL sama dengan fungsi fase model. Script diagnostik memuat data untuk membandingkan hasil; **controller produksi tidak melakukan pemuatan penuh tersebut**.

Smoke test Edge pada database SQLite/temp storage terpisah mencakup card → tabel → detail, search NIS tanpa penempatan, login siswa pada viewport 390px untuk kondisi belum ditempatkan **dan PKL aktif**, serta penolakan akses guru/siswa ke roster lain. Tes menemukan aturan CSS tanggal lama menimpa layout mobile; aturan itu sudah diperbaiki dan diperiksa dengan assertion tidak ada overflow horizontal. Tes juga memastikan badge/banner Timeline tidak lagi muncul. Kiosk dan arsip sertifikat ikut diperiksa agar tidak regresi. Script: `scripts/journal-browser-smoke.cjs`; instalasi dependency di STABILISASI_JURNAL_DAN_RESET_UJI.md.

STABLE di sini berarti masalah fungsional yang tercatat pada flag Timeline sudah terselesaikan dan tes lulus. Tidak berarti menjamin semua skala/beban atau data rusak di luar aturan aplikasi. Beban satu sekolah nyata dan umpan balik penggunaan tetap perlu dipantau. Tidak menunggu uji kamera/cetak untuk melepas flag Timeline, sebab uji tersebut milik modul jurnal/sertifikat.

Tahap berikutnya: Monitoring sebagai catatan kasus koordinator, guru opsional; kemudian Penilaian dan Dashboard. Jurnal/kiosk dan pengarsipan dokumen sertifikat lama tetap menjalani evaluasi terpisah.
