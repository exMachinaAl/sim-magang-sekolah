@extends('layouts.app')
@section('title','Timeline PKL')
@section('breadcrumb','Operasional / Timeline PKL')
@section('subtitle','Kontrol PKL menjadi sumber jadwal. Buka jurusan, pilih siswa, lalu lihat timeline tanggal otomatis.')
@section('content')
<div class="card timeline-navigator">
<form method="GET" class="timeline-search-form">
<div><label for="timeline-period">Tag tahun ajaran</label><select id="timeline-period" name="period_id">@forelse($periods as $period)<option value="{{ $period->id }}" @selected($chosen===$period->id)>{{ $period->name }}</option>@empty<option value="">Belum ada roster</option>@endforelse</select></div>
<div class="search-wide"><label for="timeline-search">Cari jurusan, siswa, atau pendamping</label><input id="timeline-search" name="q" value="{{ $search }}" maxlength="100" placeholder="Jurusan, nama, NIS, NISN, nama guru / NIP"></div>
<button class="btn btn-primary">Cari</button><a class="btn btn-ghost" href="{{ route('timeline.index',['period_id'=>$chosen ?: null]) }}">Reset / awal</a>
</form><p class="small muted">Pencarian mencakup tahun terpilih sesuai hak akses. Guru hanya melihat siswa penugasannya; siswa melihat roster miliknya. Tanggal acuan {{ $today->format('d/m/Y') }} (WIB); muat ulang setelah pergantian hari.</p>
</div>

