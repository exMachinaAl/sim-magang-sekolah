# Dashboard: rekap tahun dan status PKL

Pembaruan 24 Oktober 2026 menutup tiga temuan flag Dashboard: ringkasan tercampur lintas tahun, siswa dihitung sebagai penempatan, dan belum ada rekap fase sekolah/jurusan.

## Cara mencoba

1. Login koordinator, buka **Dashboard**. Pilih **Tag tahun ajaran**, lalu **Tampilkan**.
2. Periksa **Siswa Tahun Terpilih** dan **Status PKL Sekolah**. Siswa tanpa penempatan tetap masuk roster dan rekap fase.
3. Pada baris jurusan, klik **Lihat siswa** untuk membuka Timeline pada tahun/jurusan yang sama. Pada HP, geser tabel secara horizontal untuk melihat kolom lainnya.
4. Buka tahun berbeda jika datanya tersedia. Ringkasan jurnal, sertifikat, perusahaan yang digunakan, penempatan, dan status siswa mengikuti pilihan tersebut.
5. Login guru untuk melihat siswa dampingan saja; siswa hanya melihat data dirinya pada tahun terpilih.

Data MySQL lokal pada pemeriksaan 24 Oktober: 31 siswa/roster (termasuk satu demo sertifikat), 19 penempatan; 6 tidak magang, 6 belum dijadwalkan, 6 terjadwal, 6 sedang PKL, dan 7 masuk sekolah. Angka fase akan berubah otomatis mengikuti tanggal dan perubahan Kontrol PKL. Tidak menambahkan data demo baru untuk tahap ini.

## Sumber dan batas hitungan

- Pilihan tag dibatasi tahun yang memiliki roster atau penempatan sesuai hak akses. Default tahun berjalan WIB bila tersedia; jika tidak, tahun terbaru yang dapat diakses. Tidak ada pilihan gabungan semua tahun.
- Siswa dihitung sebagai identitas unik dari roster atau penempatan pada tahun terpilih. Akun siswa tanpa roster/penempatan tidak dihitung sebagai peserta tahun tersebut. Roster lama yang belum terhubung akun masih masuk rekap roster, sehingga tidak selalu sama dengan jumlah identitas siswa yang sudah terhubung.
- Rekap fase dan jurusan berasal dari roster historis, termasuk siswa yang belum mempunyai industri. Guru hanya melihat roster yang terhubung ke penempatan dampingannya dengan siswa/tag yang cocok. Data siswa lain tidak ditampilkan.
- Tanggal awal dan akhir termasuk hari PKL. Hari berikutnya menjadi **Masuk sekolah**. Status tidak magang, belum dijadwalkan (tanggal kosong/tidak valid), dan terjadwal tetap terpisah. Perhitungan memakai tanggal WIB dan agregasi SQL, tidak memuat seluruh siswa ke halaman.
- Masuk sekolah adalah fase jadwal, bukan bukti kehadiran. Tag periode tidak menentukan jadwal serentak. Jadwal individual/batch tetap dikelola di Kontrol PKL.
- Jumlah mitra menghitung perusahaan yang dipakai penempatan tahun terpilih. Progres jurnal dibatasi lima penempatan dengan pembaruan terakhir; progres sertifikat dibatasi lima dokumen terbaru pada tahun itu.
- Data Diri Saya tetap profil terkini; Status PKL Saya menampilkan kelas/jurusan historis dari roster tahun pilihan.

## Pengujian dan flag

140 tes / 2.459 assertion lulus. Pengujian baru mencakup siswa lintas tahun, seluruh counter/list mengikuti tag, siswa tanpa penempatan, jurusan historis, guru/siswa terbatas, akun tanpa data, parameter tidak valid, dan pergantian hari WIB serta tanggal kosong/terbalik.

Browser Edge headless memeriksa filter tahun, delapan jurusan, tautan Timeline, dan tampilan HP 390px. Pemeriksaan baca-saja pada MySQL lokal mengonfirmasi rekap 31 roster/19 penempatan. Tes otomatis memakai SQLite terpisah; database kerja tidak direset.

Artefak browser terakhir (data fiktif): `C:/Users/jorda/AppData/Local/Temp/simmag-journal-smoke-dM685Z`, termasuk `dashboard-school-desktop.png` dan `dashboard-school-mobile.png`. Regresi alur QR, monitoring, bukti nilai dan arsip sertifikat dalam skrip yang sama juga lulus.

Flag Dashboard dilepas untuk cakupan audit ini. Tidak membutuhkan migrasi atau `migrate:fresh`. Flag Jurnal dan Sertifikat tetap karena pemeriksaan perangkat/dokumen/cetak lapangan belum selesai. Tidak mengklaim UAT pengguna atau uji beban sekolah nyata selesai.
