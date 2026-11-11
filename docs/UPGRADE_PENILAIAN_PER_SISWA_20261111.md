# Upgrade Penilaian Per Siswa

Tanggal: 11 November 2026

## Tujuan

Menu Penilaian menjadi antrean dan rekap. Seluruh pekerjaan terhadap satu siswa dipindahkan ke halaman **Penilaian Siswa**, sehingga koordinator tidak perlu berpindah antara daftar nilai, foto setoran, bukti, pengesahan, dan sertifikat.

## Struktur baru

### Menu Penilaian

- Menampilkan siswa berdasarkan tahun ajaran dan jurusan.
- Menampilkan tanggal selesai PKL, status nilai, setoran yang menunggu, serta status sertifikat.
- Siswa yang tanggal PKL-nya sudah selesai tetapi nilainya belum disahkan diberi alert **Segera dinilai**.
- Antrean setoran siswa hanya berisi ringkasan dan tombol menuju halaman siswa.

### Penilaian Siswa

1. Informasi masa mulai/selesai PKL dan alert prioritas.
2. Setoran nilai serta foto dari siswa.
3. Form nilai resmi koordinator yang dapat diprefill dari setoran atau diisi manual dari jurnal fisik.
4. Pengesahan nilai pada halaman yang sama.
5. Upload dan riwayat bukti tambahan koordinator.
6. Status kesiapan serta tombol menuju sertifikat.

Alert selesai PKL tidak mengesahkan nilai secara otomatis. Nilai tetap harus diperiksa dan disahkan koordinator.

## Uji manual

1. Login koordinator dan buka **Penilaian**.
2. Pastikan siswa yang telah melewati tanggal selesai tetapi belum disahkan memiliki alert **Segera dinilai**.
3. Buka **Penilaian Siswa** dari alert atau antrean setoran.
4. Pastikan foto, kriteria, jadwal PKL, nilai resmi, bukti tambahan, dan status sertifikat berada dalam satu halaman.
5. Pilih setoran siswa dan pastikan form resmi terisi.
6. Simpan draf, unggah bukti tambahan, lalu ajukan dan sahkan tanpa kembali ke menu daftar.
7. Setelah disahkan, pastikan tombol **Siapkan Sertifikat** muncul.
8. Uji cara manual pada siswa tanpa setoran: form harus tetap dapat diisi dari jurnal fisik.

Tidak ada migrasi tambahan pada tahap pemisahan halaman ini. Data setoran, nilai, bukti, dan sertifikat lama tetap digunakan.
