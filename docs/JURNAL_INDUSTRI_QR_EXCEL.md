# Jurnal siswa, perangkat industri, dan Excel

Implementasi 23 Oktober 2026. Ini adalah upgrade jurnal; perbaikan besar sertifikat, navigasi timeline, dan monitoring tetap mengikuti backlog masing-masing.

## Alur pengguna

1. Koordinator membuka **Jurnal & Foto → Pembimbing Industri & Perangkat QR**.
2. Pilih perusahaan, cari siswa melalui nama/NIS, centang siswa, lalu daftarkan nama pembimbing (misalnya Dodi). Penugasan tambahan/pelepasan dapat dilakukan pada daftar pembimbing. Guru tidak dapat mendaftarkan identitas atau perangkat industri.
3. Koordinator membuat QR pendaftaran dan menunjukkannya **langsung kepada Dodi**, bukan siswa/grup umum.
4. Dodi memindai lewat Google Camera/Lens, membuka link, dan browser otomatis didaftarkan. Tanpa PIN. QR sekali pakai berlaku 24 jam; membuat QR baru membatalkan undangan lama yang belum digunakan. Perangkat yang sudah terdaftar tidak otomatis dicabut saat membuat undangan baru.
5. Siswa mencatat kegiatan melalui akun sendiri. Foto opsional. Data tambahan: ruang kerja, jam mulai/selesai, peralatan/bahan; hasil/kendala opsional. Tetap satu ringkasan per tanggal sesuai database lama; beberapa kegiatan sehari ditulis bersama.
6. Dodi membaca kegiatan dan menyebutkan nilai. Siswa memasukkan angka persis, menyimpan, lalu membuka **Tampilkan ringkasan & QR untuk DUDI**. Tombol tersedia setelah jurnal bukan draf dan nilai terisi.
7. Setelah isi cocok, Dodi memindai QR siswa dan membuka link pada browser terdaftar. Halaman otomatis mengirim POST untuk memvalidasi. Tidak perlu login sekolah/PIN atau mengetuk tombol persetujuan rutin; tombol manual hanya fallback jika JavaScript tidak berjalan.
8. Jika scan gagal/tidak dilakukan, jurnal tetap tercatat, bisa diedit, dan bisa diekspor; statusnya **Belum divalidasi industri**.

Google Camera harus membuka link hasil scan; membaca QR tanpa membuka link tidak menghubungi server. Browser tetap memerlukan koneksi internet/jaringan ke aplikasi. Ini bukan aplikasi offline-sync atau identifikasi hardware.

## Makna status dan kewenangan

- Siswa adalah pencatat kegiatan/nilai dari industri, bukan pihak yang memberikan nilai.
- draft: belum dimasukkan ke Excel. submitted: tercatat, bukan antrean persetujuan wajib.
- Koordinator atau guru yang ditugaskan boleh menandai **Ditinjau sekolah** dan menambahkan catatan. Ini tidak memvalidasi industri, tidak menentukan kelulusan, dan tidak mengunci jurnal.
- Status approved dipertahankan untuk kompatibilitas database tetapi labelnya kini Ditinjau sekolah. Status revision/rejected lama tetap sebagai arsip; tidak menjadi workflow investigasi baru.
- Identitas industri menggunakan tabel tersendiri, **bukan mengaktifkan kembali akun legacy_industri**. Browser industri hanya mengakses penugasan dan konfirmasi jurnal, tidak dashboard/manajemen sekolah.
- Koordinator mengatur penugasan siswa, aktivasi pembimbing, dan pencabutan perangkat. Nonaktifkan pembimbing mencabut perangkat dan undangan terbuka; mengaktifkan kembali membutuhkan pendaftaran perangkat baru.
- Perselisihan/hal mencurigakan ditangani offline. Review sekolah dan konfirmasi industri bukan dua tahap yang saling mewajibkan.
- Nilai jurnal tidak otomatis mengubah assessments.final_score/nilai sertifikat. Pengisian dan pengesahan nilai akhir tetap koordinator; sertifikat template tidak mensyaratkan semua jurnal di-scan. Aturan sertifikat skema lama belum direfactor di tahap ini.

## Tanggal, versi, dan audit

