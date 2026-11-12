# Upgrade pengaduan siswa pada Monitoring — 12 November 2026

Status: **LULUS OTOMATIS**. Menunggu koreksi alur dan tampilan oleh pengguna.

## Sebelum

- Catatan Monitoring hanya dapat dibuat koordinator atau guru.
- Siswa yang mengalami kendala harus menyampaikan secara lisan agar guru membuat laporan.
- Identitas pembuat sudah tersimpan, tetapi belum ditampilkan sebagai tag sumber laporan.

## Sesudah

- Siswa dapat membuka menu **Monitoring** dan mengirim pengaduan untuk penempatannya sendiri.
- Penempatan aktif siswa dipilih otomatis berdasarkan periode yang sedang digunakan.
- Pengaduan langsung terlihat dalam ruang lingkup koordinator dan guru pendamping yang ditugaskan pada siswa tersebut.
- Koordinator/guru tetap dapat memakai alur lama dengan membuat laporan setelah menerima aduan secara lisan.
- Setiap kasus menampilkan tag **Pengaju: Siswa**, **Pengaju: Guru pendamping**, atau **Pengaju: Koordinator** berdasarkan akun pembuat.
- Koordinator dapat menetapkan penanggung jawab, memilih guru opsional, mengubah status, menulis tindakan, menyelesaikan, atau membuka kembali kasus.
- Siswa dapat membaca status dan seluruh riwayat tindak lanjut, tetapi tidak dapat mengubahnya.
- Daftar staf menyediakan filter sumber pengaju dan peringatan jumlah pengaduan siswa yang masih terbuka.

## Hak akses dan audit

- Siswa hanya dapat membuat serta membaca kasus milik penempatannya sendiri.
- Guru hanya melihat kasus siswa yang ditugaskan kepadanya.
- Koordinator melihat seluruh kasus sesuai filter periode/jurusan dan menjadi satu-satunya role yang memperbarui status.
- Admin tetap hanya memeriksa dan tidak dapat membuat atau memperbarui kasus.
- `created_by` menyimpan ID pengaju; audit pembuatan juga menyimpan role pengaju dan ID penempatan.
- Catatan awal dan riwayat tindak lanjut tidak ditimpa.

Tidak ada migrasi database. Fitur menggunakan identitas `created_by`, relasi penempatan, dan riwayat kasus yang sudah tersedia.

## Pengujian otomatis

- Pengujian terarah Monitoring, akses menu, otorisasi role, dan FAQ: **56 tes, 534 assertion, lulus**.
- Seluruh aplikasi dengan `php artisan test --compact`: **206 tes, 3.552 assertion, lulus**.

## Uji manual siswa

1. Login sebagai siswa yang sudah memiliki penempatan.
2. Buka **Monitoring** dan pastikan form **Ajukan Kendala PKL** langsung terlihat.
3. Pilih kategori, isi tanggal dan kendala, lalu tekan **Kirim Pengaduan**.
4. Pastikan kasus muncul dengan tag **Pengaju: Siswa** dan status **Terbuka**.
5. Buka detail kasus dan pastikan siswa hanya dapat membaca, bukan mengubah status.

## Uji manual koordinator dan guru

1. Login Koordinator lalu buka Monitoring.
2. Pastikan peringatan pengaduan terbuka muncul dan kasus siswa dapat difilter dengan **Pengaju laporan: Siswa**.
3. Buka kasus, tetapkan koordinator/guru, ubah menjadi **Ditindaklanjuti**, dan tulis tindakan faktual.
4. Login sebagai guru yang ditugaskan; pastikan kasus siswa terlihat tetapi tombol perubahan status tidak tersedia.
5. Login sebagai guru lain; pastikan kasus tersebut tidak terlihat.
6. Buat laporan baru sebagai guru dan pastikan tag berubah menjadi **Pengaju: Guru pendamping**.
7. Login kembali sebagai siswa dan pastikan tindakan koordinator muncul dalam riwayat.

## Format koreksi

```text
Upgrade: Pengaduan siswa Monitoring
Role: Siswa / Guru / Koordinator
Skenario: [nomor]
Status: LULUS / GAGAL
Hasil aktual: [yang terlihat]
Harapan: [yang diharapkan]
```
