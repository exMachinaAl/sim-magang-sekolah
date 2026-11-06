# Upgrade sertifikat tahap 1 — default penilaian per jurusan

Tanggal pengujian: 6 November 2026.

Status: **LULUS OTOMATIS**. Penerimaan tampilan/alur oleh pengguna masih menunggu koreksi manual.

## Cakupan perubahan

Tahap ini menstabilkan sumber isi nilai yang akan dicetak pada sertifikat. Sebelum upgrade, template penilaian aktif hanya disusun berdasarkan urutan terbaru dan tidak ada penanda default yang eksplisit. Data demo juga memakai pembagian aspek yang berbeda dari rancangan layar.

Sesudah upgrade:

- setiap jurusan mempunyai tepat satu template penilaian default;
- penilaian baru otomatis membuka default milik jurusan roster siswa;
- koordinator tetap dapat memilih template aktif lain dari jurusan yang sama sebelum penilaian pertama disimpan;
- koordinator dapat menekan **Jadikan default** pada template aktif yang sudah ada;
- mengarsipkan default akan memindahkan default ke template aktif terbaru pada jurusan itu, atau mengosongkannya bila tidak ada pengganti;
- template standar berisi lima aspek angka: Soft skills, Hard skills, Pengembangan hard skills, Kewirausahaan, dan Laporan PKL; masing-masing berbobot 20%;
- penilaian lama tetap memakai snapshot ketentuan semula dan tidak ditulis ulang;
- perubahan default menyimpan ID editor pada relasi default dan audit log.

Tahap ini sengaja **belum** mengubah template desain cetak, identitas penandatangan, atau menjadikan nilai jurnal sebagai nilai sertifikat. Nilai resmi masih berasal dari industri dan dicatat koordinator.

## Perubahan database

- Migrasi additive: `2026_11_06_000001_create_assessment_template_defaults.php`.
- Tabel baru menyimpan satu jurusan → satu template aktif pilihan serta ID koordinator terakhir.
- Installer aman dan dapat diulang: `AssessmentDefaultTemplateSeeder` hanya memasang delapan template standar/default; tidak membuat siswa, jurnal, penempatan, atau akun demo. Jika koordinator sudah mengganti default secara manual, eksekusi ulang installer tidak menimpanya.
- `migrate:fresh` **tidak dijalankan**.
- Sebelum migrasi dibuat snapshot `before-pkl-tags-20261106-054354.sql` (3.721.187 byte).
- Backup arsip juga diuji pulih ke database MySQL sementara. Skema dan seluruh isi tabel identik, 12 referensi lampiran/1 berkas foto unik pulih dengan hash cocok, lalu database sementara dihapus.
- Migrasi dan installer sudah diterapkan ke database lokal aktif. Hasil inspeksi: 8 jurusan, 8 relasi default, dan 8 template standar.

## Pengujian pengembang

- Sintaks PHP seluruh berkas baru/berubah: lulus.
- Tes khusus template/default: 7 tes, 82 assertion, lulus.
- Regresi final sesudah seluruh perubahan dan penguatan installer: 175 tes, 3.042 assertion, lulus dalam 87,75 detik.
- Smoke browser terisolasi: migrasi fresh, 30 siswa, FAQ, dashboard, kiosk QR, jurnal, timeline, monitoring, penilaian, template cetak, persetujuan, dan arsip PDF lulus tanpa error JavaScript.
- Uji kapasitas MySQL terisolasi: lulus.

Pengujian otomatis membuktikan aturan data, otorisasi, pemilihan default, audit, kompatibilitas migrasi, dan tidak adanya regresi yang terdeteksi. Pengujian ini belum membuktikan bahwa istilah/tata letak sudah paling nyaman bagi pengguna sekolah.

## Yang perlu diuji pengguna

Gunakan `koordinator@simmag.test` pada data lokal demo.

1. Buka **Penilaian → Kelola Template Ketentuan**. Pastikan setiap jurusan memiliki baris `Default 80/20 — ...`, lima aspek berbobot 20%, dan badge **Default** hanya pada satu template per jurusan.
2. Cari NIS `2026003`, lalu buka penilaiannya. Pastikan template bertanda **Default jurusan** langsung terpilih tanpa pilihan manual.
3. Kembali ke Kelola Template Ketentuan. Pada template aktif lain, tekan **Jadikan default**. Pastikan badge berpindah dan penilaian baru jurusan tersebut mengikuti pilihan baru.
4. Buka penilaian yang sudah pernah disimpan/disahkan. Pastikan template, aspek, dan nilainya tidak berubah ketika default jurusan dipindahkan.
5. Periksa tampilan desktop dan HP: tombol, tabel, serta keterangan default tidak boleh tumpang tindih.

Jangan mengesahkan nilai UAT bila hanya ingin mencoba tampilan. Gunakan Salin/revisi bila ingin membuat variasi template, supaya versi yang lama tetap dapat dipakai sebagai pembanding.

## Status flag

Menu Sertifikat sudah berstatus `stable` untuk temuan audit sebelumnya. Upgrade ini tidak menambah atau melepas flag karena merupakan peningkatan fleksibilitas bertahap, bukan penutupan seluruh roadmap sertifikat. Status produksi tetap mengikuti checklist deployment dan UAT sekolah.

## Roadmap yang sudah disepakati

1. **Tahap 2 — desain cetak fleksibel:** pisahkan desain visual dari template aspek nilai, sediakan default desain per jurusan, dan izinkan override desain untuk satu siswa tanpa mengubah jurusan maupun sumber nilainya.
2. **Tahap 3 — identitas dan pola tanda tangan:** pusatkan default kepala sekolah/pihak industri/pembimbing serta pilihan pola tanda tangan depan-belakang. Arsip versi lama tidak boleh berubah ketika pejabat berganti.
3. **Tahap 4 — referensi perhitungan:** nilai angka industri tetap sumber resmi; ringkasan jurnal dan angka flat menjadi referensi terukur bagi koordinator, dengan sumber dan override diaudit. Jurnal tidak otomatis mengesahkan nilai sertifikat.

Tahap berikutnya dimulai setelah hasil koreksi lima skenario di atas. Tidak ada fitur di luar roadmap ini yang ditambahkan tanpa pembahasan.

## Format koreksi

```text
Upgrade: Sertifikat template tahap 1
Skenario: 1/2/3/4/5
Status: LULUS / GAGAL
Hasil aktual: ...
Harapan: ...
Perangkat: desktop / HP
```

Jika seluruhnya sesuai, cukup kirim: `Semua skenario tahap 1 lulus`.
