@extends('layouts.app')
@section('title','Monitoring PKL')
@section('breadcrumb','Operasional / Monitoring')
@section('subtitle','Pengaduan siswa, laporan pendamping, dan tindak lanjut koordinator dalam satu riwayat kasus.')
@section('content')
@if(auth()->user()->isRole('koordinator','guru') && $studentOpenCount>0)<p class="alert error"><strong>{{ $studentOpenCount }} pengaduan siswa perlu diperiksa.</strong> Buka detail kasus untuk melihat sumber laporan; tindak lanjut dan status dikontrol koordinator.</p>
@elseif($isStudent)<p class="note-box">Gunakan Monitoring untuk mengadukan kendala PKL. Pengaduan akan terlihat oleh koordinator dan guru pendamping yang ditugaskan; status penanganannya dapat Anda pantau di halaman ini.</p>@endif
<div class="card">
 <div class="card-head"><h2>Cari Siswa & Riwayat</h2></div>
 <form method="GET" action="{{ route('monitoring.index') }}">
  <div class="form-grid">
   <div class="form-field"><label for="monitoring-q">Nama / NIS</label><input id="monitoring-q" name="q" value="{{ $q }}" maxlength="100" placeholder="Ketik nama atau NIS siswa"></div>
   <div class="form-field"><label for="monitoring-period">Tag Tahun Ajaran</label><select id="monitoring-period" name="period_id"><option value="" @selected($periodId===null)>Semua tahun</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected((string)$periodId === (string)$period->id)>{{ $period->academic_year }}{{ $period->is_active ? '' : ' (arsip)' }}</option>@endforeach</select></div>
   <div class="form-field"><label for="monitoring-major">Jurusan pada Tahun PKL</label><select id="monitoring-major" name="major"><option value="">Semua jurusan</option>@foreach($majors as $name)<option value="{{ $name }}" @selected($major === $name)>{{ $name }}</option>@endforeach</select></div>
   <div class="form-field"><label for="monitoring-status">Status riwayat kasus</label><select id="monitoring-status" name="case_status"><option value="">Semua status</option>@foreach(\App\Models\MonitoringRecord::statuses() as $key=>$label)<option value="{{ $key }}" @selected(request('case_status')===$key)>{{ $label }}</option>@endforeach</select></div>
   <div class="form-field"><label for="monitoring-category-filter">Kategori riwayat kasus</label><select id="monitoring-category-filter" name="category"><option value="">Semua kategori</option>@foreach(\App\Models\MonitoringRecord::categories() as $key=>$label)<option value="{{ $key }}" @selected(request('category')===$key)>{{ $label }}</option>@endforeach</select></div>
   @if(!$isStudent)<div class="form-field"><label for="monitoring-reporter">Pengaju laporan</label><select id="monitoring-reporter" name="reporter"><option value="">Semua pengaju</option>@foreach(\App\Models\MonitoringRecord::reporterRoles() as $key=>$label)<option value="{{ $key }}" @selected(request('reporter')===$key)>{{ $label }}</option>@endforeach</select></div>@endif
  </div>
  <div class="form-footer"><button class="btn btn-primary">Cari</button><a class="btn btn-ghost" href="{{ route('monitoring.index') }}">Reset</a></div>
 </form>
 <p class="small muted">Nama/NIS, tahun dan jurusan menyaring siswa serta riwayat. Status/kategori hanya menyaring riwayat kasus. Catatan ini mengikuti penempatan PKL; siswa belum ditempatkan tetap dikelola melalui Kontrol PKL.</p>
 @if($showCandidates)
 <div class="table-wrap"><table><thead><tr><th>Siswa / NIS</th><th>Kelas / Jurusan</th><th>Tahun</th><th></th></tr></thead><tbody>
 @forelse($candidates as $p)<tr><td>{{ $p->student->user->name }}<br>{{ $p->student->nis }}</td><td>{{ $p->roster?->class_name ?? '—' }}<br>{{ $p->roster?->major ?? 'Roster belum terhubung' }}</td><td>{{ $p->period->academic_year }}</td><td><a class="btn btn-sm btn-ghost" href="{{ route('monitoring.index', ['placement_id' => $p->id,'period_id'=>$periodId,'major'=>$major,'q'=>$q]) }}">Buka siswa</a></td></tr>
 @empty<tr><td colspan="4">Tidak ada siswa yang sesuai pencarian atau hak akses.</td></tr>@endforelse
 </tbody></table></div>
 @include('partials.pager', ['items' => $candidates])
 @endif
</div>

