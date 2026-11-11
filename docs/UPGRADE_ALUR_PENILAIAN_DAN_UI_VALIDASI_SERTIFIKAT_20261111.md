# Upgrade Alur Penilaian dan UI Validasi Sertifikat

Tanggal: 11 November 2026

## Sebelum

- Nama/NIS terlihat sebagai pencarian utama pada Penilaian walaupun kandidat sudah dapat dimuat langsung.
- Halaman bukti hanya memperlihatkan ringkasan nilai dan daftar file. Koordinator perlu berpindah halaman untuk melihat seluruh aspek dan mengambil keputusan.
- Detail Sertifikat mencampur validasi aktif, riwayat keputusan, revisi, penandatangan desain, DOCX, serta distribusi. Satu validasi koordinator terlihat seperti banyak pengesahan.

## Sesudah

- Penilaian langsung mengikuti tahun ajaran aktif dan jurusan. Nama/NIS dipindahkan menjadi pencarian lanjutan yang opsional.
- Kandidat tetap dipaginasi 10 siswa per halaman dan tidak memakai dropdown siswa massal.
- Halaman Bukti menampilkan identitas, industri, penilai, semua aspek/ketentuan, bobot, hasil, nilai hitung, nilai resmi, dan catatan.
- Koordinator dapat kembali untuk menerapkan/koreksi nilai atau mengesahkan penilaian yang telah diajukan dari halaman Bukti.
- File bukti tidak dibaca otomatis dan tidak pernah mengubah angka tanpa tindakan koordinator.
- Detail Sertifikat menampilkan alur Proposal → Validasi koordinator → Pencetakan → Distribusi.
- Hanya satu validasi aktif ditampilkan. Riwayat keputusan dan pengajuan versi baru berada dalam bagian lipat.
- Format, penandatangan, serta keluaran PDF/DOCX dipisahkan dari validasi aplikasi.
- Daftar Sertifikat menampilkan satu status validasi koordinator, bukan mengulang seluruh relasi persetujuan.

Tidak ada migrasi, perubahan nilai, perubahan pihak pengesah, atau perubahan arsip dokumen pada upgrade ini.

## Uji manual Penilaian

1. Login sebagai koordinator dan buka **Penilaian**.
2. Tanpa mengisi NIS, pilih tahun ajaran dan jurusan. Pastikan daftar siswa langsung muncul.
3. Buka pencarian lanjutan hanya bila ingin mempersempit berdasarkan nama/NIS.
4. Pilih siswa, isi nilai dari dokumen industri, kemudian simpan draf atau ajukan validasi.
5. Buka **Lihat bukti & informasi penilaian industri**.
6. Pastikan seluruh aspek, hasil, bobot, nilai hitung, nilai resmi, identitas siswa, dan industri terlihat.
7. Unggah PDF/JPG/PNG sumber, kemudian unduh kembali untuk memastikan arsip benar.
8. Gunakan **Terapkan / koreksi** bila terdapat perbedaan, atau **Sahkan setelah bukti diperiksa** bila sudah sesuai.

## Uji manual Sertifikat

1. Buka sertifikat yang masih menunggu validasi.
2. Pastikan ringkasan empat langkah terlihat dan hanya ada satu panel **Validasi koordinator**.
3. Periksa isi nilai yang dibekukan dan bagian **Format, penandatangan, dan keluaran dokumen**.
4. Setujui atau tolak versi tersebut. Pastikan keputusan masuk ke riwayat yang terlipat.
5. Pastikan nama kepala sekolah/pembimbing hanya muncul sebagai penandatangan dokumen, bukan sebagai pengesahan aplikasi tambahan.
6. Setelah disetujui, uji unduhan PDF/DOCX dan tahapan distribusi seperti sebelumnya.

## Roadmap lanjutan

- Koreksi hasil UAT tampilan desktop dan ponsel dari pengguna.
- Bila sekolah membutuhkan penerimaan nilai langsung dari pihak industri, rancang kanal input industri terpisah; jangan menganggap OCR foto/PDF sebagai nilai resmi tanpa pemeriksaan koordinator.
