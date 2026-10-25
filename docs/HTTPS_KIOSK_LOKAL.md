# HTTPS kiosk pada Wi-Fi yang sama

Disiapkan 25 Oktober 2026 untuk komputer ini. Ini lingkungan uji lokal, bukan deployment publik.

**Alamat komputer dan HP: https://192.168.0.114:8443**

HTTPS memakai sertifikat server dan private key. Tidak membutuhkan API key. Sertifikat CA publik yang perlu dipasang pada perangkat pengujian tersedia di:

```text
C:\Users\jorda\Downloads\SIMMAG-Kiosk-CA.crt
```

Namanya `SIMMAG Local Kiosk Test CA`. Bagikan hanya file `.crt` tersebut kepada perangkat uji sendiri. File `ca-private.key` dan `server-private.key` tetap di direktori privat komputer, tidak dipasang di HP. CA lokal memang perlu dipercaya pada tiap perangkat pengujian; CA pengembangan tidak ditujukan bagi perangkat pengguna produksi. [Penjelasan CA pengembangan dan perangkat mobile](https://github.com/FiloSottile/mkcert#mobile-devices).

## Yang sudah disiapkan

- Apache HTTPS terpisah pada IP Wi-Fi port 8443, meneruskan ke PHP 8.2 di `127.0.0.1:18080`. Apache/PHP Laragon yang lama tidak diubah; modul PHP Laragon masih versi 8.1 sehingga tidak dipakai untuk aplikasi ini.
- Sertifikat server berlaku sampai **24 November 2026, 14:39 WIB**. CA berlaku satu tahun; setup dapat menerbitkan ulang sertifikat server menggunakan CA yang sama.
- Laravel mempercayai header proxy hanya dari IP yang dikonfigurasi: `127.0.0.1,::1`. Header forwarded host tidak dipercaya. Pengenalan HTTPS tetap benar melalui proxy. [Dokumentasi Laravel](https://laravel.com/docs/12.x/requests#configuring-trusted-proxies).
- `.env`: `APP_URL=https://192.168.0.114:8443`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, cookie sesi baru `simmag_https_session`. `APP_ENV` tetap `local` untuk demo; ini bukan konfigurasi produksi lengkap.
- Private key, konfigurasi server, log dan salinan `.env` sebelum pengaturan berada di `%LOCALAPPDATA%\SIMMAG\https`, dengan akses folder dibatasi ke pengguna ini dan SYSTEM. Jangan bagikan seluruh folder.
- Log akses HTTPS tidak memuat path/query QR. Router PHP khusus lokal juga tidak menuliskan URL QR lengkap.

## Yang perlu Anda lakukan

### 1. Percayai CA pada komputer

Klik dua kali `SIMMAG-Kiosk-CA.crt` di Downloads → **Install Certificate** → **Current User** → **Place all certificates in the following store** → **Trusted Root Certification Authorities** → selesai. Windows dapat meminta konfirmasi keamanan; cocokkan nama CA di atas. Proses otomatis tidak menyelesaikan konfirmasi ini.

Fingerprint SHA-256 CA:

```text
54:3C:04:47:FD:84:09:7E:D8:A6:32:C9:52:6E:8C:BA:06:0F:8D:33:13:19:CE:03:6A:14:4A:C0:3A:8D:39:45
```

Tutup/buka browser jika perlu. Buka alamat HTTPS di atas; jangan melanjutkan pengujian dengan mengabaikan peringatan sertifikat.

### 2. Izinkan jaringan lokal di Windows

Wi-Fi komputer saat diperiksa masih berprofil **Public**, dan sesi terminal ini tidak memiliki hak administrator. Aturan firewall belum dibuat otomatis.

Jika ini Wi-Fi rumah/sekolah yang Anda percaya, ubah **Settings → Network & Internet → Wi-Fi → properti jaringan → Network profile → Private**. Jangan lakukan pada hotspot publik yang tidak dipercaya.

Buka **PowerShell sebagai Administrator**, lalu jalankan sekali:

```powershell
New-NetFirewallRule -Name 'SIMMAG-Local-HTTPS-8443' -DisplayName 'SIMMAG HTTPS lokal 8443' -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8443 -LocalAddress 192.168.0.114 -RemoteAddress LocalSubnet -Profile Private -Program 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
```

Ini hanya menambah izin HTTPS lokal pada port tersebut; tidak mematikan firewall dan tidak membuka port MySQL/PHP. Bila nama aturan sudah ada, jangan membuat berulang; periksa aturannya. Jika IP berubah, perbarui LocalAddress aturan sesuai IP baru.

### 3. Pasang CA pada HP pengujian

Sambungkan HP ke Wi-Fi yang sama. Salin **hanya** `SIMMAG-Kiosk-CA.crt` ke HP, misalnya melalui kabel USB.

- **Android:** cari `Instal sertifikat` pada Pengaturan → pilih **Sertifikat CA**, bukan sertifikat klien/VPN → pilih file tersebut. Nama menu berbeda menurut merek/versi Android. PIN HP biasanya diminta. Setelah itu buka alamat HTTPS menggunakan Chrome. Jika kebijakan perangkat melarang pemasangan CA, laporkan; jangan menonaktifkan verifikasi HTTPS.
- **iPhone:** buka file sertifikat, instal profil lewat Settings → General → VPN & Device Management, lalu aktifkan kepercayaan CA pada General → About → Certificate Trust Settings. [Petunjuk Apple](https://support.apple.com/en-us/102390).

Setiap HP siswa/pembimbing yang digunakan untuk uji HTTPS lokal perlu mempercayai CA. Gunakan alamat IP komputer, **bukan `localhost` di HP**. Pastikan tidak muncul peringatan sertifikat.

### 4. Uji kiosk setelah fresh

1. Komputer/HP siswa membuka `https://192.168.0.114:8443`. Login ulang; sesi lama tidak digunakan.
2. Koordinator: `koordinator@simmag.test` / `password123` (akun demo).
3. Menu Jurnal → Pembimbing industri → **Dodi (Demo)** → buat QR pendaftaran perangkat.
4. HP Dodi memindai QR melalui Google Camera/Lens; pastikan tautan dibuka dalam browser yang sama dan CA sudah dipercaya. QR lama sebelum fresh tidak berlaku.
5. Contoh siswa aktif: NIS `2026007`, password awal `07112008`. Isi jurnal/nilai dan tampilkan QR jurnal.
6. HP Dodi memindai QR jurnal. Periksa nama, versi, nilai dan waktu validasi; uji juga perubahan jurnal serta pencabutan perangkat.

Jika pendaftaran dibuka di Chrome tetapi scan jurnal masuk ke browser lain, cookie perangkat tidak ikut. Gunakan browser yang didaftarkan; ini berbeda dari masalah sertifikat HTTPS.

## Menyalakan lagi setelah restart komputer

Dari folder proyek:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-local-https.ps1
```

Untuk menghentikan hanya server HTTPS/PHP uji ini:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/stop-local-https.ps1
```

Jika IP Wi-Fi berubah atau sertifikat server hampir habis, jalankan setup dengan IP baru:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/setup-local-https.ps1 -Address 192.168.0.114
```

Ganti IP pada contoh sesuai alamat komputer saat itu. Setup menghentikan proses uji miliknya, memperbarui sertifikat server/config/APP_URL, kemudian menyalakan kembali. CA tetap sama sehingga tidak perlu dipasang ulang selama CA belum diganti/kedaluwarsa. Konfigurasi ini hanya untuk pengujian lokal dan menggunakan binary yang sudah terpasang pada komputer ini.

## Pemeriksaan yang sudah lulus dan yang masih perlu Anda laporkan

Pemeriksaan dari komputer: TLS dengan CA yang ditentukan, login **200**, form action HTTPS dengan port 8443, cookie sesi `Secure`, kiosk tanpa pendaftaran **403**, dan akses `/.env` **404**. Curl Windows memakai `--ssl-revoke-best-effort` karena CA lokal tidak menyediakan layanan revokasi; verifikasi rantai sertifikat dan alamat tetap aktif, tanpa `--insecure`.

Stop/start server uji juga diverifikasi kembali: login HTTPS tetap 200. Suite aplikasi lulus **159 tes / 2.914 assertion**, termasuk proxy HTTPS tepercaya, penolakan header palsu dari klien lain, dan kewajiban HTTPS kiosk di luar local/testing.

**Belum diuji dari HP:** jangkauan Wi-Fi/firewall, kepercayaan CA pada HP, browser yang dibuka Google Camera/Lens dan alur scan nyata. Server ini belum otomatis menyala saat Windows boot.

Jika gagal, laporkan alamat yang dibuka dan jenis pesan: timeout/tidak terhubung, sertifikat tidak dipercaya, 403, 419, atau 500. Jangan kirim QR aktif, cookie, password asli atau private key.
