# Upgrade validasi checkbox penilaian — 12 November 2026

Status: **LULUS OTOMATIS**. Menunggu koreksi alur dan tampilan oleh pengguna.

## Keputusan alur

Form penilaian koordinator memakai satu tombol **Simpan Penilaian** dan satu checkbox **Nilai sudah diperiksa dan dinyatakan valid**.

- Checkbox kosong: nilai disimpan sebagai draf dan masih dapat diperbaiki.
- Checkbox dicentang: nilai disimpan sekaligus disahkan, lalu koordinator diarahkan ke halaman persiapan sertifikat.
- Sistem tidak langsung membuat atau mencetak sertifikat. Koordinator masih harus memilih desain, memeriksa identitas cetak, serta mengajukan sertifikat secara eksplisit.

Pemisahan tersebut mencegah salah centang langsung menerbitkan dokumen resmi, tetapi menghilangkan pengesahan nilai berulang yang sebelumnya membutuhkan tombol terpisah.

## Kompatibilitas

- Penilaian lama berstatus **Menunggu pengesahan** tetap dapat diproses melalui tombol pengesahan lama.
- Endpoint lama `intent=submitted` dipertahankan untuk kompatibilitas dan pengujian alur historis.
- Nilai yang sudah disahkan tetap terkunci.
- Setoran siswa yang dipakai tetap ditandai sebagai diterapkan dalam transaksi yang sama.
- Audit menyimpan aksi penyimpanan, pengesahan, ID koordinator, dan penanda bahwa validasi berasal dari checkbox.

Tidak ada migrasi database dan tidak ada perubahan perhitungan nilai maupun kriteria sertifikat.

## Pengujian otomatis

- Pengujian terarah penilaian, audit, sertifikat, kompatibilitas alur lama, dan FAQ: **42 tes, 676 assertion, lulus**.
- Seluruh aplikasi dengan `php artisan test --compact`: **207 tes, 3.575 assertion, lulus**.

## Uji manual

### Simpan draf

1. Login Koordinator dan buka Penilaian salah satu siswa.
2. Isi aspek nilai tanpa mencentang checkbox validasi.
3. Tekan **Simpan Penilaian**.
4. Pastikan status menjadi Draf, form masih dapat dibuka, dan sertifikat belum siap.

### Sahkan dan lanjutkan

1. Periksa foto/dokumen industri dan seluruh aspek nilai.
2. Centang **Nilai sudah diperiksa dan dinyatakan valid**.
3. Pastikan tombol berubah menjadi **Sahkan & Lanjut ke Sertifikat**.
4. Tekan tombol dan pastikan halaman berpindah ke **Siapkan Sertifikat**.
5. Pastikan nilai berstatus Disahkan dan tidak dapat diedit lagi.
6. Periksa desain serta identitas cetak sebelum membuat sertifikat.

## Format koreksi

```text
Upgrade: Validasi checkbox penilaian
Skenario: Draf / Sahkan
Status: LULUS / GAGAL
Hasil aktual: [yang terlihat]
Harapan: [yang diharapkan]
```
