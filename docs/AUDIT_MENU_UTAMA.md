# Audit menu utama — 23 Oktober 2026

> Pembaruan setelah audit: [Jurnal, perangkat industri, dan Excel](JURNAL_INDUSTRI_QR_EXCEL.md) sudah diimplementasikan. Review koordinator, foto opsional, pencarian NIS, koreksi setelah PKL, validasi QR per versi, dan Excel enam entri telah menggantikan temuan jurnal di bawah. Tag jurnal kini UNSTABLE untuk uji perangkat/cetak lapangan, bukan RED FLAG karena koordinator 403. Bagian audit berikut merupakan catatan kondisi **sebelum upgrade**; masalah sertifikat/timeline/monitoring lainnya tetap terbuka. Indikator submitted_journals_without_teacher sekarang bukan jurnal buntu karena koordinator dapat meninjau.

## Batas pekerjaan

Enam menu: Dashboard, Timeline PKL, Jurnal & Foto, Penilaian (termasuk Template Ketentuan), Monitoring, dan Sertifikat. Menu manajemen tidak direfactor dalam tahap ini. Tidak ada migrasi, reset, reseed database lokal, atau perubahan aturan bisnis. Penambahan hanya penanda UI, diagnostik baca-saja, dokumentasi, dan pengujian.

Kesimpulan: **data manajemen sudah terbaca, tetapi sebagian cakupan, otoritas pelaku, dan arsip dokumen belum mengikuti mekanisme baru.** Jangan menyamakan perbedaan jumlah roster dan penempatan dengan kehilangan data.

RED FLAG berarti alur penting terhambat atau integritas dokumen berisiko. UNSTABLE berarti sebagian sudah benar tetapi cakupan/rekap/UI belum konsisten. Warna bukan hasil pemantauan otomatis dan tidak menyatakan semua data modul rusak. P0 = dahulukan sebelum operasional penuh; P1 = sinkronisasi lanjutan; P2 = ringkasan/kenyamanan setelah sumber dan alur benar.

## Sumber data yang seharusnya dipercaya

- `students` + `users`: identitas/akun siswa terkini, NIS untuk pencarian.
- `internship_rosters`: keikutsertaan, kelas/jurusan per tahun, tanggal individual/batch dan fase siswa. Tanggal akhir masih hari PKL; esoknya masuk sekolah, tanpa konfirmasi manual.
- `internship_periods`: hanya tag tahun ajaran. `is_active` bukan penentu siswa sedang PKL.
- `placements.internship_roster_id`: tautan siswa-tahun ke industri dan guru opsional. Tidak semua roster harus mempunyai penempatan.
- `assessments`: nilai industri yang dicatat koordinator; `template_snapshot` menyimpan ketentuan penilaian. Ini **belum** snapshot keseluruhan sertifikat terbit.
- Guru hanya pendamping kasus tertentu. Tidak memiliki guru adalah kondisi bisnis yang sah, bukan alasan menghilangkan layanan koordinator.

## Hasil dan urutan perbaikan

| Urutan | Menu / tag | Sudah sesuai | Masalah terpenting / dampak |
|---|---|---|---|
| 1 · P0 | Jurnal & Foto — RED FLAG | Akses siswa terbatas ke penempatannya; jadwal kirim memakai roster | Review masih khusus guru di route, controller, dan view. Koordinator 403; tanpa guru jurnal tidak bisa diproses. |
| 2 · P0 | Sertifikat — RED FLAG | Nilai wajib disahkan; template ketentuan disalin; tanggal PDF dari roster | Identitas, perusahaan, tanggal memakai data hidup. Cetak ulang dapat berubah tanpa versi baru. Jadwal kosong baru ditolak saat PDF, bukan saat pengajuan/persetujuan. |
| 3 · P1 | Timeline PKL — UNSTABLE | Fase otomatis dan tanggal membaca roster; tidak memakai tanggal tag | Daftar berawal dari penempatan, bukan roster. Siswa tanpa industri tidak muncul meskipun sudah dijadwalkan. Koordinator masih dinavigasikan berdasarkan guru. |
| 4 · P1 | Monitoring — RED FLAG | Catatan terhubung ke penempatan dan akses per peran | Form dan endpoint hanya guru. Koordinator tidak bisa mencatat kasus; model masih menamai pencatat `teacher_id`. |
| 5 · P1 | Penilaian — UNSTABLE | Input/validasi koordinator; pencarian nama/NIS; template jurusan roster; penguncian nilai dan revisi | Label kelas/jurusan menggunakan profil terbaru, bisa berbeda dengan roster historis yang menentukan template. Belum ada lampiran sumber nilai. Pencarian tidak memfilter rekap. |
| 6 · P2 | Dashboard — UNSTABLE | Profil dan status siswa membaca roster; ringkasan jurnal/sertifikat membaca database | Ringkasan lintas tahun tanpa filter; jumlah Siswa Terpantau adalah jumlah penempatan, bisa menggandakan siswa lintas tahun. Belum ringkasan fase sekolah/jurusan. |

