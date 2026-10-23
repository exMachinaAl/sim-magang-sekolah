@extends('layouts.app')
@section('title','Template Ketentuan Penilaian')
@section('subtitle','Koordinator menentukan aspek dan uraian kompetensi untuk digunakan bersama oleh siswa satu jurusan.')
@section('content')
<div class="card">
    <div class="card-head"><h2>{{ $copy ? 'Salin dan sesuaikan template' : 'Buat template jurusan' }}</h2><a class="btn btn-ghost" href="{{ route('assessments.index') }}">Kembali ke penilaian</a></div>
    <p class="muted">Setiap template tersimpan menjadi versi tersendiri. Gunakan Salin / revisi untuk membuat ketentuan baru tanpa mengubah penilaian siswa sebelumnya.</p>
    <form method="POST" action="{{ route('assessment-templates.store') }}">@csrf
        <input type="hidden" name="based_on_id" value="{{ old('based_on_id',$copy?->id) }}">
        <div class="form-grid">
            <label>Nama template<input name="name" value="{{ old('name',$copy ? $copy->name.' (revisi)' : '') }}" required maxlength="150" placeholder="Contoh: TKJ — Kompetensi Jaringan 2026"></label>
            <label>Jurusan<select name="major" required>@foreach($majors as $major)<option @selected(old('major',$copy?->major)===$major)>{{ $major }}</option>@endforeach</select></label>
            <label>Judul sertifikat depan<input name="certificate_heading" value="{{ old('certificate_heading',$copy?->certificate_heading ?? 'SERTIFIKAT') }}" maxlength="100" required></label>
            <label>Keterangan kompetensi di sertifikat depan<textarea name="certificate_statement" maxlength="400" required>{{ old('certificate_statement',$copy?->certificate_statement ?? 'Telah mengikuti Praktik Kerja Lapangan sesuai kompetensi keahlian.') }}</textarea></label>
        </div>
        <h3>Ketentuan yang dinilai dan dicetak di belakang</h3>
        <p>Tambahkan aspek angka (0–100) atau isian teks. Bobot angka berjumlah 100%; teks berbobot 0%. Untuk pola lampiran 80/20: empat aspek masing-masing 20%, lalu laporan 20%.</p>
        @php $initial=old('fields',$copy?->fields ?? [
            ['label'=>'Soft skills','description'=>'Etos kerja, kerja sama, integritas dan tanggung jawab.','type'=>'score','weight'=>20],
            ['label'=>'Hard skills','description'=>'Pelaksanaan pekerjaan sesuai prosedur dan keselamatan kerja.','type'=>'score','weight'=>20],
            ['label'=>'Pengembangan hard skills','description'=>'Isi kompetensi khusus sesuai jurusan dan pekerjaan industri.','type'=>'score','weight'=>20],
            ['label'=>'Kewirausahaan','description'=>'Pemahaman kegiatan usaha dan kemandirian di tempat kerja.','type'=>'score','weight'=>20],
            ['label'=>'Laporan PKL','description'=>'Nilai laporan yang tercantum pada dokumen penilaian.','type'=>'score','weight'=>20],
        ]); @endphp
        <div id="template-fields"></div>
        <div class="form-footer"><button type="button" class="btn btn-secondary" id="add-field">Tambah aspek / ketentuan</button><span id="weight-total" aria-live="polite"></span><button class="btn btn-primary">Simpan Template Baru</button></div>
    </form>
</div>
<div class="card"><h2>Template tersedia</h2><div class="table-wrap"><table><thead><tr><th>Template</th><th>Jurusan</th><th>Ketentuan</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($templates as $template)<tr><td>{{ $template->name }}<div class="small muted">Versi #{{ $template->id }} @if($template->based_on_id) · Salinan #{{ $template->based_on_id }}@endif</div></td><td>{{ $template->major }}</td><td><details><summary>{{ count($template->fields) }} aspek</summary>@foreach($template->fields as $field)<p><strong>{{ $field['label'] }}</strong> ({{ $field['weight'] }}%)<br>{{ $field['description'] }}</p>@endforeach</details></td><td>{{ $template->is_active ? 'Aktif' : 'Diarsipkan' }}</td><td><a class="btn btn-secondary btn-sm" href="{{ route('assessment-templates.index',['copy'=>$template->id]) }}">Salin / revisi</a><form method="POST" action="{{ route('assessment-templates.toggle',$template) }}">@csrf @method('PUT')<input type="hidden" name="is_active" value="{{ $template->is_active ? 0 : 1 }}"><button class="btn btn-ghost btn-sm">{{ $template->is_active ? 'Arsipkan' : 'Aktifkan' }}</button></form></td></tr>@empty<tr><td colspan="5">Belum ada template. Buat ketentuan pertama di atas.</td></tr>@endforelse
</tbody></table></div>@include('partials.pager',['items'=>$templates])</div>
@endsection
@push('scripts')
<script>
(() => {
 const initial = {{ Illuminate\Support\Js::from($initial) }};
 const container = document.getElementById('template-fields');
 let next = 0;
 function total(){document.getElementById('weight-total').textContent='Total bobot: '+Array.from(container.querySelectorAll('[data-weight]')).reduce((sum,input)=>sum+Number(input.value||0),0)+'%';}
 function add(field={label:'',description:'',type:'score',weight:0}){
   if(container.children.length>=12) return;
   const i=next++, row=document.createElement('fieldset'); row.style.marginBottom='16px';
   row.innerHTML='<legend>Aspek penilaian</legend><div class="form-grid"><label>Nama aspek<input data-label required maxlength="100"></label><label>Jenis isian<select data-type><option value="score">Angka 0–100</option><option value="text">Teks</option></select></label><label>Uraian / ketentuan<textarea data-description required maxlength="600"></textarea></label><label>Bobot (%)<input data-weight type="number" min="0" max="100" required></label></div><button type="button" class="btn btn-danger btn-sm">Hapus aspek</button>';
   for(const key of ['label','description','type','weight']){const input=row.querySelector('[data-'+key+']'); input.name='fields['+i+']['+key+']';input.value=field[key];}
   row.querySelector('button').onclick=()=>{row.remove();total();}; row.addEventListener('input',total);container.append(row);total();
 }
 Object.values(initial).forEach(add);document.getElementById('add-field').onclick=()=>add();
})();
</script>
@endpush
