<?php
namespace App\Http\Controllers;

use App\Models\{AssessmentTemplate, CertificatePrintTemplate, CertificatePrintTemplateDefault, InternshipRoster};
use App\Support\{Audit, CertificateDocx, CertificatePrint, CertificatePdfBackground};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CertificatePrintTemplateController
{
    public function index(Request $r)
    {
        $r->validate([
            'assessment_template_id'=>'nullable|integer|exists:assessment_templates,id',
            'design_major'=>['nullable', Rule::in(InternshipRoster::officialMajors())],
        ]);
        $assessmentTemplate=$r->integer('assessment_template_id')?AssessmentTemplate::findOrFail($r->integer('assessment_template_id')):null;
        $designMajor=$r->input('design_major') ?: $assessmentTemplate?->major;
        return view('certificates.print-templates',[
            'assessmentTemplates'=>AssessmentTemplate::where('is_active',true)->orderBy('major')->get(['id','name','major']),
            'assessmentTemplate'=>$assessmentTemplate,
            'designMajor'=>$designMajor,
            'majors'=>InternshipRoster::officialMajors(),
            'defaultsByTemplate'=>CertificatePrintTemplateDefault::query()->get()->groupBy('certificate_print_template_id'),
            'items'=>CertificatePrintTemplate::select('id','name','assessment_template_id','design_major','front_variant','back_variant','front_hash','front_pdf_hash')
                ->with('assessmentTemplate')->latest()->paginate(15),
        ]);
    }

    public function store(Request $r)
    {
        $rules=['name'=>'required|string|max:150','assessment_template_id'=>'required|integer|exists:assessment_templates,id',
            'design_major'=>['nullable', Rule::in(InternshipRoster::officialMajors())], 'make_default'=>'nullable|boolean',
            'front_variant'=>['required',Rule::in(array_keys(config('certificate_print.front_variants')))],
            'back_variant'=>['required',Rule::in(array_keys(config('certificate_print.back_variants')))],
            'renderer'=>'required|in:builtin,pdf_background,docx,docx_background','grade_format'=>'required|in:custom,80_20',
            'front_pdf'=>[Rule::requiredIf(fn()=>in_array($r->input('renderer'),['pdf_background','docx_background'],true)),'nullable','file','mimes:pdf','max:2048'],
            'back_pdf'=>[Rule::requiredIf(fn()=>in_array($r->input('renderer'),['pdf_background','docx_background'],true)),'nullable','file','mimes:pdf','max:2048'],
            'front_docx'=>[Rule::requiredIf(fn()=>in_array($r->input('renderer'),['docx','docx_background'],true)),'nullable','file','mimes:docx','max:4096'],
            'back_docx'=>[Rule::requiredIf(fn()=>in_array($r->input('renderer'),['docx','docx_background'],true)),'nullable','file','mimes:docx','max:4096']];
        foreach (['front','back'] as $side) foreach (['x','y','width','height'] as $key) {
            $rules['pdf_layout.'.$side.'.'.$key]='required_if:renderer,pdf_background|nullable|numeric|between:0,297';
        }
        foreach (config('certificate_print.settings') as $key=>$label) $rules['settings.'.$key]='required|string|max:150';
        foreach (config('certificate_print.mapping') as $key=>$label) $rules['score_mapping.'.$key]='required_if:grade_format,80_20|nullable|string|max:30';
        $data=$r->validate($rules); $assessment=AssessmentTemplate::findOrFail($data['assessment_template_id']);
        abort_unless($assessment->is_active,422,'Template penilaian sudah diarsipkan.');
        $designMajor=$data['design_major'] ?? $assessment->major;
        $makeDefault=$r->boolean('make_default') || !CertificatePrintTemplateDefault::where('major',$designMajor)->exists();
        $mapping=null;
        if ($data['grade_format']==='80_20') {
            $mapping=[];
            foreach (array_keys(config('certificate_print.mapping')) as $key) $mapping[$key]=$data['score_mapping'][$key];
            app(CertificatePrint::class)->validateMapping($mapping,$assessment->fields);
        }
        $files=[];
        if (in_array($data['renderer'],['pdf_background','docx_background'],true)) {
            $service=app(CertificatePdfBackground::class);
            if ($data['renderer']==='pdf_background') $files['pdf_layout']=$service->validateLayout($data['pdf_layout']);
            foreach (['front','back'] as $side) {
                $bytes=$service->normalize(file_get_contents($r->file($side.'_pdf')->getRealPath()));
                $files[$side.'_pdf']=base64_encode($bytes);
                $files[$side.'_pdf_hash']=hash('sha256',$bytes);
            }
        }
        if (in_array($data['renderer'],['docx','docx_background'],true)) foreach (['front','back'] as $side) {
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
        DB::transaction(function () use ($r,$data,$mapping,$files,$designMajor,$makeDefault) {
            $template=CertificatePrintTemplate::create(array_intersect_key($data,array_flip(['name','assessment_template_id','front_variant','back_variant','settings']))
                + ['design_major'=>$designMajor,'score_mapping'=>$mapping,'created_by'=>$r->user()->id] + $files);
            if ($makeDefault) CertificatePrintTemplateDefault::updateOrCreate(['major'=>$designMajor],[
                'certificate_print_template_id'=>$template->id,'updated_by'=>$r->user()->id,
            ]);
            Audit::record('certificate_print_template.created',$template,['front_hash'=>$template->front_hash,'back_hash'=>$template->back_hash,
                'renderer'=>$template->renderer(),'front_pdf_hash'=>$template->front_pdf_hash,'back_pdf_hash'=>$template->back_pdf_hash,
                'design_major'=>$designMajor,'is_default'=>$makeDefault]);
        });
        return redirect()->route('certificate-print-templates.index',['assessment_template_id'=>$assessment->id,'design_major'=>$designMajor])
            ->with('success',$makeDefault
                ? 'Desain cetak tersimpan sebagai versi baru dan menjadi default jurusan. Arsip lama tidak diubah.'
                : 'Desain cetak tersimpan sebagai pilihan tambahan. Default jurusan dan arsip lama tidak berubah.');
    }

    public function makeDefault(Request $request, CertificatePrintTemplate $template)
    {
        $data=$request->validate(['major'=>['required',Rule::in(InternshipRoster::officialMajors())]]);
        DB::transaction(function () use ($request,$template,$data): void {
            CertificatePrintTemplateDefault::updateOrCreate(['major'=>$data['major']],[
                'certificate_print_template_id'=>$template->id,'updated_by'=>$request->user()->id,
            ]);
            Audit::record('certificate_print_template.default_changed',$template,[
                'major'=>$data['major'],'default_template_id'=>$template->id,
            ]);
        });
        return back()->with('success','Desain default '.$data['major'].' diperbarui. Sertifikat dan arsip yang sudah dibuat tidak berubah.');
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

    public function pdfSample(string $side)
    {
        abort_unless(in_array($side,['front','back'],true),404);
        return response(app(CertificatePdfBackground::class)->sample($side),200,[
            'Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="contoh-dekorasi-'.$side.'.pdf"',
            'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
