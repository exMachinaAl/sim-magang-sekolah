# Deployment SIMMAG melalui Nginx Gateway + AppCtl (Debian 13)

## Hasil audit arsitektur

SIMMAG adalah monolit Laravel 12.70 pada PHP 8.2+, menggunakan Blade untuk HTML server-rendered, Eloquent/MySQL, session Laravel, dan 90 route web. Repository tidak memiliki `package.json`, React, Vite, Axios, atau frontend build. CSS dan JavaScript sudah berupa berkas statis di `public/`. Tidak ada SPA, Node SSR, API frontend terpisah, WebSocket, SSE, broadcast, atau CORS lintas-origin.

QR kiosk hanya menghasilkan URL dan memakai GET/POST HTTP biasa. Foto jurnal disimpan pada disk privat dan dikirim melalui controller setelah pemeriksaan hak akses. PDF, DOCX, Excel, dan QR dibuat oleh PHP; LibreOffice hanya ekstensi opsional untuk konversi template DOCX ke PDF presisi.

Role akun aktual adalah `admin`, `koordinator`, `guru`, dan `siswa`. Pembimbing industri bukan akun Laravel: identitasnya disimpan sebagai mentor dan perangkat kiosk tepercaya. Kepala sekolah saat ini adalah identitas/penandatangan pada template sertifikat, bukan role login. Akun legacy sudah dipensiunkan. Tidak ada modul kehadiran mandiri; keadaan PKL dihitung dari jadwal dan bukti kegiatan berada pada jurnal/monitoring. Patch gateway mempertahankan model bisnis aktual ini dan tidak mengarang role atau transaksi baru.

Akibatnya, runtime produksi yang tepat adalah PHP-FPM/adaptor PHP production AppCtl di belakang Nginx. Node.js tidak perlu terus berjalan dan Nginx static-only juga tidak cukup karena halaman, autentikasi, dan otorisasi diproses Laravel.

## Perubahan sebelum dan sesudah

Sebelum patch gateway:

- Laravel tidak mempercayai `X-Forwarded-Host` dan `X-Forwarded-Prefix`, sehingga link, aset, redirect, serta QR dapat kehilangan `/simmag` atau memakai host internal.
- `SESSION_SECURE_COOKIE=true` cocok untuk HTTPS tetapi membuat login HTTP lokal gagal; nilai false sebaliknya menurunkan keamanan HTTPS.
- Belum ada allowlist host/prefix dan request langsung ke upstream dapat membawa header forwarded palsu.
- Pemeriksaan deployment belum memvalidasi mode root + `/simmag`, cookie host-only, path cookie, CIDR proxy, dan pola host gateway.

Sesudah patch:

- Symfony/Laravel menerima host, skema, port, IP, dan prefix hanya dari `TRUSTED_PROXIES` eksplisit.
- Middleware menolak host, prefix, proto, port, header `Forwarded`, serta header proxy dari alamat yang tidak dipercaya dengan status 400.
- `route()`, `asset()`, `url()`, redirect login/logout, URL kiosk, dan nested route mengikuti ingress aktif pada request yang sama.
- `SESSION_SECURE_COOKIE=auto` menghasilkan cookie `Secure` pada HTTPS tepercaya dan cookie HTTP pada akses lokal; `SESSION_DOMAIN` kosong membuat cookie host-only, sedangkan path `/` mendukung root dan `/simmag`.
- Satu codebase melayani mode subdomain pada `/` dan base-path `/simmag`; tidak ada hostname tunnel atau IP lokal yang ditanam dalam source.

Perubahan ini tidak mengubah skema database, nilai, role, akses siswa, penempatan, jurnal, sertifikat, atau data magang.

## Kontrak routing Nginx

Gunakan [template Nginx](../deploy/nginx-simmag.conf.example) sebagai referensi dan gabungkan secara manual dengan konfigurasi Master Gateway. Jangan menjalankannya otomatis dan jangan menimpa konfigurasi Laptop B.

| Ingress publik | Upstream URI | Header penting |
|---|---|---|
| `smkn1cerme.test/simmag/...` | `/...` | Host publik, prefix `/simmag` |
| host ngrok/Cloudflare `/simmag/...` | `/...` | Host tunnel saat itu, proto `https`, prefix `/simmag` |
| `simmag.smkn1cerme.test/...` | `/...` | Host subdomain, prefix kosong |
| domain publik root masa depan | `/...` | Host publik, prefix kosong |