- Tanggal kegiatan wajib dalam jadwal roster Kontrol PKL, tidak melewati hari ini, dan bukan untuk siswa yang dinyatakan tidak magang.
- Penginputan/koreksi boleh setelah tanggal selesai PKL maupun setelah sertifikat dibuat, selama tanggal kegiatan sesuai jadwal. Hal ini tidak mengubah nilai akhir sertifikat.
- Waktu scan adalah waktu server aktual (Asia/Jakarta), terpisah dari tanggal kegiatan. Tidak ada batas harus scan pada hari yang sama.
- QR jurnal berlaku 24 jam sejak dibuat, bukan sejak tanggal kegiatan. Setelah kedaluwarsa siswa boleh membuat QR baru untuk jurnal lama. Membuat QR membatalkan QR belum terpakai sebelumnya.
- Setiap penyimpanan perubahan menaikkan revisi. QR lama ditolak; review/validasi versi baru kembali kosong. Snapshot validasi versi lama tetap ada.
- Scan berulang untuk versi sama idempotent: satu record per jurnal+revisi. Pembimbing dan waktu scan pertama tidak ditimpa.
- Audit menyimpan ID pembimbing, ID perangkat, ID jurnal/revisi, snapshot kegiatan/nilai/foto terkait, IP, dan waktu scan. Event kiosk tidak diatribusikan ke akun sekolah yang kebetulan login pada browser yang sama.
- Revisi formulir diperiksa di server untuk mencegah tab lama menimpa perubahan baru. Penguncian transaksi digunakan untuk QR/revisi/perangkat.

## Kepercayaan perangkat: kelonggaran yang disengaja

Identitas Dodi disimpan dalam cookie browser terenkripsi, HttpOnly, SameSite=Lax, terbatas path /industry; server hanya menyimpan hash token. Masa berlaku perangkat 180 hari, tidak diperpanjang otomatis, dan dapat dicabut.

Ini **bukti konfirmasi dari browser yang didaftarkan sebagai Dodi**, bukan bukti biometrik bahwa Dodi sendiri memegang HP. Siapa pun yang memakai browser tersebut dapat memvalidasi siswa yang ditugaskan. Siapa pun yang memperoleh QR pendaftaran sebelum digunakan dapat mendaftarkan browser sebagai Dodi. Kelonggaran ini mengikuti permintaan pengguna; QR pendaftaran wajib dibagikan secara privat. Tidak ada PIN harian/OTP yang ditambahkan diam-diam.

