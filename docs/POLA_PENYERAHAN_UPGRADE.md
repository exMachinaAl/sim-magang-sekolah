# Pola penyerahan setiap upgrade SIMMAG

Dokumen ini menjadi aturan kerja untuk setiap perbaikan atau pembaruan berikutnya. Setiap tahap harus menghasilkan satu catatan pengujian yang bisa dibaca pengguna tanpa perlu memahami kode.

## Isi wajib catatan upgrade

1. **Cakupan perubahan**: menu, masalah yang diperbaiki, perilaku baru, dan bagian yang sengaja tidak diubah.
2. **Perubahan database**: sebutkan migrasi, seeder, backup, fresh/reset, atau tulis jelas bila tidak ada.
3. **Pengujian pengembang**: perintah/skenario, jumlah tes dan assertion, hasil, serta batas yang belum dibuktikan.
4. **Yang harus diuji pengguna**: akun yang dipakai, menu/tombol, data contoh, langkah singkat, dan hasil yang seharusnya terlihat.
5. **Format koreksi**: tempat pengguna menyebut langkah yang gagal, hasil aktual, hasil yang diharapkan, dan bila relevan dokumen/perangkat yang digunakan.
6. **Status flag**: flag yang dilepas atau tetap dipasang beserta alasan yang masih nyata.
7. **Roadmap lanjutan**: urutan pekerjaan sesudah koreksi, dibatasi pada flag/rencana yang sudah disepakati. Usulan fitur baru dibicarakan dahulu.

## Aturan status pengujian

- `LULUS OTOMATIS`: tes kode selesai; belum berarti pengguna menerima tampilan atau alur bisnis.
- `LULUS UJI PENGGUNA`: pengguna sudah mencoba skenario yang disebutkan dan menyatakan hasilnya benar.
- `TERTUNDA`: belum diuji karena perangkat, template, server, data, atau keputusan bisnis belum tersedia.
- `GAGAL`: hasil aktual berbeda dari hasil yang diharapkan dan harus masuk tahap perbaikan berikutnya.

Flag hanya dicopot ketika masalah yang menjadi alasan flag sudah diperbaiki dan skenario penerimaannya selesai. Kesiapan lokal, kesiapan UAT, dan kesiapan produksi dicatat terpisah agar kelulusan tes otomatis tidak disalahartikan sebagai siap produksi.

## Format jawaban koreksi pengguna

Pengguna cukup mengirim jawaban seperti berikut:

```text
Upgrade: [nama/tanggal]
Skenario: [nomor]
Status: LULUS / GAGAL
Hasil aktual: [yang terlihat]
Harapan: [yang seharusnya]
Perangkat/dokumen: [opsional]
```

Jika semua skenario lulus, cukup tulis `Semua skenario lulus`. Hasil tersebut akan dicatat pada dokumentasi upgrade berikutnya dan menjadi dasar untuk memperbarui flag serta roadmap.