Urutan 1 dipilih karena hambatan operasional sudah terlihat pada data lokal. Urutan 2 sama-sama P0 dan wajib sebelum memakai sertifikat sebagai dokumen final. Timeline diperbaiki sebelum rekap dashboard agar cakupan data punya definisi yang sama. Perbaikan monitoring tidak boleh mewajibkan kunjungan rutin guru; arah baru adalah penanganan kasus. Template/rubrik penilaian yang sudah bekerja dipertahankan.

## Bukti implementasi dan tindak lanjut

### 1. Jurnal & Foto

- Bukti: `routes/web.php` middleware `role:guru` pada `journals.review`; `JournalController::review()` juga memeriksa guru; form review di `resources/views/journals/show.blade.php` hanya tampil untuk guru.
- `Access::placements()` membatasi guru ke `teacher_id` sendiri. Pada penempatan tanpa guru, kedua guru demo tidak bisa mengakses jurnal untuk review (404), koordinator ditolak (403).
- `JournalController::store()` masih mewajibkan `activity` dan `outcome`. Foto yang dikirim belum dibedakan kegiatan vs halaman jurnal penting.
- Revisi hanya bisa disimpan saat fase `pkl` dan belum ada record sertifikat, termasuk sertifikat yang masih menunggu validasi. UI mengatakan belum diterbitkan, tetapi pengecekan sebenarnya adalah keberadaan record. Perlu kebijakan koreksi setelah PKL, bukan menambah konfirmasi selesai manual.
- Pencarian daftar dan ekspor hanya nama, belum NIS. PDF maksimal 300 baris dan batas sudah dicantumkan di dokumen; belum cocok sebagai ekspor lengkap satu sekolah tanpa pembagian filter.
- **Selesai jika:** koordinator dapat memproses bukti tanpa guru, guru hanya sesuai mandat kasus, actor ID terekam, akses siswa lain tetap ditolak, foto kegiatan/jurnal dapat dibedakan, jalur revisi setelah PKL disepakati dan diuji, pencarian nama/NIS bekerja.

### 2. Sertifikat

- Bukti: `CertificateController::create()` memeriksa nilai (dan jurnal untuk skema lama), tetapi tidak memeriksa kelengkapan jadwal; `approve()` dapat mengesahkan; `pdf()` baru menolak jadwal kosong dengan 422.
- `resources/views/certificates/template-pdf.blade.php` dan `certificates/pdf.blade.php` membaca identitas/perusahaan dari relasi hidup. Tanggal diambil dari roster saat cetak. Mengganti nama/tanggal setelah penerbitan mengubah isi render tanpa perubahan `version`.
- `Certificate` belum menyimpan snapshot isi dokumen atau arsip PDF per versi. Snapshot template pada penilaian tidak membekukan nama, NIS, kelas, perusahaan, atau tanggal PKL.
- Pemilihan kandidat masih dropdown semua penempatan tanpa sertifikat dan memuat jurnal ke memori. Format resmi/penandatangan masih tahap template sementara; bukan fitur yang dinyatakan selesai dalam audit ini.
- **Selesai jika:** kesiapan data dicek sebelum pengajuan dan saat pengesahan; snapshot identitas/roster/industri/hasil/ketentuan/penandatangan serta arsip per versi dibekukan; perubahan master tidak mengubah cetak ulang versi lama; revisi resmi menghasilkan versi baru dengan actor ID. Cari kandidat lewat nama/NIS dan filter tag/jurusan.
- Kebijakan apakah pengajuan harus menunggu PKL berakhir perlu diputuskan saat implementasi; audit tidak menetapkan aturan baru tersebut diam-diam.

### 3. Timeline PKL