Scan GET dan HEAD tidak melakukan enrollment/validasi; perubahan memakai POST dengan CSRF. URL token tidak memuat data jurnal/identitas. Response sukses maupun kesalahan kiosk memakai no-store, no-referrer; QR dibuat lokal tanpa layanan pihak ketiga. Kesalahan kiosk tidak menampilkan SQL, token, atau stack trace meskipun debug aktif. Token tidak ditulis ke audit. Server/reverse proxy perlu meredaksi URL /industry/enroll/* dan /industry/scan/* pada access log karena link membawa token akses. Gunakan HTTPS di lingkungan produksi; middleware menolak HTTP di luar local/testing.

Kehilangan cookie, mengganti browser, incognito, memindahkan profil browser, atau memakai browser in-app Camera yang berbeda dapat memengaruhi pengenalan perangkat. Daftarkan ulang dan cabut perangkat lama bila diperlukan. Jika APP_KEY diganti, cookie lama tidak bisa dipakai.

## Excel

Sumber template disalin tanpa mengubah lampiran asli: resources/templates/jurnal-pkl.xlsx.

- Tombol Excel ada pada detail penempatan siswa, tersedia sesuai akses siswa/guru/koordinator/admin.
- Filter tanggal opsional, boleh hanya tanggal awal atau hanya tanggal akhir. Jurnal draf tidak diekspor; jurnal tanpa foto/validasi tetap diekspor.
- Kelompok minggu Senin–Minggu; minggu ke-1 adalah minggu yang memuat tanggal mulai PKL siswa. Tanggal jadwal dari roster, tag tahun bukan tanggal.
- Maksimal enam entri per lembar Jurnal. Minggu yang memuat tujuh entri menghasilkan lembar lanjutan. Tidak ada pemotongan data setelah enam entri.
- Judul, lebar kolom, garis, dan merged cells berasal dari template. Print area A1:L23, A4 landscape, satu lembar per halaman. Foto tidak dimasukkan.
- Angka nilai 0–100 dengan maksimal dua angka desimal. Sel angka di kolom predikat tetap nilai persis, bukan tanda centang/rata-rata rentang.
- Rentang kontinu untuk desimal: B− [75,81), B [81,86), B+ [86,91), A− [91,96), A [96,100]. Contoh 88.25 masuk kolom I (B+); 95.99 masuk J (A−). Ini melengkapi label rentang bilangan bulat pada template.
- Nilai di bawah 75 tetap tersimpan dan dituliskan pada keterangan (template tidak punya kolom predikatnya); tidak dinaikkan menjadi 75. Nilai kosong tidak dianggap nol.
- Total kolom adalah **jumlah angka skor per kategori dalam minggu/filter tersebut**, bukan jumlah kegiatan. Pada minggu dengan lembar lanjutan, total minggu diulang dan diberi label “seluruh lembar”; jangan menjumlahkan ulang total antarlembar minggu yang sama. Total menyertakan nilai belum divalidasi, dinyatakan di catatan lembar.
- Kolom paraf memuat “QR: nama pembimbing” dan tanggal/jam scan, atau “Belum divalidasi industri”. Bukan gambar tanda tangan buatan.
- Sheet Data Laravel berisi seluruh entri hasil filter, nilai, predikat, versi, status review/validasi, dan waktu scan. Tidak ada placeholder mentah atau token kiosk.
- Teks siswa diisi sebagai string eksplisit agar tidak dieksekusi sebagai formula Excel.
- Narasi panjang otomatis mendapat sheet **Rincian ID** yang dapat dicetak beberapa halaman; tabel enam entri menampilkan cuplikan dan rujukan. Teks kegiatan/peralatan lengkap tetap tersedia di Rincian dan Data Laravel. Cetak Jurnal beserta Rincian yang dirujuk, bukan hanya Jurnal. Pemicu: kegiatan >240 karakter, keterangan >140 karakter, atau lebih dari tiga baris baru pada salah satunya. Ini batas tata letak, bukan batas penyimpanan.
- PDF rekap menolak hasil di atas 300 entri dengan petunjuk mempersempit filter; tidak menghasilkan dokumen yang diam-diam kehilangan baris. Gunakan Excel per siswa untuk pembukuan lengkap.
- File XLSX diselesaikan dahulu di file sementara sebelum header unduhan dikirim, kemudian file sementara otomatis ditutup/dihapus. Kegagalan pembuatan tidak dikirim sebagai unduhan XLSX sukses yang rusak.
- Hasil cetak fisik, keterbacaan nama panjang, dan pergantian halaman Rincian masih harus diperiksa di Excel/printer sekolah.

## Database dan pemasangan

Migrasi additive: 2026_10_23_000002_add_industry_journal_validation.php.
Menambah field jurnal, mentor+penugasan, undangan perangkat, perangkat, token scan, dan validasi bersnapshot.
Data lama tidak dihapus; review lama yang memiliki reviewer/waktu ditautkan ke revisi 1, tanpa memalsukan validasi industri.

Database lokal sudah dimigrasikan tanpa migrate:fresh. Backup sebelum migrasi:
storage/app/private/backups/before-pkl-tags-20261023-222424.sql.

Untuk instalasi lain:

    composer install
    php artisan migrate
    php artisan test

Seeder demo opsional (hanya local/testing, memerlukan demo utama sudah ada):

    php artisan db:seed --class=IndustryJournalDemoSeeder

Seeder menambahkan **Dodi (Demo)** pada PT Mitra Teknologi (Demo) dan menautkan penempatan demo perusahaan itu. Tidak membuat perangkat dipercaya, token tetap, atau scan fiktif; tidak mengubah jurnal/nilai siswa. Seeder ini belum dijalankan pada database lokal di tahap implementasi.

## Uji HP nyata sebelum penggunaan

1. Buka aplikasi melalui host yang dapat diakses kedua HP. localhost/127.0.0.1 di QR tidak menunjuk komputer server ketika dibuka pada HP lain. Atur domain/IP jaringan dan HTTPS sesuai lingkungan; pastikan URL aplikasi benar.
2. Daftarkan HP Dodi lewat QR koordinator. Pastikan muncul nama pembimbing yang benar.
3. Siswa simpan kegiatan kemarin dan nilai 88.25, lalu tampilkan ringkasan/QR.
4. Dodi scan pada browser yang sama; cek kegiatan tetap tanggal kemarin, waktu scan hari ini, nilai 88.25.
5. Edit nilai; QR lama harus ditolak dan validasi versi lama tetap terbaca. Scan QR baru.
6. Cabut perangkat melalui koordinator; scan berikutnya harus ditolak.
7. Ekspor tujuh entri seminggu, buka Excel/Print Preview, cek dua lembar cetak, nilai/predikat, catatan belum validasi, dan waktu scan.

Pengujian otomatis mencakup endpoint/form, batas hak akses, cookie enrollment, penugasan, revisi, scan terlambat/ulang/kedaluwarsa, pencabutan, skor batas/desimal, template enam entri dan overflow, placeholder, filter/draf, dan keamanan string spreadsheet. Uji kamera Google Camera/HP serta cetak fisik belum dilakukan di lingkungan ini.

Hasil verifikasi pembaruan stabilisasi: seluruh suite lulus **90 tes / 1.724 assertion**, termasuk 17 tes jurnal industri dan dua tes upgrade/seeder. Smoke test Edge headless dengan browser terpisah koordinator/siswa/Dodi juga lulus, menggunakan QR yang didekode dari gambar sebenarnya, cookie browser, CSRF aktif, dan database SQLite sementara yang baru. Belum merupakan uji Google Camera di HP, pengujian transaksi konkuren MySQL, atau cetak fisik. Composer audit tidak menemukan advisory dependency terpasang. Pemeriksaan read-only database kerja tetap menemukan 30 siswa, 30 roster, 18 penempatan, dan seluruh migrasi sudah Ran.

Prosedur pengujian ulang, kapan fresh migrate, serta gerbang STABLE: [STABILISASI_JURNAL_DAN_RESET_UJI.md](STABILISASI_JURNAL_DAN_RESET_UJI.md).
