@extends('layouts.app')
@section('title','Timeline PKL')
@section('breadcrumb','Operasional / Timeline PKL')
@section('subtitle','Fase PKL dihitung otomatis dari tanggal mulai dan selesai; tanpa konfirmasi manual.')
@section('content')

<div class="card timeline-navigator">
    <form method="GET" class="timeline-search-form">
        <div><label>Tag Tahun Ajaran</label><select name="period_id">@foreach($periods as $period)<option value="{{ $period->id }}" @selected($chosen===$period->id)>{{ $period->name }}</option>@endforeach</select></div>
        <div class="search-wide"><label>Cari pembimbing atau siswa</label><input name="q" value="{{ $search }}" placeholder="Nama, NIP, NIS, atau NISN"></div>
        <button class="btn btn-primary">Cari</button>
        @if($search!=='')<a class="btn btn-ghost" href="{{ route('timeline.index',['period_id'=>$chosen]) }}">Reset</a>@endif
    </form>
</div>

@if($search!=='')
<section class="card search-results">
    <div class="card-head"><div><h2>Hasil Pencarian “{{ $search }}”</h2><p>Pilih hasil untuk langsung menuju pembimbing atau siswa.</p></div><span class="pill">{{ $searchTeachers->count()+$searchStudents->count() }} hasil</span></div>
    @if($searchTeachers->isEmpty() && $searchStudents->isEmpty())<div class="empty">Data tidak ditemukan.</div>@endif
    @if($searchTeachers->isNotEmpty())<h3 class="result-heading">Guru Pembimbing</h3><div class="result-links">@foreach($searchTeachers as $group)<a href="{{ route('timeline.index',['period_id'=>$chosen,'teacher_id'=>$group['teacher']->id]).'#teacher-section' }}"><span class="result-avatar">{{ strtoupper(mb_substr($group['teacher']->name,0,1)) }}</span><span><strong>{{ $group['teacher']->name }}</strong><small>NIP {{ $group['teacher']->nip ?: 'belum diisi' }} · {{ $group['students_count'] }} siswa</small></span><b>→</b></a>@endforeach</div>@endif
    @if($searchStudents->isNotEmpty())<h3 class="result-heading">Siswa</h3><div class="result-links">@foreach($searchStudents as $row)@php $placement=$row['placement']; @endphp<a href="{{ route('timeline.index',['period_id'=>$chosen,'teacher_id'=>$placement->teacher_id ?? 0,'placement_id'=>$placement->id]).'#student-timeline' }}"><span class="result-avatar student">{{ strtoupper(mb_substr($placement->student->user->name,0,1)) }}</span><span><strong>{{ $placement->student->user->name }}</strong><small>NIS {{ $placement->student->nis }} · {{ $placement->teacher?->name ?? 'Tanpa guru pendamping' }}</small></span><b>→</b></a>@endforeach</div>@endif
</section>
@endif

@if($selectedStudentRow)
@php
    $placement=$selectedStudentRow['placement']; $schedule=$selectedStudentRow['schedule'];
    $stateClass=match($schedule['state']){'pkl','school'=>'approved','scheduled','unscheduled'=>'submitted','not_internship'=>'rejected',default=>''};