- Bukti: `TimelineController::index()` memulai daftar melalui `Access::placements()`; tag untuk siswa juga dibatasi penempatan. `dateSchedule()` sendiri sudah benar menggunakan roster langsung.
- Uji siswa dengan tanggal aktif tetapi tanpa penempatan: Dashboard menunjukkan Sedang PKL; Timeline tidak memiliki baris siswa/tag untuk dibuka.
- Guru opsional sudah ditampung kelompok Tanpa guru pendamping / Koordinator, tetapi sumber navigasi tetap guru. Identitas kelas/jurusan view masih profil terkini, bukan roster tahun terpilih.
- Seluruh penempatan pada satu tag diambil lewat `get()` sebelum pencarian/paginasi koleksi. Ini risiko skala berdasarkan kode, **bukan** klaim hasil benchmark atau beban produksi.
- **Selesai jika:** roster tanpa industri tetap terlihat dengan penjelasan belum ditempatkan; siswa langsung membuka jadwal dirinya; koordinator membuka jurusan → siswa; nama/NIS dan jurusan dicari di database; akses guru tetap dibatasi siswa yang ditugaskan; label historis sesuai roster. Uji tanggal batas dan pengecualian batch tetap lulus.

### 4. Monitoring

- Bukti: `MonitoringController::store()` dan route hanya mengizinkan guru; view `monitoring/index.blade.php` hanya menyediakan form untuk guru.
- Kolom `teacher_id` dipakai sebagai pencatat, bukan pemisahan actor dan pihak yang ditugaskan. `follow_up` hanya teks bebas; tidak ada status kasus/penanggung jawab terstruktur.
- Pemilihan masih dropdown seluruh penempatan, tanpa filter tag/jurusan/nama/NIS. Tanggal kunjungan hanya dicek tidak melewati hari ini; kebijakan kunjungan pra/pasca-PKL belum dijelaskan. Jangan langsung menganggap seluruh kunjungan di luar jadwal tidak sah.
- **Selesai jika:** koordinator dapat membuka/menutup kasus dan menugaskan guru secara opsional, actor ID berbeda dari assignee, histori jelas, pencarian dan pembatasan akses diuji.

### 5. Penilaian

- Bukti: `AssessmentController` memilih template berdasarkan `placement.roster.major`, tetapi view `assessments/index.blade.php` menampilkan `student.major/class_name`. Perubahan profil antar tahun dapat membuat label dan ketentuan terlihat bertentangan.
- Snapshot ketentuan, validasi bobot/nilai, alasan selisih nilai akhir, serta pemeriksaan revision sudah ada. Jangan merombak bagian ini tanpa kebutuhan.
- `q` mencari kandidat penempatan, bukan `$items` Rekap Penilaian. Belum ada filter rekap tahun/jurusan dan lampiran sumber nilai industri.
- **Selesai jika:** konteks identitas historis konsisten; nama/NIS tetap input pencarian (bukan selector siswa); bukti sumber dapat ditelusuri; rekap bisa difilter; perubahan ketentuan tidak mengubah nilai yang disahkan. Lampiran sumber adalah kebutuhan penguatan jejak bukti, bukan klaim data angka saat ini salah.

### 6. Dashboard

- Bukti: `DashboardController` menghitung `Student::count()` untuk koordinator/admin, tetapi jumlah penempatan untuk guru/siswa; jurnal/sertifikat menggunakan penempatan semua tahun. Tidak ada pilihan tahun.
- Saat ini 18 penempatan dari 18 siswa, sehingga penggandaan belum terlihat pada seed. Risiko hitung ganda muncul ketika siswa mempunyai penempatan pada beberapa tag tahun. Total Siswa 30 pada dashboard koordinator sendiri tidak salah.
- **Selesai jika:** label menjelaskan siswa unik vs penempatan, filter tahun berlaku konsisten, counter fase mengambil roster sekolah/jurusan, dashboard siswa tidak menyerupai tugas rutin guru, angka cocok dengan Kontrol PKL pada tag yang sama.

## Pemeriksaan database lokal (baca-saja)

Pada 23 Oktober 2026, tag 2026/2027:

| Indikator | Hasil | Interpretasi |
|---|---:|---|
| Seluruh siswa / roster tahun terpilih | 30 / 30 | Akun/profil dan roster sudah terisi. |
| Penempatan / siswa unik ditempatkan | 18 / 18 | 18 siswa sudah punya industri. |
| Roster tanpa penempatan | 12 | 6 belum dijadwalkan dan 6 tidak magang; bukan kehilangan data. |
| Roster sudah bertanggal tanpa penempatan | 0 | Belum terjadi pada data lokal; celah timeline direproduksi di database pengujian. |
| Penempatan tanpa roster / ketidakcocokan siswa-tag | 0 / 0 | Tautan langsung pada penempatan saat ini sesuai. |
| Penempatan tanpa guru | 6 | Sah menurut mekanisme koordinator; jangan dianggap korup. |
| Jurnal submitted tanpa guru | 2 | Alur review buntu pada mekanisme saat ini. |
| Sertifikat / sertifikat tanpa jadwal lengkap | 2 / 0 | Data lokal saat audit belum mengalami gagal cetak akibat jadwal kosong. |
| Sertifikat sudah diterbitkan dengan data hidup | 1 | Isi belum dibekukan; bukan bukti dokumen telah berubah. |
| Penempatan tanpa tag tahun, seluruh tahun | 0 | Tidak ada penempatan legacy tanpa tag akademik yang terdeteksi. |

