@extends('layouts.app')
@section('title','Kontrol PKL Siswa')
@section('breadcrumb','Manajemen / Kontrol PKL')
@section('subtitle','Jadwal PKL berbasis tanggal; status siswa berubah otomatis tanpa konfirmasi manual.')
@section('content')

<div class="internship-stat-grid lifecycle-stats">
    <div class="internship-stat"><span>Seluruh siswa</span><strong>{{ $totals['all'] }}</strong><small>Tahun ajaran {{ $academicYear }}</small></div>
    <div class="internship-stat success"><span>Sedang PKL</span><strong>{{ $totals['pkl'] }}</strong><small>Dihitung dari tanggal hari ini</small></div>
    <div class="internship-stat neutral"><span>Masuk sekolah</span><strong>{{ $totals['school'] }}</strong><small>PKL telah selesai</small></div>
    <div class="internship-stat"><span>Terjadwal</span><strong>{{ $totals['scheduled'] }}</strong><small>Belum mencapai tanggal mulai</small></div>
    <div class="internship-stat warning"><span>Belum dijadwalkan</span><strong>{{ $totals['unscheduled'] }}</strong><small>Termasuk keputusan yang masih pending</small></div>
    <div class="internship-stat neutral"><span>Tidak magang</span><strong>{{ $totals['not_internship'] }}</strong><small>Keputusan partisipasi terpisah</small></div>
</div>

