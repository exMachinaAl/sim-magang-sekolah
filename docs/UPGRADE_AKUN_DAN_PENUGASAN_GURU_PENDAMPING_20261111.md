# Upgrade akun dan penugasan guru pendamping — 11 November 2026

Status: **LULUS OTOMATIS**. Tetap memerlukan koreksi tampilan/alur oleh pengguna.

## Cakupan perubahan

- Koordinator memperoleh menu **Guru Pendamping** pada bagian Manajemen.
- Koordinator dapat membuat akun guru menggunakan nama, NIP opsional, email login, dan kata sandi awal.
- Koordinator hanya dapat membuat, memperbarui NIP, mengaktifkan, atau menonaktifkan akun dengan role `guru`. Percobaan mengelola Admin/Koordinator ditolak.
- Akun guru aktif langsung muncul pada pilihan guru di menu **Penempatan**.
- Guru dapat dipilih saat penempatan massal/individual atau ditugaskan belakangan kepada siswa yang sudah ditempatkan.
- Penugasan/penggantian guru tidak mengubah jadwal, perusahaan, jurnal, nilai, maupun dokumen siswa.
- Penggantian perusahaan tetap ditolak bila penempatan sudah memiliki dokumen, sehingga perlindungan data lama tidak dilemahkan.
- Pembuatan akun dan perubahan penempatan tetap masuk audit log.

Bagian yang tidak diubah: role database tetap `guru`; guru tetap hanya mengakses siswa yang ditugaskan; guru pendamping tetap opsional dan kontrol rutin tetap berada pada koordinator.

## Perubahan database

Tidak ada migrasi, reset, fresh migration, atau perubahan seeder. Data lama tidak dihapus maupun diubah massal.

## Pengujian pengembang

Pengujian otomatis mencakup:

1. koordinator membuka daftar guru tanpa melihat akun Admin/Koordinator/siswa;
2. koordinator membuat akun guru dan audit pembuatan tersimpan;
3. manipulasi role untuk membuat Koordinator ditolak;
4. koordinator tidak dapat mengubah NIP/status akun Admin;
5. guru baru tampil pada Penempatan;
6. guru dapat ditugaskan setelah siswa memiliki jurnal tanpa menghapus jurnal;
7. siswa melihat nama guru barunya;
8. perubahan perusahaan pada penempatan berdokumen tetap ditolak;
9. guru biasa tidak dapat membuka pengelolaan akun guru.

Hasil akhir: **200 tes lulus dengan 3.401 assertion**. Blade berhasil dikompilasi dan pemeriksaan diff tidak menemukan whitespace error. Saat suite penuh dijalankan, satu kelemahan lama juga ditemukan dan diperbaiki: label prioritas `Segera dinilai` kini tampil langsung pada inbox setoran siswa, tidak bergantung pada siswa muncul di halaman pertama daftar penilaian.

## Uji manual pengguna

1. Login sebagai koordinator, buka **Manajemen → Guru Pendamping**.
2. Buat akun guru contoh menggunakan email dan NIP yang belum pernah dipakai.
3. Pastikan pesan berhasil muncul dan guru masuk daftar.
4. Buka **Penempatan**; pastikan guru tersebut muncul pada selector pendamping.
5. Pilih siswa yang sudah memiliki penempatan, buka **Ubah industri / pendamping**, pilih guru baru tanpa mengganti perusahaan, lalu simpan.
6. Pastikan perusahaan, jadwal, dan jurnal siswa tidak berubah.
7. Login sebagai siswa itu dan buka Timeline; nama guru pendamping harus tampil.
8. Login sebagai guru baru; guru hanya boleh melihat siswa yang ditugaskan kepadanya.
9. Kembali sebagai koordinator, nonaktifkan guru; akun harus gagal login dan tidak lagi muncul pada selector penempatan baru.

## Format koreksi

```text
Upgrade: Akun dan penugasan guru pendamping
Skenario: [nomor]
Status: LULUS / GAGAL
Hasil aktual: [yang terlihat]
Harapan: [yang seharusnya]
```

## Roadmap

Sesudah koreksi pengguna: rapikan tampilan bila ada bagian yang membingungkan, lalu lanjutkan flag/perbaikan yang sebelumnya telah disepakati. Batch pemindahan banyak siswa ke satu guru belum ditambahkan karena belum diminta pada tahap ini.
