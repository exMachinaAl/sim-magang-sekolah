# Upgrade stabilitas layout Sertifikat — 5 November 2026

## Hasil

Layout Sertifikat berstatus **STABLE untuk render digital lokal**. Pratinjau dan dokumen terbit yang dibuat ulang melalui browser telah lulus pemeriksaan struktur, geometri, integritas alur, dan inspeksi visual. Tahap ini tidak mengubah nilai, aturan penerbitan, arsip lama, migrasi, atau database kerja.

Status tersebut tidak menggantikan penerimaan cetak fisik dari sekolah. Perbedaan margin printer, pilihan balik sisi, dan opsi `fit/shrink` tetap hanya dapat dipastikan pada perangkat cetak yang akan dipakai.

## Sebelum dan sesudah

| Sebelum | Sesudah |
| --- | --- |
| Blok identitas sudah dipusatkan, tetapi bukti presisi bergantung pada inspeksi contoh lama. | Pemeriksa PDF mengukur pusat nama, lima separator identitas, dan lima baris identitas belakang pada setiap dokumen. |
| Pemeriksa lama hanya memastikan dua halaman, artwork dan tabel nilai. Status pratinjau disimpulkan dari nama file. | Mode `--preview`/`--issued` wajib dan eksplisit; watermark, artwork, ukuran A4, batas halaman, pusat konten, tabel nilai, render PNG, serta laporan JSON diperiksa. |
| Nama dan blok identitas belum diberi perlindungan eksplisit terhadap pemisahan halaman. | Nama, tabel identitas, dan ringkasan identitas memakai `page-break-inside: avoid`; render yang melampaui halaman tetap ditolak oleh penerbit. |
| Smoke browser memiliki satu ekspektasi teks demo lama sehingga berhenti setelah alur Sertifikat utama sebenarnya berhasil. | Ekspektasi diselaraskan dengan teks UI saat ini dan seluruh alur browser selesai tanpa error JavaScript. |

## Bukti otomatis

- Tes Sertifikat terarah: **14 tes lulus / 238 assertion**.
- Regresi aplikasi lengkap: **168 tes lulus / 2.962 assertion**, 64,03 detik.
- Smoke browser terisolasi: lulus dari fresh migration SQLite sementara, login role, kiosk, Jurnal, Timeline, Monitoring, Penilaian, pembuatan template Sertifikat, pratinjau, pengajuan, persetujuan, unduh PDF/DOCX, dan revisi demo.
- Probe kapasitas MySQL lokal setelah perubahan: **lulus**; database sementara dihapus dan database kerja tidak berubah.
- Pemeriksa fail-closed: PDF pratinjau yang sengaja dinyatakan sebagai dokumen terbit berhasil ditolak.
- `node --check` untuk kedua script pemeriksaan browser/PDF: lulus.

Hasil geometri PDF baru, baik pratinjau maupun terbit:

| Pemeriksaan | Hasil |
| --- | --- |
| Halaman | Tepat 2 halaman A4, masing-masing 595,28 × 841,89 pt |
| Pusat halaman | 297,64 pt |
| Pusat nama penerima | 297,64 pt |
| Lima separator identitas depan | Seluruhnya 297,64 pt |
| Lima baris identitas belakang | Seluruhnya 297,64 pt |
| Batas teks | Tidak ada teks melewati sisi halaman |
| Pratinjau | Watermark ada pada kedua halaman |
| Dokumen terbit | Watermark tidak ada; artwork dan tabel nilai tetap ada |

Artefak sintetis terakhir berada di `C:\Users\jorda\AppData\Local\Temp\simmag-journal-smoke-4CcKTn`. Folder `layout-audit` berisi PNG dan laporan JSON per PDF. Artefak ini memakai data demo dan dapat dibuang; bukan bagian dari arsip sekolah.

## Cara uji manual opsional

Pengujian otomatis sudah lulus. Ketika Anda dapat memeriksa hasilnya:

1. Login sebagai koordinator dan buka menu **Sertifikat**.
2. Pada card contoh, buka **1. Buka contoh & unduh PDF**.
3. Periksa halaman depan pada zoom 100%: nama penerima harus tepat di tengah; label berada di kiri kolon, nilai di kanan, dan lima kolon segaris.
4. Periksa halaman belakang: lima baris identitas berada di tengah, tabel nilai utuh, dan tanda tangan tidak keluar halaman.
5. Pada pratinjau, pastikan watermark merah terlihat. Pada PDF yang sudah disahkan, watermark tidak boleh ada.
6. Bila mencetak, gunakan A4 dan skala 100% tanpa `fit`/`shrink`. Catat pilihan balik sisi dan margin printer bila hasil fisik berbeda.

Untuk mengulang pemeriksaan geometri melalui terminal, siapkan `pdfjs-dist` dan `@napi-rs/canvas` pada folder alat Node sementara, kemudian jalankan:

```powershell
node scripts/inspect-certificate-pdf.cjs <folder-alat> <file-pdf> --preview --artwork=required --output-dir <folder-hasil>
```

Gunakan `--issued` untuk dokumen terbit dan `--artwork=forbidden` untuk PDF bawaan tanpa dekorasi. Script tidak membaca database.

## Roadmap setelah tahap ini

1. UAT cetak fisik Sertifikat tetap opsional sampai pengguna kembali dapat melakukan tes; temuan printer dicatat sebagai temuan output khusus, bukan membuka kembali seluruh modul.
2. Tentukan domain dan server staging dengan HTTPS tetap.
3. Pulihkan pasangan SQL + ZIP foto pada staging terpisah dan jalankan pemeriksaan deployment.
4. Jalankan load test HTTP bersamaan pada staging, termasuk dua koordinator dan validasi kiosk.
5. Nilai kesiapan produksi, backup terjadwal, dan latihan pemulihan setelah semua pemeriksaan staging lulus.