@endphp
<section id="student-timeline">
    <p><a class="btn btn-secondary" href="{{ route('journals.show',$placement) }}">Buka jurnal, validasi industri & ekspor Excel</a></p>
    @unless(auth()->user()->isRole('siswa'))<div class="section-back"><a href="{{ route('timeline.index',['period_id'=>$chosen,'teacher_id'=>$placement->teacher_id ?? 0]).'#teacher-section' }}">← Kembali ke daftar siswa {{ $placement->teacher?->name ?? 'Tanpa guru pendamping' }}</a></div>@endunless
    <div class="card student-profile-bar"><div class="student-profile-main"><span class="profile-initial">{{ strtoupper(mb_substr($placement->student->user->name,0,1)) }}</span><div><span class="eyebrow">TIMELINE SISWA</span><h2>{{ $placement->student->user->name }}</h2><p>NIS {{ $placement->student->nis }} · {{ $placement->student->class_name }} · {{ $placement->student->major }}</p></div></div><div class="student-profile-meta"><span><small>Pembimbing</small><strong>{{ $placement->teacher?->name ?? 'Tanpa guru pendamping' }}</strong></span><span><small>Perusahaan</small><strong>{{ $placement->company->name }}</strong></span><span><small>Fase hari ini</small><strong><span class="pill {{ $stateClass }}">{{ $schedule['label'] }}</span></strong></span></div></div>

    <div class="card">
        <div class="card-head"><div><h2>Rincian Timeline Berbasis Tanggal</h2><p>Status berubah sendiri saat tanggal berganti. Guru dan siswa tidak perlu menekan tombol penyelesaian.</p></div><span class="pill">{{ $schedule['source'] }}</span></div>
        @if(!$schedule['starts_at'] || !$schedule['ends_at'])
            <div class="empty"><span class="empty-mark">○</span>Koordinator belum menetapkan jadwal PKL untuk siswa ini.</div>
        @else
        <div class="student-timeline-list date-lifecycle-list">
            <div class="student-timeline-row"><div class="timeline-marker {{ $schedule['state']==='scheduled'?'in-progress':'approved' }}"></div><div class="timeline-row-date"><strong>{{ $schedule['starts_at']->format('d M') }}</strong><span>{{ $schedule['starts_at']->format('Y') }}</span></div><div class="timeline-row-content"><div><h3>Mulai PKL</h3><p>Siswa mulai menjalankan kegiatan PKL pada tanggal ini.</p></div></div><div class="timeline-row-status"><span class="pill {{ now()->startOfDay()->lt($schedule['starts_at'])?'submitted':'approved' }}">{{ now()->startOfDay()->lt($schedule['starts_at'])?'Menunggu':'Sudah dimulai' }}</span></div></div>
            <div class="student-timeline-row"><div class="timeline-marker {{ $schedule['state']==='pkl'?'in-progress':($schedule['state']==='school'?'approved':'') }}"></div><div class="timeline-row-date"><strong>{{ $schedule['starts_at']->format('d M') }}</strong><span>s.d. {{ $schedule['ends_at']->format('d M Y') }}</span></div><div class="timeline-row-content"><div><h3>Pelaksanaan PKL</h3><p>Tanggal selesai tetap dihitung sebagai hari PKL.</p></div></div><div class="timeline-row-status"><span class="pill {{ $schedule['state']==='pkl'?'active':($schedule['state']==='school'?'approved':'submitted') }}">{{ $schedule['state']==='pkl'?'Sedang berjalan':($schedule['state']==='school'?'Selesai':'Belum dimulai') }}</span></div></div>
            <div class="student-timeline-row"><div class="timeline-marker {{ $schedule['state']==='school'?'approved':'' }}"></div><div class="timeline-row-date"><strong>{{ $schedule['return_at']->format('d M') }}</strong><span>{{ $schedule['return_at']->format('Y') }}</span></div><div class="timeline-row-content"><div><h3>Masuk Sekolah</h3><p>Terhitung otomatis sehari setelah tanggal selesai PKL.</p></div></div><div class="timeline-row-status"><span class="pill {{ $schedule['state']==='school'?'approved':'submitted' }}">{{ $schedule['state']==='school'?'Sudah masuk':'Akan datang' }}</span></div></div>
        </div>
        <div class="date-audit-note">Jadwal: {{ $schedule['source'] }}@if($schedule['editor']) · terakhir diubah oleh {{ $schedule['editor'] }}@endif</div>
        @endif
    </div>
</section>

