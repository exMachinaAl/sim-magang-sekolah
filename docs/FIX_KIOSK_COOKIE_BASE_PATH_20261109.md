# Perbaikan cookie kiosk pada Nginx base-path

Tanggal: 9 November 2026.

## Masalah

QR pendaftaran pembimbing melalui alamat publik `/simmag/industry/enroll/...` berhasil membuat record perangkat dan menandai undangan terpakai. Namun cookie `industry_device` sebelumnya selalu memakai `Path=/industry`. Browser tidak mengirim cookie tersebut ketika diarahkan ke `/simmag/industry`, sehingga perangkat langsung dianggap belum terdaftar.

Tes lama tidak menemukan masalah karena alur kiosk browser hanya dijalankan pada root `/industry`, sedangkan tes gateway berhenti setelah memeriksa URL QR. Pemakaian `withCookie()` pada tes unit juga tidak mensimulasikan pemilihan cookie berdasarkan path oleh browser.

## Perbaikan

Path cookie sekarang dibentuk dari base URL request yang sudah divalidasi oleh trusted proxy:

- subdomain/root menghasilkan `Path=/industry`;
- Nginx base-path menghasilkan `Path=/simmag/industry`.

Cookie tetap `Secure` pada HTTPS, `HttpOnly`, `SameSite=Lax`, host-only, dan hanya berlaku pada area kiosk. Logika mentor, penugasan, QR, nilai, dan jurnal tidak berubah.

## Pengujian otomatis

Tes regresi gateway baru menjalankan alur lengkap: koordinator membuat undangan, URL memakai hostname tunnel runtime, halaman enrollment dibuka, perangkat didaftarkan, redirect mempertahankan `/simmag`, cookie mempunyai path yang benar, kemudian cookie yang sama dapat membuka halaman kiosk.

Tes kiosk root tetap memeriksa `Path=/industry` agar perbaikan base-path tidak merusak mode subdomain.

Hasil regresi setelah perbaikan: **188 tes / 3.186 assertion lulus**. Tes gateway khusus: **8 tes / 61 assertion lulus**.

## Pengujian manual pengguna

1. Koordinator mencabut record perangkat dari percobaan yang gagal.
2. Buat QR pendaftaran baru; QR lama sudah terpakai dan tidak dapat digunakan ulang.
3. Pindai QR melalui alamat HTTPS ngrok dengan prefix `/simmag` menggunakan browser HP yang akan dijadikan kiosk.
4. Pastikan halaman kembali ke beranda kiosk dan menampilkan nama pembimbing, bukan pesan belum terdaftar.
5. Tutup lalu buka kembali alamat `/simmag/industry`; perangkat harus tetap dikenali.
6. Tampilkan QR jurnal siswa dan validasi dari browser HP yang sama.

Jika masih gagal, catat URL publik tanpa token QR, browser HP, status redirect, dan atribut cookie `industry_device`. Jangan mengirim nilai cookie atau token QR aktif.
