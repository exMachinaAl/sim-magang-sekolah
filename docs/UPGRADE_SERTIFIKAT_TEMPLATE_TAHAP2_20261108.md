# Upgrade sertifikat tahap 2 — desain fleksibel per jurusan dan siswa

Tanggal pengujian: 8 November 2026.

Status: **LULUS OTOMATIS**. Penerimaan istilah, tampilan, dan alur pengguna masih menunggu koreksi manual.

## Cakupan perubahan

Sebelum upgrade, sebuah desain cetak hanya dapat dipakai bila `assessment_template_id` desain persis sama dengan ID template nilai siswa. Dua jurusan dengan struktur nilai sama tetap harus membuat desain duplikat.

Sesudah upgrade:

- desain cetak memiliki **jurusan asal desain**, terpisah dari template nilai yang dipakai siswa;
- setiap jurusan dapat memiliki satu desain default;
- desain default dipilih otomatis ketika halaman Siapkan Sertifikat dibuka;
- koordinator dapat memilih desain kompatibel lain untuk satu siswa; pilihan itu tersimpan pada versi sertifikat, bukan pada nilai siswa;
- satu desain dapat dijadikan default untuk lebih dari satu jurusan;
- desain format bebas PDF bawaan/PDF dekorasi dapat dipakai lintas jurusan;
- desain 80/20 hanya dapat dipakai jika siswa memiliki lima aspek angka kompatibel berbobot 20%;
- DOCX format bebas hanya dapat dipakai bila jumlah aspek siswa sama dengan sumber desain, supaya token tidak hilang atau tersisa;
- desain yang tidak kompatibel disembunyikan dan pilihan eksplisitnya ditolak tanpa mengubah nilai;
- audit pengajuan/revisi menyimpan ID desain, jurusan siswa, ID default saat itu, status override, dan ID koordinator;
- snapshot nilai, PDF/DOCX lama, serta versi sertifikat yang sudah diterbitkan tidak ditulis ulang.

Kolom `assessment_template_id` pada desain lama tetap dipertahankan sebagai referensi sumber pemetaan/token. Kolom itu bukan lagi pengunci pemakaian desain.

Tahap ini belum memusatkan identitas kepala sekolah/pihak industri. Identitas dan pola tanda tangan adalah Tahap 3.

## Perubahan database

- Migrasi additive: `2026_11_08_000001_decouple_certificate_print_designs.php`.
- Kolom baru `certificate_print_templates.design_major` diisi dari jurusan template nilai asal untuk seluruh desain lama.
- Tabel baru `certificate_print_template_defaults` menyimpan jurusan, desain default, dan ID editor.
- Default hasil migrasi memakai desain terbaru pada setiap jurusan yang sudah memiliki desain.
- Jurusan tanpa desain tidak diberi identitas atau desain palsu; halaman sertifikat memakai fallback kompatibel sampai koordinator menetapkan default resmi.
- `migrate:fresh` dan seeder demo tidak dijalankan pada database kerja.
- Snapshot sebelum migrasi: `before-pkl-tags-20261108-075308.sql`, 3.733.223 byte.
- Restore-probe sebelum dan sesudah migrasi lulus; database sementara kemudian dihapus.

Percobaan migrasi pertama berhenti karena nama foreign key otomatis MySQL melebihi 64 karakter. Migrasi belum tercatat dan tabel default masih kosong. Migrasi lalu diperkeras untuk menangani DDL parsial, memakai nama constraint pendek, dan berhasil dijalankan. Tidak ada sertifikat atau arsip yang hilang.

Hasil database lokal setelah migrasi:

- 3 desain lama, seluruhnya memiliki jurusan asal;
- 2 jurusan yang sudah mempunyai desain memperoleh default;
- 3 arsip dokumen sertifikat tetap tersedia;
- migrasi tercatat sukses.

## Pengujian pengembang