Aturan wajib:

1. `location /simmag/` memakai `proxy_pass .../` dengan slash terakhir agar prefix dihapus sebelum request masuk Laravel.
2. Nginx menimpa `Host`, semua `X-Forwarded-*`, dan mengosongkan `Forwarded`; jangan meneruskan nilai mentah dari internet.
3. Tunnel diarahkan ke Nginx Gateway, bukan langsung ke port AppCtl.
4. Hanya IP/CIDR agent tunnel yang dikontrol boleh memengaruhi skema publik HTTPS. Template memakai `geo` + `map`; tambahkan alamat privat agent bila bukan loopback.
5. Listener AppCtl hanya bind ke loopback/jaringan privat dan dibatasi firewall.
6. Tidak dibutuhkan konfigurasi Upgrade/Connection WebSocket. Bila realtime ditambahkan nanti, konfigurasi itu harus diaudit terpisah.

## Environment runtime

Pertahankan `APP_KEY` instalasi. Jangan mengirim key, password database, private key TLS, cookie, atau QR aktif ketika meminta bantuan.

```dotenv
APP_ENV=production
APP_DEBUG=false
# Fallback untuk CLI/queue, tanpa /simmag. Browser mengikuti request gateway.
APP_URL=https://simmag.smkn1cerme.test

# IP/CIDR yang terlihat PHP sebagai Nginx Gateway.
TRUSTED_PROXIES=127.0.0.1,::1

# Exact host dan wildcard suffix yang benar-benar akan digunakan.
# Host acak tunnel tidak ditulis di source.
GATEWAY_TRUSTED_HOSTS=smkn1cerme.test,*.smkn1cerme.test,*.ngrok-free.app,*.ngrok.app,*.trycloudflare.com
GATEWAY_ALLOWED_PREFIXES=/,/simmag

SESSION_DRIVER=file
SESSION_COOKIE=simmag_session
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=auto
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
LOG_LEVEL=info
```

Wildcard hanya berlaku sebagai suffix (`*.contoh.test`), tidak menerima domain induknya kecuali domain induk juga didaftarkan. Untuk domain produksi tetap, exact host lebih ketat. Perubahan hostname/suffix dilakukan pada environment AppCtl, dilanjutkan `php artisan config:cache` dan restart process; source tidak berubah.

`APP_URL` adalah fallback untuk command CLI, bukan pemilih mode ingress. Jangan mengisinya dengan `/simmag`, karena aplikasi yang sama juga melayani root subdomain.

## Rekomendasi registrasi AppCtl

Gunakan field/konsep berikut sesuai sintaks AppCtl yang tersedia di Laptop B; repository tidak mengasumsikan CLI AppCtl tertentu:

- ID service: `simmag`.
- Working directory/release: `/srv/simmag/current` atau lokasi release aktual.
- Runtime: PHP 8.2+ production adapter/PHP-FPM.
- Document root: `public/`; front controller: `public/index.php`.
- Bind: `127.0.0.1` dan port privat yang diberikan AppCtl.
- Environment file: secret store/berkas di server, bukan repository.
- Health check upstream: `/up`; dari publik menjadi `/simmag/up` pada mode base-path dan `/up` pada subdomain.
- Writable persistent paths: `storage/` dan `bootstrap/cache/`. Jangan memakai `chmod -R 777`.
- Tidak perlu Node service, queue worker, atau scheduler saat ini (`QUEUE_CONNECTION=sync` dan tidak ada scheduled task aplikasi).

Fallback `php artisan serve --host=127.0.0.1 --port="$PORT"` hanya untuk smoke test staging ketika AppCtl belum mempunyai adapter PHP production. Jangan jadikan server development itu runtime produksi permanen.

## Build dan release

Tidak ada `npm install` atau `npm run build`.

```bash
cd /srv/simmag/current
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan optimize:clear
php artisan migrate:status
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan simmag:check-deployment --target=production
```

Patch gateway ini tidak menambah migration dan tidak membutuhkan reset database. Jika release juga membawa migration fitur lain, backup database dan `storage/app/private`, periksa `migrate:status`, lalu gunakan `php artisan migrate --force`; jangan pernah `migrate:fresh` pada data nyata.

## Pengujian otomatis

Untuk CI/developer yang masih memasang dependency dev:

```bash
php artisan test --filter=GatewayCompatibilityTest
php artisan test --filter=HttpsProxyTest
php artisan test
```