@if($selected)
<div class="card">
 <div class="card-head"><h2>{{ $isStudent?'Ajukan Kendala PKL':$selected->student->user->name.' · '.$selected->student->nis }}</h2></div>
 @if($isStudent)<p><strong>{{ $selected->student->user->name }}</strong> · NIS {{ $selected->student->nis }}</p>@endif
 <p>{{ $selected->period->academic_year }} · {{ $selected->roster?->class_name ?? '—' }} · {{ $selected->roster?->major ?? 'Roster belum terhubung' }}</p>
 <p class="small muted">Guru terkait: {{ $selected->teacher?->name ?? 'Tidak ditetapkan (opsional)' }}. Pengaju dicatat otomatis dari akun yang sedang masuk.</p>
 @if(auth()->user()->isRole('koordinator','guru','siswa'))
 <form method="POST" action="{{ route('monitoring.store') }}">@csrf
  <input type="hidden" name="placement_id" value="{{ $selected->id }}">
  <div class="form-grid">
   <div class="form-field"><label for="monitoring-date">Tanggal Kejadian / Kunjungan</label><input id="monitoring-date" name="visit_date" type="date" required value="{{ is_string(old('visit_date')) ? old('visit_date') : now('Asia/Jakarta')->toDateString() }}" max="{{ now('Asia/Jakarta')->toDateString() }}"></div>
   <div class="form-field"><label for="monitoring-category">Kategori kasus</label><select id="monitoring-category" name="category" required>@foreach(\App\Models\MonitoringRecord::categories() as $key=>$label)<option value="{{ $key }}" @selected(old('category','other')===$key)>{{ $label }}</option>@endforeach</select></div>
   <div class="form-field"><label for="monitoring-notes">{{ $isStudent?'Jelaskan kendala yang dialami':'Hasil Monitoring / Kendala' }}</label><textarea id="monitoring-notes" name="notes" required minlength="10" maxlength="5000" placeholder="{{ $isStudent?'Tuliskan kejadian, dampak, dan bantuan yang dibutuhkan.':'' }}">{{ is_string(old('notes')) ? old('notes') : '' }}</textarea></div>
   @if(!$isStudent)<div class="form-field"><label for="monitoring-follow-up">Tindak Lanjut Awal (opsional)</label><textarea id="monitoring-follow-up" name="follow_up" maxlength="3000">{{ is_string(old('follow_up')) ? old('follow_up') : '' }}</textarea></div>@endif
  </div>
  <div class="form-footer"><button class="btn btn-primary">{{ $isStudent?'Kirim Pengaduan':'Simpan Monitoring' }}</button></div>
 </form>
 @endif
</div>
@elseif($isStudent)
<p class="alert error">Penempatan PKL belum tersedia pada periode yang dipilih. Hubungi koordinator agar data penempatan diperiksa sebelum mengirim pengaduan.</p>
@elseif(auth()->user()->isRole('koordinator','guru'))
<p class="small muted">Cari nama/NIS atau pilih filter, lalu buka siswa untuk mencatat monitoring. Tidak ada kewajiban kunjungan rutin.</p>
@endif

<div class="card"><div class="card-head"><h2>Riwayat Monitoring</h2><span class="small muted">{{ $items->total() }} catatan</span></div>
 <div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Siswa / Tahun</th><th>Status / Kategori</th><th>Penanggung Jawab</th><th>Pengaju</th><th>Guru Terkait</th><th>Hasil</th><th>Tindak Lanjut</th><th></th></tr></thead><tbody>
 @forelse($items as $m)<tr>
  <td>{{ $m->visit_date->format('d M Y') }}</td>
  <td>{{ $m->placement->student->user->name }}<br>{{ $m->placement->student->nis }} · {{ $m->placement->period->academic_year }}<br>{{ $m->placement->roster?->major ?? 'Roster belum terhubung' }}</td>
  <td>{{ \App\Models\MonitoringRecord::statuses()[$m->case_status] ?? $m->case_status }}<br>{{ \App\Models\MonitoringRecord::categories()[$m->category] ?? 'Belum diklasifikasi' }}</td>
  <td>{{ $m->coordinator?->name ?? 'Menunggu penetapan koordinator' }}@if($m->coordinator_id)<br>ID: {{ $m->coordinator_id }}@endif</td>
  <td><span class="pill">Pengaju: {{ $m->reporterRoleLabel() }}</span><br>{{ ($m->creator ?? $m->teacher)?->name ?? 'Tidak tercatat' }}<br><span class="small muted">ID: {{ $m->created_by ?? $m->teacher_id ?? '—' }}</span></td>
  <td>{{ $m->teacher?->name ?? 'Tidak ditetapkan' }}</td><td>{{ $m->notes }}</td><td>{{ $m->latestUpdate?->note ?? $m->follow_up ?? '—' }}</td>
  <td><a class="btn btn-sm btn-secondary" href="{{ route('monitoring.show',$m) }}">Detail kasus</a></td>
 </tr>@empty<tr><td colspan="9"><div class="empty">Belum ada catatan yang sesuai.</div></td></tr>@endforelse
 </tbody></table></div>@include('partials.pager')
</div>
@endsection
