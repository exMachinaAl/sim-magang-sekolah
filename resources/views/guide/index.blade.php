@extends('layouts.app')
@section('title','Panduan Penggunaan')
@section('breadcrumb','Bantuan / Panduan')
@section('subtitle','Panduan singkat pengoperasian SIMMAG sesuai tahap pelaksanaan magang.')
@section('content')
<div class="two-cols"><div class="card"><div class="card-head"><h2>Cara Mengisi Jurnal & Foto</h2></div><div class="steps-guide">
<div class="guide-item"><span class="badge-step">1</span><div><h3>Masuk dan buka menu Jurnal</h3><p>Gunakan akun siswa, lalu pilih menu Jurnal & Foto pada sidebar.</p></div></div>
<div class="guide-item"><span class="badge-step">2</span><div><h3>Pilih periode dan tanggal</h3><p>Masukkan tanggal kegiatan sesuai dengan periode penempatan PKL.</p></div></div>
<div class="guide-item"><span class="badge-step">3</span><div><h3>Catat aktivitas dan nilai industri</h3><p>Isi kegiatan, ruang kerja, jam, dan peralatan/bahan. Hasil dan kendala opsional. Jika pembimbing memberi nilai, catat angka persisnya; jika belum, biarkan kosong.</p></div></div>
<div class="guide-item"><span class="badge-step">4</span><div><h3>Foto opsional</h3><p>Boleh menyimpan tanpa foto. Maksimal 5 foto JPG/PNG, masing-masing 4 MB. Jangan unggah materi rahasia perusahaan.</p></div></div>
<div class="guide-item"><span class="badge-step">5</span><div><h3>Tunjukkan ringkasan dan QR</h3><p>Simpan jurnal dan nilai, lalu tampilkan ringkasan kepada pembimbing industri. Setelah isi cocok, pembimbing membuka QR melalui kamera HP dan browser terpercaya; sistem mencatat waktu scan aktual. Scan boleh hari lain. Tanpa scan tetap tercatat sebagai belum divalidasi.</p></div></div>
<div class="guide-item"><span class="badge-step">6</span><div><h3>Ekspor untuk buku</h3><p>Unduh Excel dari detail siswa. Satu lembar memuat maksimal enam entri, sisanya dilanjutkan. Predikat otomatis menampung angka nilai persis. Review sekolah berbeda dari validasi industri.</p></div></div>
</div></div>
<div class="stack"><div class="card"><div class="card-head"><h2>Alur Sertifikat</h2></div><div class="steps-guide">
<div class="guide-item"><span class="badge-step">1</span><div><h3>Nilai akhir disahkan</h3><p>Nilai akhir industri diinput dan disahkan koordinator; tidak otomatis dihitung dari nilai jurnal. Sertifikat berbasis template tidak mensyaratkan scan semua jurnal. Skema sertifikat lama masih memiliki syarat review jurnal tersendiri.</p></div></div>
<div class="guide-item"><span class="badge-step">2</span><div><h3>Persetujuan koordinator PKL</h3><p>Koordinator memvalidasi kelengkapan dan menyetujui penerbitan sertifikat.</p></div></div>
<div class="guide-item"><span class="badge-step">3</span><div><h3>Cetak, tanda tangan, dan distribusi</h3><p>Unduh PDF dua halaman untuk dicetak bolak-balik: sertifikat di depan dan daftar nilai di belakang. Koordinator kemudian mencatat proses distribusinya.</p></div></div>
<div class="guide-item"><span class="badge-step">4</span><div><h3>Ambil sertifikat</h3><p>Periksa jadwal serta lokasi di halaman detail dan konfirmasi penerimaan kepada koordinator.</p></div></div></div></div>
<div class="card"><h2>Pertanyaan Umum</h2>
<details><summary><strong>Bagaimana jika saya lupa kata sandi?</strong></summary><p class="small muted">Hubungi admin sekolah untuk pengaturan ulang akun.</p></details><div class="divider"></div>
<details><summary><strong>Bagaimana mengubah jurnal yang sudah divalidasi?</strong></summary><p class="small muted">Klik Edit / isi nilai. Perubahan membuat versi baru yang belum divalidasi; riwayat versi lama tetap ada. Jika versi telah berubah di tab lain, muat ulang. Tanggal kegiatan tetap harus berada dalam jadwal Kontrol PKL.</p></details><div class="divider"></div>
<details><summary><strong>Bagaimana mendaftarkan HP pembimbing?</strong></summary><p class="small muted">Koordinator membuka Jurnal → Pembimbing Industri & Perangkat QR, menugaskan siswa, lalu membuat QR pendaftaran sekali pakai. Pembimbing memindai dengan kameranya dan membuka link di browser yang akan dipakai seterusnya. Tanpa PIN. Jangan membagikan QR pendaftaran kepada siswa. Koordinator dapat mencabut perangkat.</p></details><div class="divider"></div>
<details><summary><strong>Apakah harus membuat folder Google Drive sendiri?</strong></summary><p class="small muted">Tidak. Foto langsung diunggah melalui SIMMAG dan disimpan dalam penyimpanan privat server.</p></details>
</div></div></div>
@endsection