Tes gateway memeriksa root/subdomain, base-path, aset/form, nested route dan refresh, login/logout, redirect, cookie HTTP/HTTPS, QR kiosk dengan hostname dinamis, upload jurnal/foto, otorisasi role, kontrak CSRF, serta penolakan host/prefix/proxy palsu. Tes memakai SQLite in-memory dan storage palsu; database kerja tidak dihapus atau dimigrasikan.

Hasil lokal 9 November 2026:

- regresi PHPUnit lengkap: **187 tes / 3.172 assertion, lulus**;
- tes gateway/proxy/readiness terarah: **13 tes / 73 assertion, lulus**;
- smoke browser Edge pada SQLite dan storage sementara: **lulus**, termasuk login/logout, CSRF nyata, jurnal/foto, kiosk Dodi, QR, timeline, monitoring, role, penilaian, sertifikat dua keluaran, DOCX, dan FAQ;
- `config:cache`, `route:cache`, `view:cache`, `composer validate`, kebutuhan platform Composer, serta `git diff --check`: **lulus**;
- pemeriksaan `.env` lokal dengan target staging bekerja dan aman, tetapi sengaja belum siap: `APP_ENV=local` dan `LOG_LEVEL=debug` adalah dua kegagalan wajib; hostname IP dan akun demo adalah dua saran;
- binary Nginx tidak tersedia pada komputer Windows ini. Template belum diterapkan atau diuji dengan `nginx -t` pada Laptop B karena server tersebut berada di luar scope perubahan. Jalankan `sudo nginx -t` sebelum reload di Debian.

Tes framework membuktikan kontrak header dan perilaku dua mode aplikasi. Acceptance infrastruktur baru final setelah template digabung ke Nginx/AppCtl Debian dan checklist manual di bawah lulus pada alamat nyata.

## Uji manual setelah gateway dipasang

1. Buka empat target: domain utama `/simmag/`, subdomain root, ngrok `/simmag/`, dan Cloudflare `/simmag/`.
2. Pada masing-masing target buka login, lalu periksa CSS termuat dan tidak ada request 404 ke `/css` di luar prefix.
3. Login sebagai koordinator, refresh langsung pada `/simmag/sertifikat` atau `/sertifikat`, lalu logout. Semua `Location` harus mempertahankan host, skema, dan prefix ingress saat itu.
4. Login sebagai siswa, buat jurnal dengan foto, buka kembali foto, buat QR validasi, dan pastikan QR memakai URL publik yang sedang dibuka.
5. Daftarkan perangkat kiosk lewat HTTPS, pindai QR jurnal, dan pastikan cookie kiosk serta waktu scan tercatat.
6. Uji role lain dan pastikan URL koordinator tetap 403/404 bagi siswa atau guru yang tidak berhak.
7. Periksa cookie: HTTPS memiliki `Secure; HttpOnly; SameSite=Lax`; HTTP lokal tidak memiliki `Secure`; keduanya path `/` dan tanpa `Domain` eksplisit.
8. Uji `/simmag/up` dan subdomain `/up`.
9. Kirim Host yang tidak terdaftar, prefix selain `/simmag`, atau forwarded header langsung ke port upstream dari mesin uji yang diizinkan: aplikasi harus memberi 400. Port upstream tetap tidak boleh dibuka publik.
10. Jalankan `php artisan simmag:check-deployment --target=production`; hasil wajib nol kegagalan sebelum menerima data nyata.

Karena tidak ada API terpisah, semua form tetap same-origin dan dilindungi CSRF Laravel; CORS tidak perlu dibuka. HTTPS pada tunnel hanya valid bila Nginx menerima proto publik dari agent tepercaya seperti pada template.

## Rollback

1. Hentikan trafik atau aktifkan maintenance pada release bermasalah.
2. Arahkan AppCtl kembali ke release/commit sebelumnya atau revert commit deployment.
3. Kembalikan environment gateway sebelumnya bila ikut diubah.
4. Jalankan `php artisan optimize:clear`, lalu buat ulang cache config/route/view untuk release lama dan restart service.
5. Verifikasi `/up`, login, foto privat, dan logout dari ingress lama.

Rollback patch gateway tidak memerlukan rollback database karena patch ini tidak mempunyai migration/data write. Jika release gabungan membawa migration fitur lain, ikuti backup dan rencana rollback migration fitur tersebut secara terpisah.