@if($selectedStudentRow)
@php $roster=$selectedStudentRow['roster']; $placement=$selectedStudentRow['placement']; $schedule=$selectedStudentRow['schedule']; @endphp
<section id="student-timeline">
@unless(auth()->user()->isRole('siswa'))<p><a class="btn btn-secondary" href="{{ route('timeline.index',['period_id'=>$chosen,'major'=>$roster->major]) }}">← Daftar siswa {{ $roster->major }}</a></p>@endunless
<div class="card student-profile-bar"><div><span class="eyebrow">TIMELINE SISWA</span><h2>{{ $roster->student?->user?->name ?? $roster->name }}</h2><p>NIS {{ $roster->student?->nis ?? $roster->nis }} · {{ $roster->class_name }} · {{ $roster->major }}</p></div>
<div class="student-profile-meta"><span><small>Guru pendamping (opsional)</small><strong>{{ $placement?->teacher?->name ?? 'Tanpa guru pendamping' }}</strong></span><span><small>Perusahaan</small><strong>{{ $placement?->company?->name ?? 'Belum ditempatkan' }}</strong></span><span><small>Fase hari ini</small><strong>{{ $schedule['label'] }}</strong></span></div></div>
@if($placement)<p><a class="btn btn-secondary" href="{{ route('journals.show',$placement) }}">Buka jurnal, validasi industri & ekspor Excel</a></p>
@else<div class="note-box">Belum ditempatkan. Jadwal dan fase tetap mengikuti Kontrol PKL; jurnal tersedia setelah koordinator menetapkan industri.</div>@endif
<div class="card"><div class="card-head"><div><h2>Rincian Timeline Berbasis Tanggal</h2><p>Tidak memerlukan konfirmasi selesai dari siswa atau guru.</p></div><span class="pill">{{ $schedule['source'] }}</span></div>
@if($schedule['state']==='not_internship')<div class="empty">Siswa dinyatakan tidak magang di Kontrol PKL. Tidak ada tahapan PKL aktif.</div>
@elseif($schedule['state']==='unscheduled')<div class="empty">Jadwal belum lengkap atau urutan tanggal tidak valid. Koordinator perlu memeriksa Kontrol PKL.</div>
@else
<div class="student-timeline-list date-lifecycle-list">
<div class="student-timeline-row"><div class="timeline-marker {{ $schedule['state']==='scheduled'?'in-progress':'approved' }}"></div><div class="timeline-row-date"><strong>{{ $schedule['starts_at']->format('d M') }}</strong><span>{{ $schedule['starts_at']->format('Y') }}</span></div><div class="timeline-row-content"><h3>Mulai PKL</h3><p>Tanggal awal sesuai jadwal individual atau batch.</p></div><div class="timeline-row-status"><span class="pill">{{ $schedule['state']==='scheduled'?'Menunggu':'Sudah dimulai' }}</span></div></div>
<div class="student-timeline-row"><div class="timeline-marker {{ $schedule['state']==='pkl'?'in-progress':($schedule['state']==='school'?'approved':'') }}"></div><div class="timeline-row-date"><strong>{{ $schedule['ends_at']->format('d M') }}</strong><span>{{ $schedule['ends_at']->format('Y') }}</span></div><div class="timeline-row-content"><h3>Pelaksanaan PKL</h3><p>Tanggal selesai tetap dihitung sebagai hari PKL.</p></div><div class="timeline-row-status"><span class="pill">{{ $schedule['state']==='pkl'?'Sedang berjalan':($schedule['state']==='school'?'Selesai':'Belum dimulai') }}</span></div></div>
<div class="student-timeline-row"><div class="timeline-marker {{ $schedule['state']==='school'?'approved':'' }}"></div><div class="timeline-row-date"><strong>{{ $schedule['return_at']->format('d M') }}</strong><span>{{ $schedule['return_at']->format('Y') }}</span></div><div class="timeline-row-content"><h3>Masuk Sekolah</h3><p>Terhitung otomatis sehari setelah selesai PKL; bukan catatan absensi.</p></div><div class="timeline-row-status"><span class="pill">{{ $schedule['state']==='school'?'Sudah masuk':'Akan datang' }}</span></div></div>
</div>
@endif
<p class="date-audit-note">Sumber: {{ $schedule['source'] }}@if($schedule['editor_id']) · editor #{{ $schedule['editor_id'] }} {{ $schedule['editor'] }}@endif</p>
@if(auth()->user()->isRole('koordinator'))<a href="{{ route('internships.index',['academic_year'=>$roster->academic_year,'roster_id'=>$roster->id]) }}">Periksa jadwal di Kontrol PKL</a>@endif
</div></section>
@else
@if(!$selectedMajor && !$selectedTeacher && !auth()->user()->isRole('guru','siswa'))
<section id="major-directory"><div class="card"><h2>Timeline per Jurusan</h2><p>Pilih jurusan untuk membuka tabel siswa. Termasuk roster tanpa penempatan atau tanpa guru pendamping.</p></div>
<div class="major-directory">
@forelse($majorDirectory as $group)
<a class="card major-directory-card" data-timeline-major="{{ $group->major }}" href="{{ route('timeline.index',['period_id'=>$chosen,'major'=>$group->major]) }}">
<h3>{{ $group->major }}</h3><p>{{ $group->total }} siswa</p><div class="detail-grid"><div><small>Sedang PKL</small><strong>{{ $group->pkl_count }}</strong></div><div><small>Masuk sekolah</small><strong>{{ $group->school_count }}</strong></div><div><small>Terjadwal</small><strong>{{ $group->scheduled_count }}</strong></div><div><small>Belum dijadwalkan</small><strong>{{ $group->unscheduled_count }}</strong></div><div><small>Tidak magang</small><strong>{{ $group->not_internship_count }}</strong></div></div><p>Lihat siswa →</p></a>
@empty<div class="card empty">{{ $search!==''?'Tidak ada jurusan yang cocok; periksa hasil siswa di bawah.':'Belum ada roster pada tahun ini.' }}</div>@endforelse
</div>@include('partials.pager',['items'=>$majorDirectory])</section>
@endif
@if($showStudents)
<section class="card" id="student-directory"><div class="card-head"><div><h2>{{ $selectedTeacher || auth()->user()->isRole('guru') ? 'Daftar Siswa Bimbingan' : ($selectedMajor ?: 'Hasil Pencarian Siswa') }}</h2><p>@if($search!=='')Hasil untuk “{{ $search }}”. @endif Pilih satu siswa untuk melihat timeline; bukan seluruh timeline sekaligus.</p>@if($selectedTeacher)<p>{{ $selectedTeacher->name }} · NIP {{ $selectedTeacher->nip ?: 'belum diisi' }}</p>@endif</div><span class="pill">{{ $studentRows->total() }} siswa</span></div>
<div class="table-wrap"><table class="student-directory"><thead><tr><th>Siswa / jurusan roster</th><th>Penempatan / pendamping</th><th>Mulai PKL</th><th>Selesai PKL</th><th>Fase Hari Ini</th><th></th></tr></thead><tbody>
@forelse($studentRows as $roster)@php $p=$roster->placement; @endphp
<tr data-timeline-roster="{{ $roster->id }}"><td><strong>{{ $roster->student?->user?->name ?? $roster->name }}</strong><div class="small muted">NIS {{ $roster->student?->nis ?? $roster->nis }} · {{ $roster->class_name }}<br>{{ $roster->major }}</div></td><td>{{ $p?->company?->name ?? 'Belum ditempatkan' }}<div class="small muted">{{ $p?->teacher?->name ?? 'Tanpa guru pendamping' }}@if($p?->teacher?->nip)<br>NIP {{ $p->teacher->nip }}@endif</div></td><td>{{ $roster->internship_starts_at?->format('d/m/Y') ?? '—' }}</td><td>{{ $roster->internship_ends_at?->format('d/m/Y') ?? '—' }}</td><td><span class="pill">{{ $roster->lifecycleLabel($today) }}</span></td><td><a class="btn btn-secondary btn-sm" href="{{ route('timeline.index',['period_id'=>$chosen,'roster_id'=>$roster->id]) }}#student-timeline">Lihat timeline</a></td></tr>
@empty<tr><td colspan="6"><div class="empty">Tidak ada siswa yang sesuai filter dan hak akses Anda.</div></td></tr>@endforelse
</tbody></table></div>@include('partials.pager',['items'=>$studentRows])</section>
@elseif(auth()->user()->isRole('siswa'))<div class="card empty">Belum ada roster PKL yang terhubung dengan akun Anda. Hubungi koordinator.</div>@endif
@endif
@endsection
