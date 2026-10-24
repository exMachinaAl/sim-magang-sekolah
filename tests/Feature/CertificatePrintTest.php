<?php
namespace Tests\Feature;

use App\Models\{AssessmentTemplate, Certificate, CertificatePrintTemplate, Placement, User};
use App\Support\{CertificateDocx, CertificatePrint};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificatePrintTest extends TestCase
{
    use RefreshDatabase;
    protected Placement $placement;
    protected AssessmentTemplate $assessmentTemplate;
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 12:00:00','Asia/Jakarta'));
        Storage::fake('local'); $this->seed();
        $this->actingAs(User::where('role','koordinator')->firstOrFail());
        $this->placement=Placement::whereHas('assessment',fn($q)=>$q->where('status','approved'))->whereDoesntHave('certificate')->firstOrFail();
        $fields=[];
        foreach (array_values(config('certificate_print.mapping')) as $i=>$label) $fields[]=['key'=>'field_'.($i+1),'label'=>$label,'description'=>'Ketentuan '.$label,'weight'=>20,'type'=>'score'];
        $this->assessmentTemplate=AssessmentTemplate::create(['name'=>'Ketentuan Uji 80/20','major'=>$this->placement->roster->major,
            'certificate_heading'=>'SERTIFIKAT','certificate_statement'=>'Telah melaksanakan PKL.','fields'=>$fields,'created_by'=>auth()->id()]);
        $this->placement->assessment->update(['assessment_template_id'=>$this->assessmentTemplate->id,'template_snapshot'=>$this->assessmentTemplate->snapshot(),
            'field_values'=>['field_1'=>80,'field_2'=>82,'field_3'=>85,'field_4'=>89,'field_5'=>90],
            'calculated_score'=>85.2,'final_score'=>85,'final_score_reason'=>'Nilai resmi mengikuti dokumen industri.']);
    }
    private function templateData(array $extra=[]): array
    {
        $settings=[]; foreach (config('certificate_print.settings') as $key=>$label) $settings[$key]=$label.' Uji';
        $settings['nip_kepala_sekolah']='00123456789';
        return array_replace(['name'=>'Cetak uji','assessment_template_id'=>$this->assessmentTemplate->id,
            'front_variant'=>'kepala_sekolah','back_variant'=>'pembimbing_industri','renderer'=>'builtin','grade_format'=>'80_20',
            'settings'=>$settings,'score_mapping'=>array_combine(array_keys(config('certificate_print.mapping')),['field_1','field_2','field_3','field_4','field_5'])],$extra);
    }
    private function template(array $extra=[]): CertificatePrintTemplate
    {
        $this->post(route('certificate-print-templates.store'),$this->templateData($extra))->assertSessionHasNoErrors()->assertRedirect();
        return CertificatePrintTemplate::latest('id')->firstOrFail();
    }
    private function submission(CertificatePrintTemplate $template): array
    {
        return ['placement_id'=>$this->placement->id,'print_template_id'=>$template->id,'number'=>'0001/SEKOLAH/2026',
            'print_options'=>['birth_place'=>'Gresik','bidang_pekerjaan'=>'Pengujian','nisn_nipd'=>'00001/0002',
            'tanggal_pengesahan'=>'2026-10-24','nama_pimpinan_industri'=>'Pimpinan Uji','nama_pembimbing'=>'Pembimbing Sekolah Uji','nip_pembimbing'=>'00076543']];
    }
    private function upload(string $file): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($file,file_get_contents(resource_path('templates/certificates/'.$file)));
    }

    public function test_all_four_signature_combinations_keep_exact_final_and_historical_data(): void
    {
        $certificate=null; $firstPDF=null;
        foreach (array_keys(config('certificate_print.front_variants')) as $front) foreach (array_keys(config('certificate_print.back_variants')) as $back) {
            $template=$this->template(['front_variant'=>$front,'back_variant'=>$back]);
            $data=$this->submission($template);
            if (!$certificate) {
                $this->post(route('certificates.create'),$data)->assertSessionHasNoErrors();
                $certificate=$this->placement->certificate()->firstOrFail();
            } else {
                $this->put(route('certificates.resubmit',$certificate),$data+['version'=>$certificate->version,'note'=>'Uji variasi tanda tangan.'])->assertSessionHasNoErrors();
                $certificate->refresh();
            }
            $document=$certificate->documents()->where('version',$certificate->version)->firstOrFail();
            $values=$document->snapshot['print']['values'];
            $this->assertSame('84,00',$values['nilai_rata_rata_aspek']);
            $this->assertSame('85,20',$values['nilai_hitung']); $this->assertSame('85,00',$values['nilai_akhir_pkl']);
            $this->assertSame('00001/0002',$values['nisn_nipd']);
            $this->assertStringContainsString('00123456789',$document->rendered_html);
            $this->assertSame($front==='kepala_sekolah_dan_industri',str_contains($document->rendered_html,'Pimpinan Uji'));
            $this->assertSame($back==='pembimbing_sekolah',str_contains($document->rendered_html,'00076543'));
            $this->put(route('certificates.approve',$certificate),['version'=>$certificate->version,'decision'=>'approved'])->assertSessionHasNoErrors();
            $pdf=$this->get(route('certificates.pdf',$certificate))->assertOk()->getContent();
            $firstPDF??=$pdf;
            $this->assertSame($firstPDF,$this->get(route('certificates.pdf',[$certificate,'version'=>1]))->assertOk()->getContent());
            $this->get(route('certificates.show',$certificate))->assertOk()->assertSee('Format dokumen versi ini');
        }
        $this->assertSame('85.00',$this->placement->assessment->fresh()->final_score);
        $this->assertSame(4,$certificate->documents()->count());
    }

    public function test_prepare_and_template_pages_work_and_other_roles_cannot_write(): void
    {
        $t=$this->template();
        $this->get(route('certificate-print-templates.index',['assessment_template_id'=>$this->assessmentTemplate->id]))->assertOk()->assertSee('LibreOffice belum dikonfigurasi');
        $this->get(route('certificates.prepare',[$this->placement,'print_template_id'=>$t->id]))->assertOk()->assertSee('Tempat lahir');
        foreach (array_merge(array_keys(config('certificate_print.front_variants')),array_keys(config('certificate_print.back_variants'))) as $variant) {
            $this->get(route('certificate-print-templates.sample',$variant))->assertOk();
        }
        foreach (['admin','guru','siswa'] as $role) {
            $this->actingAs(User::where('role',$role)->firstOrFail());
            $this->get(route('certificate-print-templates.index'))->assertForbidden();
            $this->post(route('certificate-print-templates.store'),$this->templateData())->assertForbidden();
            $this->get(route('certificates.prepare',$this->placement))->assertForbidden();
            $this->post(route('certificates.create'),$this->submission($t))->assertForbidden();
        }
    }

    public function test_incomplete_identity_wrong_template_mapping_and_fractional_aspects_are_rejected(): void
    {
        $data=$this->templateData(); $data['score_mapping']['soft_skills']='field_2';
        $this->post(route('certificate-print-templates.store'),$data)->assertSessionHasErrors('certificate');
        $t=$this->template(['back_variant'=>'pembimbing_sekolah','front_variant'=>'kepala_sekolah_dan_industri']);
        $data=$this->submission($t); unset($data['print_options']['nip_pembimbing']);
        $this->post(route('certificates.create'),$data)->assertSessionHasErrors('print_options.nip_pembimbing');
        $birth=$this->placement->student->birth_date;
        $this->placement->student->update(['birth_date'=>null]);
        $this->post(route('certificates.create'),$this->submission($t))->assertSessionHasErrors('certificate');
        $this->placement->student->update(['birth_date'=>$birth]);
        $values=$this->placement->assessment->field_values; $values['field_1']=80.5;
        $this->placement->assessment->update(['field_values'=>$values]);
        $this->post(route('certificates.create'),$this->submission($t))->assertSessionHasErrors('certificate');
        $this->assertDatabaseMissing('certificates',['placement_id'=>$this->placement->id]);
    }

    public function test_builtin_custom_fields_support_text_without_forcing_four_aspects(): void
    {
        $p=Placement::whereHas('assessment',fn($q)=>$q->where('status','approved')->where('assessment_template_id','!=',$this->assessmentTemplate->id))->firstOrFail();
        $t=$this->template(['assessment_template_id'=>$p->assessment->assessment_template_id,'grade_format'=>'custom']);
        $old=$this->placement; $this->placement=$p; $data=$this->submission($t);
        // Use an existing certificate's revision when this seeded placement already has a document.
        if ($c=$p->certificate) $this->put(route('certificates.resubmit',$c),$data+['version'=>$c->version,'note'=>'Menguji aspek bebas.'])->assertSessionHasNoErrors();
        else $this->post(route('certificates.create'),$data)->assertSessionHasNoErrors();
        $doc=$p->certificate()->firstOrFail()->documents()->latest('version')->firstOrFail();
        $this->assertStringContainsString('Pekerjaan praktik sesuai jurusan.',$doc->rendered_html);
        $this->assertNull($doc->snapshot['print']['score_mapping']);
        $this->placement=$old;
    }

    public function test_docx_upload_fill_archive_download_and_missing_converter_fail_closed(): void
    {
        $t=$this->template(['renderer'=>'docx','front_docx'=>$this->upload('sertifikat_kepala_sekolah.docx'),
            'back_docx'=>$this->upload('daftar_nilai_pembimbing_industri.docx')]);
        config(['certificate_print.libreoffice'=>'']);
        $this->post(route('certificates.create'),$this->submission($t))->assertSessionHasErrors('certificate');
        $this->assertDatabaseMissing('certificates',['placement_id'=>$this->placement->id]);
        // Only conversion is simulated. Upload validation, XML substitution, archive and PDF merging are real.
        $this->app->instance(CertificateDocx::class,new class extends CertificateDocx {
            public function convert(array $files,string $dir): string {
                $pdfs=[];
                foreach ($files as $i=>$file) {
                    $processor=new \PhpOffice\PhpWord\TemplateProcessor($file);
                    if ($processor->getVariables()) throw new \RuntimeException('Unfilled DOCX');
                    $target=$dir.'/converted-'.$i.'.pdf';
                    file_put_contents($target,Pdf::loadHTML('<p>Conversion fixture '.$i.'</p>')->setPaper('a4')->output()); $pdfs[]=$target;
                }
                return $this->merge($pdfs);
            }
        });
        $this->placement->student->user->update(['name'=>'Nama & Uji <XML>']);
        $this->post(route('certificates.create'),$this->submission($t))->assertSessionHasNoErrors();
        $c=$this->placement->certificate()->firstOrFail();
        $this->get(route('certificates.docx',[$c,'front']))->assertForbidden();
        $this->put(route('certificates.approve',$c),['version'=>1,'decision'=>'approved'])->assertSessionHasNoErrors();
        $bytes=$this->get(route('certificates.docx',[$c,'front']))->assertOk()->getContent();
        $this->assertSame($c->documents()->firstOrFail()->front_hash,hash('sha256',$bytes));
        app(CertificateDocx::class)->temporary(function($dir) use($bytes) {
            $path=$dir.'/inspect.docx'; file_put_contents($path,$bytes); $zip=new \ZipArchive; $zip->open($path);
            $xml=$zip->getFromName('word/document.xml'); $zip->close();
            $this->assertStringContainsString('Nama &amp; Uji &lt;XML&gt;',$xml);
            $this->assertStringNotContainsString('${',$xml);
        });
        $this->actingAs(User::where('role','siswa')->where('id','!=',$this->placement->student->user_id)->firstOrFail());
        $this->get(route('certificates.docx',[$c,'front']))->assertNotFound();
    }

    public function test_docx_with_unknown_tokens_external_content_or_wrong_variant_is_rejected(): void
    {
        $data=$this->templateData(['renderer'=>'docx','front_docx'=>$this->upload('sertifikat_kepala_sekolah_dan_industri.docx'),
            'back_docx'=>$this->upload('daftar_nilai_pembimbing_industri.docx')]);
        $this->post(route('certificate-print-templates.store'),$data)->assertSessionHasErrors('certificate');
        app(CertificateDocx::class)->temporary(function($dir) use ($data) {
            foreach (['${unknown_token}','<!DOCTYPE evil>'] as $bad) {
                $path=$dir.'/bad.docx'; copy(resource_path('templates/certificates/sertifikat_kepala_sekolah.docx'),$path);
                $zip=new \ZipArchive; $zip->open($path); $xml=$zip->getFromName('word/document.xml');
                $xml=str_replace('${nama_siswa}',$bad,$xml); $zip->addFromString('word/document.xml',$xml); $zip->close();
                $data['front_docx']=UploadedFile::fake()->createWithContent('bad.docx',file_get_contents($path));
                $this->post(route('certificate-print-templates.store'),$data)->assertSessionHasErrors('certificate');
            }
        });
        $this->assertDatabaseCount('certificate_print_templates',0);
    }

    public function test_print_source_changes_require_new_version_and_templates_are_immutable(): void
    {
        $t=$this->template(); $this->post(route('certificates.create'),$this->submission($t))->assertSessionHasNoErrors();
        $c=$this->placement->certificate()->firstOrFail(); $options=$c->print_options; $options['birth_place']='Kota lain'; $c->update(['print_options'=>$options]);
        $this->put(route('certificates.approve',$c),['version'=>1,'decision'=>'approved'])->assertSessionHasErrors('certificate');
        $this->assertStringContainsString('Gresik',$c->documents()->firstOrFail()->snapshot['print']['values']['tempat_tanggal_lahir']);
        $this->expectException(\LogicException::class); $t->update(['name'=>'Overwrite']);
    }
}