- Sintaks PHP seluruh model, migrasi, controller, dan layanan: lulus.
- Tes sertifikat/arsip/template terkait: 42 tes, 500 assertion, lulus.
- Regresi penuh: 177 tes, 3.073 assertion, lulus dalam 83,64 detik.
- Smoke browser dari fresh migration dan database/storage terisolasi: lulus, termasuk pembuatan desain, pemilihan default, preview PDF, pengajuan, persetujuan, unduhan arsip, mobile layout, dan tidak ada error JavaScript.
- Uji kapasitas MySQL terisolasi: lulus.
- Restore-probe pascamigrasi: skema dan isi seluruh tabel identik setelah dipulihkan; 10 berkas arsip diperiksa; 12 referensi lampiran/1 foto unik memiliki hash cocok.

## Yang perlu diuji pengguna

Gunakan `koordinator@simmag.test` pada data lokal.

1. Buka **Sertifikat → Desain Cetak & Penandatangan**. Pastikan desain lama menampilkan jurusan asal, sumber pembuatan, format, dan badge default bila tersedia.
2. Pada salah satu desain, pilih jurusan lalu tekan **Jadikan default**. Muat ulang dan pastikan badge `Default [jurusan]` berpindah tanpa mengubah sertifikat lama.
3. Cari siswa yang penilaiannya sudah disahkan, lalu buka **Siapkan Sertifikat**. Pastikan desain default jurusannya langsung terpilih.
4. Pilih desain asal jurusan lain yang kompatibel. Pastikan muncul peringatan lintas jurusan dan nama, jurusan, aspek, serta nilai tetap milik siswa tersebut.
5. Buka pratinjau PDF. Pastikan dua halaman, posisi isi, nilai, serta penandatangan benar. Pratinjau tidak boleh membuat versi sertifikat.
6. Ajukan menggunakan desain selain default. Pastikan detail sertifikat memakai desain pilihan tersebut, sedangkan default jurusan tidak berpindah.
7. Buka sertifikat lama dan unduh PDF versi lamanya. Hasil harus tetap sama dan tidak dibangun ulang dari data hidup.
8. Periksa desktop dan HP: select desain, peringatan, form identitas, serta tabel desain tidak boleh terpotong horizontal.

## Batas keamanan dan bisnis

- “Lintas jurusan” hanya mengganti desain/format/pola penandatangan. Sistem tidak mengganti roster, template nilai, aspek, atau nilai akhir siswa.
- Format 80/20 yang tidak kompatibel ditolak, bukan dikonversi atau dibulatkan otomatis.
- Default adalah pilihan awal, bukan persetujuan otomatis. Sertifikat tetap melalui preview, pengajuan, dan persetujuan koordinator.
- Desain tersimpan immutable. Perbaikan desain dibuat sebagai versi baru; arsip terbit lama tidak ditimpa.

## Status flag

Menu Sertifikat tetap `stable` untuk cakupan audit yang sudah ditutup. Tidak ada flag yang ditambah atau dilepas pada tahap ini. Kesiapan produksi tetap bergantung pada UAT printer sekolah dan checklist deployment.

## Roadmap lanjutan

**Tahap 3 — identitas dan pola tanda tangan:** pusatkan profil kepala sekolah, pihak industri, dan pembimbing; tentukan default pola depan-belakang; sediakan pilihan hanya kepala sekolah; serta simpan snapshot identitas per versi agar pergantian pejabat tidak mengubah arsip lama.

Tahap 4 tetap sesudahnya: angka industri sebagai sumber resmi, sementara ringkasan jurnal/angka flat hanya menjadi referensi terukur dan diaudit.

## Format koreksi

```text
Upgrade: Sertifikat template tahap 2
Skenario: 1-8
Status: LULUS / GAGAL
Hasil aktual: ...
Harapan: ...
Perangkat/dokumen: desktop / HP / PDF
```

Jika seluruh skenario sesuai, cukup kirim: `Semua skenario tahap 2 lulus`.
