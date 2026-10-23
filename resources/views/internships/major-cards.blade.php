@if(!$selectedMajor)
<section aria-label="Direktori jurusan"><div class="card-head"><div><h2>Rekap Per Jurusan</h2><p>Pilih jurusan untuk membuka daftar siswa. Cari nama/NIS untuk langsung menemukan siswa dari halaman ini.</p></div></div>
<div class="major-directory">
@forelse($visibleMajorStats as $stat)
<a class="card major-directory-card" href="{{ route('internships.index',['academic_year'=>$academicYear,'major'=>$stat->major,'lifecycle'=>request('lifecycle')]) }}">
    <h3>{{ $stat->major }}</h3><p><strong>{{ $stat->total }}</strong> siswa terdaftar</p>
    <dl><div><dt>Sedang PKL</dt><dd>{{ $stat->pkl_count }}</dd></div><div><dt>Masuk sekolah</dt><dd>{{ $stat->school_count }}</dd></div><div><dt>Terjadwal</dt><dd>{{ $stat->scheduled_count }}</dd></div><div><dt>Belum dijadwalkan</dt><dd>{{ $stat->unscheduled_count }}</dd></div><div><dt>Tidak magang</dt><dd>{{ $stat->not_internship_count }}</dd></div></dl>
    <span class="link">Buka daftar siswa →</span>
</a>
@empty<p class="note-box">Tidak ada nama jurusan yang cocok. Hasil pencarian siswa ditampilkan di bawah.</p>@endforelse
</div></section>
@else
<div class="card"><a href="{{ route('internships.index',['academic_year'=>$academicYear]) }}">← Semua jurusan</a><h2>{{ $selectedMajor }}</h2><p>Daftar dan pengecualian jadwal khusus jurusan ini.</p></div>
@endif
