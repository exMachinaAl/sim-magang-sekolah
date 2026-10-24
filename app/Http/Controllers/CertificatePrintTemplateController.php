<?php
namespace App\Http\Controllers;

use App\Models\{AssessmentTemplate, CertificatePrintTemplate};
use App\Support\{Audit, CertificateDocx, CertificatePrint};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CertificatePrintTemplateController
{
    public function index(Request $r)
    {
        $r->validate(['assessment_template_id'=>'nullable|integer|exists:assessment_templates,id']);
        $assessmentTemplate=$r->integer('assessment_template_id')?AssessmentTemplate::findOrFail($r->integer('assessment_template_id')):null;
        return view('certificates.print-templates',[
            'assessmentTemplates'=>AssessmentTemplate::where('is_active',true)->orderBy('major')->get(['id','name','major']),
            'assessmentTemplate'=>$assessmentTemplate,
            'items'=>CertificatePrintTemplate::select('id','name','assessment_template_id','front_variant','back_variant','front_hash')->with('assessmentTemplate')->latest()->paginate(15),
        ]);
    }

    public function store(Request $r)
    {
        $rules=['name'=>'required|string|max:150','assessment_template_id'=>'required|integer|exists:assessment_templates,id',
            'front_variant'=>['required',Rule::in(array_keys(config('certificate_print.front_variants')))],
            'back_variant'=>['required',Rule::in(array_keys(config('certificate_print.back_variants')))],
            'renderer'=>'required|in:builtin,docx','grade_format'=>'required|in:custom,80_20',
            'front_docx'=>'required_if:renderer,docx|nullable|file|mimes:docx|max:4096',
            'back_docx'=>'required_if:renderer,docx|nullable|file|mimes:docx|max:4096'];
        foreach (config('certificate_print.settings') as $key=>$label) $rules['settings.'.$key]='required|string|max:150';
        foreach (config('certificate_print.mapping') as $key=>$label) $rules['score_mapping.'.$key]='required_if:grade_format,80_20|nullable|string|max:30';
        $data=$r->validate($rules); $assessment=AssessmentTemplate::findOrFail($data['assessment_template_id']);
        abort_unless($assessment->is_active,422,'Template penilaian sudah diarsipkan.');
        $mapping=null;
        if ($data['grade_format']==='80_20') {
            $mapping=[];
            foreach (array_keys(config('certificate_print.mapping')) as $key) $mapping[$key]=$data['score_mapping'][$key];
            app(CertificatePrint::class)->validateMapping($mapping,$assessment->fields);
        }
        $files=[];
        if ($data['renderer']==='docx') foreach (['front','back'] as $side) {
            $file=$r->file($side.'_docx'); $service=app(CertificateDocx::class);
            $bytes=$service->sanitize($file->getRealPath());
            $tokens=$service->temporary(function($dir) use($bytes,$service) { $path=$dir.'/clean.docx'; file_put_contents($path,$bytes); return $service->inspect($path); });
            $required=$side==='front'?['nama_siswa','nis','nomor_sertifikat','tanggal_mulai_pkl','tanggal_selesai_pkl','nama_kepala_sekolah','nip_kepala_sekolah']
                :['nama_siswa','nisn_nipd','nilai_akhir_pkl','nama_pembimbing','tanggal_penilaian'];
            if ($side==='front' && $data['front_variant']==='kepala_sekolah_dan_industri') $required[]='nama_pimpinan_industri';
            if ($side==='back') {
                if ($data['back_variant']==='pembimbing_sekolah') $required[]='nip_pembimbing';
                if ($mapping) {
                    foreach (array_keys($mapping) as $key) { $required[]='nilai_'.$key; if ($key!=='laporan_pkl') $required[]='deskripsi_'.$key; }
                    $required[]='nilai_rata_rata_aspek';
                } else foreach ($assessment->fields as $i=>$field) foreach (['aspek','deskripsi','hasil'] as $prefix) $required[]=$prefix.'_'.($i+1);
            }
            if ($missing=array_diff($required,$tokens)) CertificatePrint::fail('Token wajib pada '.$side.' belum ada: '.implode(', ',$missing));
            // A token belonging to another signature choice would otherwise print incorrect parties.
            if (($side==='front' && $data['front_variant']==='kepala_sekolah' && in_array('nama_pimpinan_industri',$tokens,true))
                || ($side==='back' && $data['back_variant']==='pembimbing_industri' && in_array('nip_pembimbing',$tokens,true))) CertificatePrint::fail('Token penandatangan DOCX tidak sesuai tipe yang dipilih.');
            $files[$side.'_docx']=base64_encode($bytes); $files[$side.'_hash']=hash('sha256',$bytes);
        }
        DB::transaction(function () use ($r,$data,$mapping,$files) {
            $template=CertificatePrintTemplate::create(array_intersect_key($data,array_flip(['name','assessment_template_id','front_variant','back_variant','settings']))
                + ['score_mapping'=>$mapping,'created_by'=>$r->user()->id] + $files);
            Audit::record('certificate_print_template.created',$template,['front_hash'=>$template->front_hash,'back_hash'=>$template->back_hash]);
        });
        return redirect()->route('certificate-print-templates.index')->with('success','Template cetak tersimpan sebagai versi baru. Arsip lama tidak diubah.');
    }

    public function sample(string $variant)
    {
        $names=['kepala_sekolah'=>'sertifikat_kepala_sekolah.docx','kepala_sekolah_dan_industri'=>'sertifikat_kepala_sekolah_dan_industri.docx',
            'pembimbing_industri'=>'daftar_nilai_pembimbing_industri.docx','pembimbing_sekolah'=>'daftar_nilai_pembimbing_sekolah.docx'];
        abort_unless(isset($names[$variant]),404);
        $bytes=app(CertificateDocx::class)->sanitize(resource_path('templates/certificates/'.$names[$variant]));
        return response($bytes,200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition'=>'attachment; filename="'.$names[$variant].'"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
