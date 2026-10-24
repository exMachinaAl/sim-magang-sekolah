# Penilaian — perbaikan sesuai flag, 24 Oktober 2026

Tahap ini hanya menutup tiga temuan audit: identitas historis, bukti sumber industri, dan filter rekap. Tidak mengubah rumus, nilai disahkan, proses sertifikat, atau kewenangan guru.

## Uji singkat

1. Login sebagai koordinator, buka **Penilaian**.
2. Cari **DEMO-SERT-001**. Kandidat dan Rekap Penilaian sama-sama tersaring. Coba filter tahun dan jurusan **Kuliner**.
3. Klik **Bukti industri (1)** pada rekap, atau **Bukti nilai dari industri** setelah membuka penilaian. Contoh PDF berlabel **DEMO**, bukan dokumen industri asli, sudah disiapkan.
4. Unduh contoh; bila ingin menguji unggahan, lampirkan PDF/JPG/PNG maksimum 2 MB beserta catatan minimal 10 karakter. Nilai resmi tetap sama dan sertifikat lama tidak berubah.

## Perilaku yang diperbaiki

- Label kelas/jurusan pada kandidat, penilaian terpilih, rekap, dan bukti memakai roster tahun penempatan. Profil terbaru tidak digunakan sebagai pengganti sejarah. Roster kosong ditandai **Roster belum terhubung**; penilaian baru tidak boleh menebak jurusan dari profil jika relasi roster belum benar.
- Nama/NIS, tahun ajaran, serta jurusan berlaku pada kandidat dan rekap. Siswa tetap dicari, bukan dropdown seluruh siswa. Kandidat baru dimuat setelah pencarian nama/NIS; rekap bisa disaring tahun/jurusan tanpa nama.
- Scope guru/siswa dipertahankan sebelum kondisi pencarian. Siswa hanya melihat nilai miliknya yang telah disahkan; guru hanya siswa bimbingannya.
- Daftar kandidat dan rekap menggunakan paginasi SQL terpisah dengan filter tetap terbawa. Lampiran dipaginasi sepuluh per halaman tanpa memuat isi file pada daftar.

## Bukti industri

Koordinator boleh menambahkan lampiran setelah penilaian pertama kali disimpan, termasuk pada nilai yang telah disahkan. Ini tambahan bukti administratif, bukan pembukaan kunci nilai. Bukti tidak menjadi syarat baru yang memblokir pengesahan/sertifikat; angka nol tetap terlihat ketika belum ada dokumen.

Dokumen sumber dapat berisi informasi sensitif, sehingga hanya koordinator yang mengunggah, dan koordinator/admin yang dapat melihat atau mengunduhnya. Guru/siswa tidak mendapat akses file sumber, meskipun dapat membaca nilai sesuai hak aksesnya.

Setiap lampiran menyimpan:

- ID penilaian, versi/status penilaian saat unggah;
- nama asli, MIME, ukuran, hash SHA-256, dan isi berkas privat;
- ID pengunggah, waktu, dan catatan asal dokumen.

Nilai, status pengesahan, nomor revisi nilai, serta PDF/Word sertifikat tidak berubah ketika menambah lampiran. Form unggahan dari versi penilaian lama ditolak dengan 409. Versi/status saat unggah bukan klaim bahwa lampiran otomatis sesuai revisi nilai berikutnya; koordinator tetap memeriksa relevansinya.

Berkas lama tidak ditimpa/dihapus melalui aplikasi. Untuk dokumen salah, tambahkan bukti pengganti dan penjelasan; riwayat tetap tersedia. Penyimpanan file dan audit berada dalam satu transaksi. Unduhan memeriksa ukuran/hash, memakai nama aman, `attachment`, `no-store`, dan `nosniff`; tidak ada URL publik. Validasi MIME/ukuran tidak menggantikan pemeriksaan isi dokumen oleh pengguna atau antivirus.

## Migrasi dan contoh

Migrasi `2026_10_24_000006_create_assessment_evidence` diterapkan pada MySQL lokal setelah backup `storage/app/private/backups/before-pkl-tags-20261024-163957.sql` (1347021 byte). Tidak ada fresh/reset/reseed umum. Lampiran disimpan base64 di database agar ikut backup/transaksi; ukuran backup bertambah. Rollback ditolak jika sudah ada bukti untuk menghindari kehilangan dokumen.

Seeder `AssessmentEvidenceDemoSeeder` hanya menambahkan satu PDF fiktif pada siswa demo yang sudah ada. Tidak menambah siswa atau mengubah nilai. Pemanggilan ulang tidak menimpa lampiran yang sudah ada. Dokumen contoh bukan bukti asli dan tidak boleh dipakai sebagai dokumen resmi.

## Flag dan batas

Verifikasi: **136 tes / 2.392 assertion lulus**. Tes mencakup label historis, satu siswa lintas tahun, filter kandidat/rekap, scope guru/siswa, larangan akses bukti, file/ukuran/versi salah, hash rusak, rollback audit, lampiran yang tidak mengubah nilai/PDF, dan paginasi tanpa blob. Browser Edge headless lulus untuk pencarian, unggah/unduh privat, penolakan akses guru/siswa, PDF tetap identik serta tampilan 390px tanpa overflow. Artefak: `C:/Users/jorda/AppData/Local/Temp/simmag-journal-smoke-Am6bYC`. Query baca-saja MySQL memastikan demo tetap disahkan dengan nilai 85,00 dan satu lampiran. Ini bukan klaim seluruh UAT manual telah selesai.

Flag Penilaian dilepas untuk ketiga temuan audit yang telah diperbaiki dan diuji. Flag Sertifikat/Jurnal/Dashboard tidak ikut berubah. Bukti kosong pada data lama tidak diisi otomatis dengan dokumen palsu. UAT pengguna, pemulihan backup dan beban sekolah nyata tetap diperlukan; status STABLE bukan jaminan seluruh kondisi operasional sudah diuji.
