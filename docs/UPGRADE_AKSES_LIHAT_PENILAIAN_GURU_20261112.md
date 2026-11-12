# Upgrade akses lihat penilaian guru — 12 November 2026

Status: **LULUS OTOMATIS**. Menunggu koreksi alur dan tampilan oleh pengguna.

## Sebelum

- Guru hanya melihat ringkasan penilaian yang sudah tercatat pada tabel rekap.
- Guru tidak dapat membuka halaman detail setoran siswa, foto sumber, atau bukti tambahan.
- Endpoint foto dan arsip bukti menolak role guru meskipun guru tersebut ditugaskan kepada siswa.

## Sesudah

- Guru pendamping melihat daftar setoran nilai akhir dari siswa yang ditugaskan kepadanya.
- Guru dapat membuka halaman detail dalam **Mode lihat saja**.
- Informasi yang dapat dilihat meliputi kriteria, nilai yang disetor siswa, foto sumber, nilai resmi koordinator, status, dan bukti tambahan JPG/PNG/PDF.
- Thumbnail dan overlay bukti tetap tersedia bagi guru.
- Guru tidak dapat menerapkan setoran, mengubah nilai, mengunggah bukti, mengesahkan nilai, atau menyiapkan sertifikat.
- Guru yang tidak ditugaskan tetap menerima respons tidak ditemukan dan tidak dapat mengambil bytes bukti.

## Batas keamanan

- Seluruh akses guru dibatasi oleh `teacher_id` pada penempatan melalui `Access::placements()`.
- Route tulis tetap khusus koordinator; hanya route baca yang diperluas.
- Pemeriksaan ukuran dan SHA-256 file tetap dijalankan sebelum foto/bukti dikirim.
- Siswa lain dan guru lain tidak memperoleh akses silang.

Tidak ada migrasi database dan tidak ada perubahan nilai, status, atau perhitungan sertifikat.

## Pengujian otomatis

- Pengujian terarah setoran siswa, bukti privat, scope guru, penilaian, FAQ, dan role: **55 tes, 555 assertion, lulus**.
- Seluruh aplikasi dengan `php artisan test --compact`: **207 tes, 3.607 assertion, lulus**.

## Uji manual

1. Gunakan siswa yang sudah mempunyai guru pendamping dan pernah mengirim setoran nilai beserta foto.
2. Login sebagai guru pendamping tersebut lalu buka **Penilaian**.
3. Pastikan bagian **Setoran nilai dari siswa** menampilkan siswa yang ditugaskan.
4. Tekan **Lihat Penilaian & Bukti** dan pastikan halaman bertanda **Mode lihat saja**.
5. Buka thumbnail foto/overlay, unduh bukti tambahan, dan periksa nilai resmi.
6. Pastikan tidak ada tombol menerapkan setoran, menyimpan nilai, mengunggah bukti, atau mengesahkan.
7. Login sebagai guru lain dan pastikan siswa/bukti tersebut tidak dapat dibuka.

## Format koreksi

```text
Upgrade: Akses lihat penilaian guru
Skenario: [nomor]
Status: LULUS / GAGAL
Hasil aktual: [yang terlihat]
Harapan: [yang diharapkan]
```