<div class="card">
    <div class="card-head"><div><h2>Cari Jurusan atau Siswa</h2><p>Buka jurusan, atau cari siswa langsung berdasarkan nama, NIS, atau NISN.</p></div><div class="header-actions"><a class="btn btn-ghost btn-sm" href="{{ route('students.index',['academic_year'=>$academicYear]) }}#impor-siswa">Tambah / Impor di Data Siswa</a><a class="btn btn-secondary btn-sm" href="{{ route('internships.export',request()->query()) }}">Ekspor Hasil</a></div></div>
    <form method="GET" class="roster-filter">
        <div><label>Tahun Ajaran</label><select name="academic_year">@foreach($academicYears as $year)<option value="{{ $year }}" @selected($academicYear===$year)>{{ $year }}</option>@endforeach</select></div>
        <div><label>Jurusan</label><select name="major"><option value="">Semua jurusan</option>@foreach($majors as $major)<option value="{{ $major }}" @selected(request('major')===$major)>{{ $major }}</option>@endforeach</select></div>
        <div><label>Fase Hari Ini</label><select name="lifecycle"><option value="">Semua fase</option>@foreach(\App\Models\InternshipRoster::lifecycleLabels() as $value=>$label)<option value="{{ $value }}" @selected(request('lifecycle')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="roster-search"><label>Cari Jurusan / Siswa</label><input name="q" value="{{ request('q') }}" placeholder="Nama jurusan / singkatan, nama siswa, NIS"></div>
        <button class="btn btn-primary">Terapkan</button>
    </form>
</div>

@include('internships.major-cards')
<div class="header-actions roster-actions">
<a class="btn btn-secondary" href="{{ route('internships.index',['academic_year'=>$academicYear,'major'=>$selectedMajor,'batch'=>1]) }}#batch-jadwal">Atur Jadwal Batch</a>
@if($search !== '' || request('roster_id'))<a class="btn btn-ghost" href="{{ route('internships.index',['academic_year'=>$academicYear,'major'=>$selectedMajor]) }}">Hapus Pencarian</a>@endif
</div>
@if($batchMode)

<details class="card batch-schedule-card" id="batch-jadwal" open>
    <summary class="card-summary"><span><strong>Terapkan Jadwal PKL Massal</strong><small>Pilih jurusan, centang siswa yang ikut, lalu tentukan tanggal. Siswa yang dilepas hanya dikecualikan dari batch ini.</small></span><span class="btn btn-primary btn-sm">Atur Batch</span></summary>
    <form class="config-form" method="POST" action="{{ route('internships.schedule-batch') }}" id="schedule-batch-form">@csrf
        <input type="hidden" name="academic_year" value="{{ $academicYear }}">
        <div class="form-grid three">
            <div class="form-field"><label>Nama Batch</label><input name="name" value="{{ old('name','PKL '.$academicYear) }}" required placeholder="Contoh: PKL Serentak Gelombang 1"></div>
            <div class="form-field"><label>Mulai PKL</label><input type="date" name="internship_starts_at" value="{{ old('internship_starts_at') }}" required></div>
            <div class="form-field"><label>Selesai PKL</label><input type="date" name="internship_ends_at" value="{{ old('internship_ends_at') }}" required><div class="field-help">Masuk sekolah otomatis pada hari berikutnya.</div></div>
        </div>

        <div class="batch-section">
            <div class="batch-section-head"><div><strong>1. Pilih jurusan</strong><small>Boleh satu, beberapa, atau seluruh jurusan.</small></div><button type="button" class="btn btn-ghost btn-sm" id="select-all-majors">Pilih semua jurusan</button></div>
            <div class="major-checkbox-grid">
                @foreach($majors as $major)
                <label class="check-card"><input type="checkbox" class="major-checkbox" name="selected_majors[]" value="{{ $major }}" @checked(in_array($major,old('selected_majors',$selectedMajor ? [$selectedMajor] : []),true))><span>{{ $major }}</span><small>{{ $batchCandidates->where('major',$major)->count() }} siswa</small></label>
                @endforeach
            </div>
        </div>

        <div class="batch-section">
            <div class="batch-section-head"><div><strong>2. Periksa siswa dan pengecualian</strong><small>Memilih jurusan akan mencentang semua siswanya. Lepas checkbox untuk siswa yang dikecualikan.</small></div><span class="pill"><b id="batch-selected-count">0</b> siswa dipilih</span></div>
            <input id="batch-student-search" placeholder="Cari nama, NIS, atau kelas di dalam pilihan batch">
            <div class="batch-student-groups">
                @foreach($majors as $major)
                <section class="batch-major-group" data-major="{{ $major }}" hidden>
                    <div class="batch-major-heading"><strong>{{ $major }}</strong><button type="button" class="link toggle-major-students" data-major="{{ $major }}">Pilih/lepas semua</button></div>
                    <div class="batch-student-list">
                    @forelse($batchCandidates->where('major',$major) as $candidate)
                        <label class="batch-student" data-search="{{ mb_strtolower($candidate->name.' '.$candidate->nis.' '.$candidate->nisn.' '.$candidate->class_name) }}">
                            <input type="checkbox" class="batch-student-checkbox" name="roster_ids[]" value="{{ $candidate->id }}" data-major="{{ $major }}" @checked(in_array($candidate->id,old('roster_ids',$defaultRosterIds)))>
                            <span><strong>{{ $candidate->name }}</strong><small>NIS {{ $candidate->nis }} · {{ $candidate->class_name }}</small></span>
                            <em>{{ $candidate->lifecycleLabel() }}</em>
                        </label>
                    @empty<div class="empty">Belum ada siswa di jurusan ini.</div>@endforelse
                    </div>
                </section>
                @endforeach
            </div>
        </div>

        <label class="inline-check"><input type="checkbox" name="overwrite_batch_schedules" value="1"> Timpa jadwal batch sebelumnya pada siswa terpilih <small>(jadwal individual tetap dilindungi)</small></label>
        <div class="note-box">Tanggal selesai termasuk hari PKL. Sistem menampilkan <strong>Masuk sekolah</strong> mulai tanggal berikutnya. Tidak ada tombol konfirmasi mulai/selesai.</div>
        <div class="form-footer"><button class="btn btn-primary">Terapkan Jadwal ke Siswa Terpilih</button></div>
    </form>
</details>

@endif

@if($recentBatches->isNotEmpty())
<details class="card"><summary class="card-summary"><span><strong>Riwayat Batch Terbaru</strong><small>Identitas koordinator pembuat dan jumlah siswa tersimpan untuk audit.</small></span><span class="pill">{{ $recentBatches->count() }} batch</span></summary><div class="table-wrap"><table><thead><tr><th>Batch</th><th>Cakupan</th><th>Jadwal</th><th>Siswa</th><th>Dibuat oleh</th></tr></thead><tbody>@foreach($recentBatches as $batch)<tr><td><strong>{{ $batch->name }}</strong></td><td>{{ implode(', ',$batch->selected_majors) }}</td><td>{{ $batch->starts_at->format('d/m/Y') }} – {{ $batch->ends_at->format('d/m/Y') }}</td><td>{{ $batch->student_count }}</td><td>{{ $batch->creator?->name ?: 'Akun dihapus' }}<div class="small muted">{{ $batch->created_at->format('d/m/Y H:i') }}</div></td></tr>@endforeach</tbody></table></div></details>
@endif

@if($showStudents)
<div class="card" id="hasil-siswa"><div class="card-head"><div><h2>{{ $selectedMajor ? 'Daftar Siswa Jurusan' : 'Hasil Pencarian Siswa' }}</h2><p>Ubah individual hanya untuk pengecualian; jadwal ini dilindungi dari batch berikutnya.</p></div><span class="pill">{{ $items->total() }} data</span></div><div class="table-wrap"><table class="roster-table"><thead><tr><th>Siswa</th><th>Kelas / Jurusan</th><th>Keikutsertaan</th><th>Fase Hari Ini</th><th>Rentang PKL</th><th>Sumber / Editor</th><th></th></tr></thead><tbody>
    @forelse($items as $item)@php $state=$item->lifecycleState(); $stateClass=match($state){'pkl','school'=>'approved','scheduled','unscheduled'=>'submitted','not_internship'=>'rejected'}; @endphp<tr><td><strong>{{ $item->name }}</strong>@if(!$item->student_id)<div class="pill submitted">Akun belum terhubung: lengkapi tanggal lahir</div>@endif<div class="small muted">NIS {{ $item->nis }}@if($item->nisn) · NISN {{ $item->nisn }}@endif</div></td><td>{{ $item->class_name }}<div class="small muted">{{ $item->major }}</div><a href="{{ route('internships.index',['academic_year'=>$academicYear,'major'=>$item->major,'roster_id'=>$item->id]) }}#hasil-siswa">Buka siswa di jurusan</a></td><td>{{ $item->statusLabel() }}</td><td><span class="pill {{ $stateClass }}">{{ $item->lifecycleLabel() }}</span></td><td>@if($item->internship_starts_at){{ $item->internship_starts_at->format('d/m/Y') }} – {{ $item->internship_ends_at->format('d/m/Y') }}<div class="small muted">Masuk sekolah {{ $item->returnToSchoolDate()->format('d/m/Y') }}</div>@else—@endif</td><td>{{ $item->scheduleSourceLabel() }}<div class="small muted">{{ $item->updater?->name ?: 'Impor awal' }} · {{ $item->updated_at->format('d/m/Y H:i') }}</div></td><td><details><summary class="link" style="cursor:pointer">Atur individual</summary><form method="POST" action="{{ route('internships.update',$item) }}" class="stack roster-edit-form">@csrf @method('PUT')@if(!$item->student_id)<label>Tanggal lahir untuk membuat akun<input type="date" name="birth_date"><small>Wajib jika NIS ini belum memiliki akun.</small></label>@endif<label>Keikutsertaan<select name="internship_status">@foreach(\App\Models\InternshipRoster::statuses() as $value=>$label)<option value="{{ $value }}" @selected($item->internship_status===$value)>{{ $label }}</option>@endforeach</select></label><label>Mulai PKL<input type="date" name="internship_starts_at" value="{{ $item->internship_starts_at?->toDateString() }}"></label><label>Selesai PKL<input type="date" name="internship_ends_at" value="{{ $item->internship_ends_at?->toDateString() }}"></label><label>Catatan<textarea name="notes">{{ $item->notes }}</textarea></label><button class="btn btn-primary btn-sm">Simpan Pengecualian</button></form></details></td></tr>@empty<tr><td colspan="7"><div class="empty">Tidak ada siswa yang sesuai filter.</div></td></tr>@endforelse
    </tbody></table></div>@include('partials.pager',['items'=>$items])</div>
@endif
@endsection

@push('scripts')
<script>
(function(){
 const majors=Array.from(document.querySelectorAll('.major-checkbox')), students=Array.from(document.querySelectorAll('.batch-student-checkbox')), groups=Array.from(document.querySelectorAll('.batch-major-group')), count=document.getElementById('batch-selected-count'), search=document.getElementById('batch-student-search');
 function refresh(){if(!count)return;const selected=new Set(majors.filter(x=>x.checked).map(x=>x.value));groups.forEach(group=>group.hidden=!selected.has(group.dataset.major));count.textContent=students.filter(x=>x.checked&&selected.has(x.dataset.major)).length;}
 majors.forEach(box=>box.addEventListener('change',()=>{students.filter(x=>x.dataset.major===box.value).forEach(x=>x.checked=box.checked);refresh();}));
 document.getElementById('select-all-majors')?.addEventListener('click',()=>{const on=majors.some(x=>!x.checked);majors.forEach(x=>x.checked=on);students.forEach(x=>x.checked=on);refresh();});
 document.querySelectorAll('.toggle-major-students').forEach(button=>button.addEventListener('click',()=>{const matching=students.filter(x=>x.dataset.major===button.dataset.major),on=matching.some(x=>!x.checked);matching.forEach(x=>x.checked=on);refresh();}));
 students.forEach(box=>box.addEventListener('change',refresh));
 search?.addEventListener('input',()=>{const q=search.value.toLocaleLowerCase('id');document.querySelectorAll('.batch-student').forEach(row=>row.hidden=!row.dataset.search.includes(q));});
 refresh();
})();
</script>
@endpush
