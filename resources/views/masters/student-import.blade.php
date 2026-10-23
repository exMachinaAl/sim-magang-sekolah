<section class="card roster-import" id="impor-siswa" aria-labelledby="import-title">
    <div class="card-head"><div><h2 id="import-title">Impor Data Siswa Massal</h2><p>Daftarkan seluruh angkatan dan buat akun siswa melalui satu file CSV dari Excel.</p></div></div>
    <div class="header-actions">
        <a class="btn btn-secondary btn-sm" href="{{ route('students.template',['academic_year'=>$academicYear,'example'=>1]) }}">Template + Contoh 8 Siswa</a>
        <a class="btn btn-ghost btn-sm" href="{{ route('students.template',['academic_year'=>$academicYear,'example'=>0]) }}">Template Kosong (opsional)</a>
        <a class="btn btn-ghost btn-sm" href="{{ route('students.template',['academic_year'=>$academicYear,'type'=>'schedule','example'=>1]) }}">Template Jadwal + Contoh</a>
    </div>
    <p class="small">1. Unduh template. 2. Isi data asli; hapus/ganti baris contoh. 3. Simpan sebagai CSV UTF-8, lalu unggah. File .xlsx belum didukung.</p>
    <div class="table-wrap"><table><caption>Contoh dua baris dari template (data fiktif, ganti sebelum impor)</caption><thead><tr><th>NIS</th><th>Nama</th><th>Tanggal lahir</th><th>Kelas</th><th>Jurusan</th></tr></thead><tbody>
        <tr><td>CONTOH001</td><td>Contoh Siswa 1</td><td>2008-11-07</td><td>XII 1</td><td>Teknik Instalasi Tenaga Listrik</td></tr>
        <tr><td>CONTOH002</td><td>Contoh Siswa 2</td><td>2008-11-07</td><td>XII 1</td><td>Teknik Kimia Industri</td></tr>
    </tbody></table></div>
    <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data" class="stack">
        @csrf
        <div class="form-grid">
            <div class="form-field"><label for="import-year">Tahun Ajaran Default</label><input id="import-year" name="academic_year" value="{{ old('academic_year',$academicYear) }}" placeholder="2026/2027" pattern="[0-9]{4}/[0-9]{4}" required><small>Dipakai bila kolom tahun_ajaran pada baris kosong.</small></div>
            <div class="form-field"><label for="roster-file">File Data Siswa (CSV)</label><input id="roster-file" type="file" name="roster_file" accept=".csv,text/csv" aria-describedby="import-rules" required><small>Maksimal 5 MB dan 5.000 baris siswa. Pemisah koma atau titik koma.</small></div>
        </div>
        <div id="import-rules" class="note-box">Kolom wajib: <strong>nis, nama, kelas, jurusan</strong>. <strong>tanggal_lahir</strong> wajib untuk akun baru, format <strong>YYYY-MM-DD</strong>. Login siswa memakai NIS; password awal DDMMYYYY. NIS yang sudah terdaftar memakai akun lama tanpa mereset password. Koreksi nama/email akun melalui Data Siswa.</div>
        <p class="small">NIS/NISN harus disimpan sebagai <strong>teks</strong> agar nol awal tidak hilang. Impor siswa saja tidak mengubah jadwal yang sudah ada. Profil kelas/jurusan mengikuti pendaftaran tahun ajaran terbaru; tahun sebelumnya tetap menjadi riwayat. Untuk jadwal serentak, buka <a href="{{ route('internships.index',['academic_year'=>$academicYear]) }}">Kontrol PKL</a> setelah impor selesai.</p>
        <details><summary class="link">Panduan kolom dan jurusan yang diterima</summary>
            <div class="table-wrap"><table><thead><tr><th>Kolom</th><th>Aturan / contoh</th></tr></thead><tbody>
                <tr><td>nis, nama, kelas</td><td>Wajib. Contoh: 001234, Nama Siswa, XII TKJ 1. Kombinasi NIS + tahun ajaran tidak boleh berulang dalam file.</td></tr>
                <tr><td>jurusan</td><td>Wajib, gunakan salah satu jurusan di bawah. Singkatan TITL, TKI, DKV, APL, TKJ, TPTUP, TOI dan Kuliner juga diterima.</td></tr>
                <tr><td>tanggal_lahir</td><td>Wajib untuk akun baru. Contoh 2008-11-07 menghasilkan password awal 07112008. Tanggal lahir akun lama yang diisi harus cocok dengan profil.</td></tr>
                <tr><td>tahun_ajaran</td><td>Opsional per baris; kosong mengikuti pilihan di atas. Contoh {{ $academicYear }}.</td></tr>
                <tr><td>nisn, email, catatan</td><td>Opsional. Email akun baru tidak boleh sudah dipakai akun lain. Catatan kosong pada impor ulang mengosongkan catatan roster.</td></tr>
                <tr><td>status_magang</td><td>Hanya template jadwal: magang / tidak_magang / belum_ditentukan. Kosong mempertahankan status lama; siswa baru menjadi belum_ditentukan.</td></tr>
                <tr><td>mulai_pkl, selesai_pkl</td><td>Hanya template jadwal: YYYY-MM-DD, wajib berpasangan. Jadwal terisi menandai siswa magang dan menjadi pengecualian individual, bukan batch jurusan. Status tidak_magang atau belum_ditentukan tanpa tanggal menghapus jadwal lama.</td></tr>
            </tbody></table></div>
            <ul>@foreach($majors as $major)<li>{{ $major }}</li>@endforeach</ul>
        </details>
        <div class="form-footer"><span class="small">Jika satu baris gagal, seluruh impor dibatalkan. Periksa pesan kesalahan lalu unggah ulang.</span><button class="btn btn-primary" type="submit">Impor Siswa &amp; Buat Akun</button></div>
    </form>
</section>