Fase: tidak magang 6, belum dijadwalkan 6, terjadwal 6, sedang PKL 6, masuk sekolah 6. Fase akan berubah mengikuti tanggal; angka di atas adalah snapshot audit, bukan angka yang harus dipaksa tetap.

Seeder mengisi beberapa jurnal approved dengan ID koordinator langsung ke database. **Itu bukan bukti endpoint review koordinator sudah bekerja.** Uji HTTP membuktikan endpoint masih 403. Seeder tidak diubah pada pekerjaan ini.

## Cara cek ulang secara berkala

Jalankan setelah impor/perubahan jadwal, sebelum penerbitan sertifikat, dan setelah setiap tahap refactor:

```powershell
php artisan simmag:audit-main-menu --year=2026/2027
php artisan simmag:audit-main-menu --year=2026/2027 --json
php artisan test --filter=MainMenuAudit
php artisan test
```

Command pertama/kedua hanya SELECT, tanpa identitas pribadi, migrasi, reseed, koreksi data, atau pengiriman keluar. Default `--year` adalah tahun ajaran berjalan; untuk periode historis berikan tag eksplisit. `--json` cocok untuk perbandingan hasil pemeriksaan berikutnya. Exit 0 berarti command berhasil membaca, **bukan** seluruh menu sehat; tahun tidak valid menghasilkan exit 2.

Tidak ada cron/scheduler otomatis dipasang. Frekuensi dan lingkungan target belum ditentukan. Indikator hanya membantu melihat gejala data: tanggal kosong, hubungan putus/tidak cocok, dan jurnal tanpa guru. Command bukan audit menyeluruh seluruh aturan database, bukan pengujian hak akses live, dan tidak mendeteksi bahwa bug sudah diperbaiki secara otomatis.

`tests/Feature/MainMenuAuditTest.php` memeriksa enam tag/banner, pembatasan role, isolasi dari manajemen, lingkup tahun, indikator relasi, serta memastikan inspeksi hanya menjalankan SELECT. `MainMenuAuditEvidenceTest.php` adalah uji karakterisasi empat masalah terbuka: lolosnya test membuktikan masalah masih ada, **bukan** izin menerima perilaku salah selamanya. Setelah perbaikan, ganti assertion dengan perilaku yang benar.

Semua fixture/uji perubahan identitas atau jadwal dijalankan pada SQLite `:memory:` menurut `phpunit.xml`, dengan penyimpanan foto palsu pada pengujian audit. Database sekolah lokal tidak diubah untuk mereproduksi bug.

Checklist penutupan tiap tahap:

1. Uji data tanpa guru, tanpa penempatan, dua tahun ajaran, delapan jurusan, dan jadwal individual/batch yang berbeda.
2. Uji dua akun koordinator, siswa sendiri vs siswa lain, guru terkait vs guru lain; jangan memperluas akses lewat label UI.
3. Uji tanggal sebelum mulai, tepat mulai, tepat selesai, dan sehari sesudah selesai.
4. Bandingkan angka/cakupan dengan roster Kontrol PKL; jangan membandingkan jumlah penempatan dengan jumlah seluruh siswa tanpa konteks.
5. Ubah data master setelah sertifikat terbit pada fixture uji; dokumen terbit harus tetap sama setelah perbaikan snapshot.
6. Perbarui tanggal audit, temuan, uji, dan `config/main_menu_health.php` hanya setelah kriteria selesai terpenuhi. Penghapusan entry modul menghilangkan tag/banner; label manajemen tetap di `config/legacy_modules.php`.

Belum dilakukan: uji beban ribuan siswa, pemeriksaan visual browser/perangkat, verifikasi desain sertifikat resmi, maupun simulasi seluruh kondisi konkurensi. Halaman telah diuji melalui respons HTTP/render Blade, bukan klaim uji visual manual.

Verifikasi tahap audit: `php artisan test` lulus **71 tes / 659 assertion**. Termasuk 9 tes audit (149 assertion); 4 di antaranya sengaja mereproduksi masalah terbuka, bukan menandakan masalah tersebut sudah diperbaiki. Command diagnostik juga berhasil dijalankan baca-saja pada database lokal MySQL.