@elseif($selectedTeacher)
<section id="teacher-section">
    @if(auth()->user()->isRole('admin','koordinator'))<div class="section-back"><a href="{{ route('timeline.index',['period_id'=>$chosen]) }}">← Kembali ke daftar pembimbing</a></div>@endif
    <div class="card teacher-heading-bar"><div><span class="eyebrow">GURU PEMBIMBING</span><h2>{{ $selectedTeacher->name }}</h2><p>NIP {{ $selectedTeacher->nip ?: 'belum diisi' }} · Informasi ini hanya untuk pemantauan dan penanganan kasus khusus.</p></div><span class="pill">{{ $studentRows->total() }} siswa</span></div>
    <div class="card"><div class="card-head"><div><h2>Daftar Siswa Bimbingan</h2><p>Pilih satu siswa untuk melihat garis tanggal PKL. Tidak ada kontrol penyelesaian manual.</p></div></div><div class="table-wrap"><table class="student-directory"><thead><tr><th>Siswa</th><th>Penempatan</th><th>Mulai PKL</th><th>Selesai PKL</th><th>Fase Hari Ini</th><th></th></tr></thead><tbody>
    @forelse($studentRows as $row)@php $p=$row['placement']; $s=$row['schedule']; $class=match($s['state']){'pkl','school'=>'approved','scheduled','unscheduled'=>'submitted','not_internship'=>'rejected',default=>''}; @endphp<tr><td><strong>{{ $p->student->user->name }}</strong><div class="small muted">NIS {{ $p->student->nis }} · {{ $p->student->class_name }}</div></td><td>{{ $p->company->name }}</td><td>{{ $s['starts_at']?->format('d/m/Y') ?: '—' }}</td><td>{{ $s['ends_at']?->format('d/m/Y') ?: '—' }}</td><td><span class="pill {{ $class }}">{{ $s['label'] }}</span></td><td><a class="btn btn-secondary btn-sm" href="{{ route('timeline.index',['period_id'=>$chosen,'teacher_id'=>$selectedTeacher->id,'placement_id'=>$p->id]).'#student-timeline' }}">Lihat timeline</a></td></tr>@empty<tr><td colspan="6"><div class="empty">Belum ada siswa pada pembimbing dan periode ini.</div></td></tr>@endforelse
    </tbody></table></div>@include('partials.pager',['items'=>$studentRows])</div>
</section>

@else
<section class="card" id="teacher-directory"><div class="card-head"><div><h2>Daftar Guru Pembimbing</h2><p>Pilih guru untuk membuka daftar siswa. Data siswa tidak ditampilkan sekaligus.</p></div><span class="pill">{{ $teacherDirectory->total() }} pembimbing</span></div><div class="table-wrap"><table class="teacher-directory"><thead><tr><th>Guru Pembimbing</th><th>Jumlah Siswa</th><th>Sedang PKL</th><th>Masuk Sekolah</th><th>Belum Terjadwal</th><th></th></tr></thead><tbody>
@forelse($teacherDirectory as $group)<tr><td><strong>{{ $group['teacher']->name }}</strong><div class="small muted">NIP {{ $group['teacher']->nip ?: 'belum diisi' }}</div></td><td>{{ $group['students_count'] }}</td><td><span class="pill approved">{{ $group['pkl_count'] }}</span></td><td>{{ $group['school_count'] }}</td><td><span class="pill {{ $group['unscheduled_count']?'submitted':'' }}">{{ $group['unscheduled_count'] }}</span></td><td><a class="btn btn-secondary btn-sm" href="{{ route('timeline.index',['period_id'=>$chosen,'teacher_id'=>$group['teacher']->id]).'#teacher-section' }}">Lihat siswa</a></td></tr>@empty<tr><td colspan="6"><div class="empty">Belum ada guru pembimbing pada periode ini.</div></td></tr>@endforelse
</tbody></table></div>@include('partials.pager',['items'=>$teacherDirectory])</section>
@endif
@endsection
