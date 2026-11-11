# Upgrade arsip akun yang dapat dipulihkan — 11 November 2026

Status: **LULUS OTOMATIS**. Menunggu koreksi alur/tampilan oleh pengguna.

## Tujuan

Mengurangi gangguan pada daftar akun tanpa menghapus identitas atau riwayat. Istilah sistem adalah **Arsipkan / Sembunyikan**, bukan delete.

## Perilaku

- Hanya Admin yang dapat mengarsipkan dan memulihkan akun.
- Arsip otomatis menonaktifkan akun dan menyembunyikannya dari daftar utama.
- Record `users`, NIP, email, profil siswa, penempatan guru, jurnal, nilai, sertifikat, serta audit tetap tersimpan.
- Email/NIP akun arsip tetap dianggap digunakan agar identitas historis tidak diduplikasi.
- Akun arsip tidak dapat login, diaktifkan, atau diedit sebelum dipulihkan.
- Admin membuka tab **Arsip Akun** untuk melihat siapa dan kapan akun diarsipkan.
- Pemulihan mengembalikan akun ke daftar utama dalam status nonaktif. Admin harus memeriksa lalu menekan Aktifkan secara terpisah.
- Admin tidak dapat mengarsipkan akun sendiri.
- Koordinator tetap dapat mengelola akun guru biasa, tetapi tidak dapat mengarsipkan atau memulihkan akun.
- Setiap arsip dan pemulihan masuk audit log.

## Database

Migrasi aditif: `2026_11_11_000002_add_user_archive_state.php`.

Kolom baru:

- `users.archived_at`
- `users.archived_by`

Rollback ditolak bila masih terdapat akun arsip karena penghapusan status tersebut dapat membuat akun tersembunyi kembali terlihat tanpa informasi pemulihan. Tidak ada data yang dihapus dan tidak memerlukan `migrate:fresh`.

Pengujian akhir: **203 tes lulus dengan 3.456 assertion**. Pengujian khusus memastikan record pengguna dan relasi penempatan tidak terhapus, akun arsip tidak dapat login, email tetap unik, akses Koordinator ditolak, pemulihan tetap nonaktif, aktivasi terpisah berhasil, serta arsip/pemulihan tercatat pada audit.

## Uji manual

1. Login sebagai Admin dan buka **Pengguna & Role**.
2. Pilih akun uji selain akun sendiri, lalu klik **Arsipkan / Sembunyikan**.
3. Pastikan akun hilang dari **Akun Tersedia** dan muncul di **Arsip Akun**.
4. Pastikan akun tersebut tidak dapat login.
5. Pastikan data lama yang menyebut siswa/guru tersebut tetap tampil pada menu terkait.
6. Klik **Pulihkan**; pastikan akun kembali ke daftar utama dengan status Nonaktif.
7. Pastikan akun masih belum dapat login.
8. Klik **Aktifkan**, lalu uji login akun tersebut.
9. Login sebagai Koordinator dan pastikan tidak tersedia tombol arsip/pulihkan.

## Format koreksi

```text
Upgrade: Arsip akun recoverable
Skenario: [nomor]
Status: LULUS / GAGAL
Hasil aktual: [yang terlihat]
Harapan: [yang diharapkan]
```
