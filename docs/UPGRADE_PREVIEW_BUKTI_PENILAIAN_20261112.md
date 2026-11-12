# Upgrade preview bukti penilaian — 12 November 2026

Status: **LULUS OTOMATIS**. Menunggu pemeriksaan tampilan oleh pengguna.

## Sebelum

- Foto setoran nilai siswa hanya berupa tautan yang langsung membuka tab baru.
- Bukti tambahan JPG/PNG hanya dapat diunduh.
- Koordinator harus berpindah konteks untuk memastikan gambar sesuai dengan input nilai.

## Sesudah

- Foto setoran siswa dan bukti tambahan JPG/PNG langsung tampil sebagai thumbnail pada halaman penilaian siswa maupun riwayat bukti.
- Menekan thumbnail membuka overlay ukuran besar tanpa meninggalkan halaman.
- Overlay menyediakan tombol **Unduh gambar**, **Buka di tab lain**, dan **Tutup**.
- Overlay dapat ditutup dengan tombol, menekan area gelap, atau tombol `Esc`.
- PDF tetap ditampilkan sebagai aksi unduh; sistem tidak memaksakan PDF menjadi thumbnail gambar.
- Tampilan responsif untuk desktop dan HP.

## Keamanan dan data

- Gambar tidak dibuat publik. Thumbnail, overlay, unduhan, dan tab baru tetap memakai route privat dengan pemeriksaan akses penempatan.
- Integritas ukuran dan SHA-256 tetap diperiksa sebelum bytes dikirim.
- Respons memakai `private, no-store` dan `nosniff`.
- Tidak ada perubahan nilai, bukti, hak akses, database, maupun migrasi.

## Pengujian otomatis

- Pengujian terarah penilaian, privasi bukti, template, header inline/attachment, serta rendering Blade: **15 tes, 252 assertion, lulus**.
- JavaScript preview lolos pemeriksaan sintaks `node --check`.
- Seluruh aplikasi dengan `php artisan test --compact`: **205 tes, 3.515 assertion, lulus**.

## Uji manual

1. Login sebagai Koordinator.
2. Buka **Penilaian**, pilih siswa yang memiliki setoran nilai dengan foto.
3. Pastikan thumbnail terlihat tanpa membuka tab baru.
4. Tekan thumbnail dan pastikan overlay menampilkan gambar lebih besar.
5. Uji **Unduh gambar**, **Buka di tab lain**, tombol **Tutup**, klik area gelap, dan `Esc`.
6. Unggah bukti tambahan JPG/PNG lalu ulangi pemeriksaan pada bagian bukti tambahan dan halaman riwayat bukti.
7. Unggah PDF dan pastikan tetap tersedia sebagai unduhan, bukan thumbnail rusak.
8. Uji dari layar HP untuk memastikan overlay tidak melewati layar.

## Format koreksi

```text
Upgrade: Preview bukti penilaian
Skenario: [nomor]
Perangkat: Desktop / HP
Status: LULUS / GAGAL
Hasil aktual: [yang terlihat]
Harapan: [yang diharapkan]
```
