<?php
namespace Tests\Feature;

use App\Models\{Certificate, CertificateWordExport, Placement, User};
use App\Support\CertificateDocx;
use Database\Seeders\{CertificateDemoWordSeeder, CertificatePreviewDemoSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class CertificateWordArchiveTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local'); $this->seed();
        $this->actingAs(User::where('role','koordinator')->firstOrFail());
        config(['certificate_print.libreoffice'=>'']);
    }
    private function demo(): Certificate
    {
        $this->seed(CertificatePreviewDemoSeeder::class);
        return Certificate::whereHas('placement.student',fn($q)=>$q->where('nis',CertificatePreviewDemoSeeder::NIS))->firstOrFail();
    }
    private function xml(string $bytes): string
    {
        return app(CertificateDocx::class)->temporary(function($dir) use($bytes) {
            $path=$dir.'/check.docx'; file_put_contents($path,$bytes); $zip=new \ZipArchive; $zip->open($path);
            $xml=$zip->getFromName('word/document.xml'); $zip->close(); return $xml;
        });
    }
    public function test_word_uses_frozen_source_and_preserves_original_pdf_and_is_idempotent(): void
    {
        $c=$this->demo(); $document=$c->documents()->firstOrFail();
        $before=$document->toArray(); $pdf=$this->get(route('certificates.pdf',$c))->assertOk()->getContent();
        $this->get(route('certificates.docx',[$c,'front']))->assertConflict();
        $c->placement->student->user->update(['name'=>'NAMA SUDAH BERUBAH']);
        $c->placement->assessment->update(['final_score'=>10]);
        $this->post(route('certificates.prepare-word',$c),['version'=>1])->assertSessionHasNoErrors()->assertRedirect();
        $front=$this->get(route('certificates.docx',[$c,'front']))->assertOk()->getContent();
        $back=$this->get(route('certificates.docx',[$c,'back']))->assertOk()->getContent();
        $this->assertStringContainsString('Siswa Uji Sertifikat (DEMO)',$this->xml($front));
        $this->assertStringNotContainsString('NAMA SUDAH BERUBAH',$this->xml($front));
        $this->assertStringContainsString('85,00',$this->xml($back));
        $this->assertStringNotContainsString('${',$this->xml($back));
        $this->assertStringContainsString('w:sectPr',$this->xml($front));
        $this->assertSame($before,$document->fresh()->toArray());
        $this->assertSame($pdf,$this->get(route('certificates.pdf',$c))->getContent());
        $this->seed(CertificateDemoWordSeeder::class);
        $this->assertDatabaseCount('certificate_word_exports',1);
        $this->assertSame($front,$this->get(route('certificates.docx',[$c,'front']))->getContent());
        $this->get(route('certificates.show',$c))->assertOk()->assertSee('Unduh DOCX depan')->assertSee('DOCX belakang v1');
    }
    public function test_only_coordinator_can_prepare_and_download_is_scoped_and_hash_verified(): void
    {
        $c=$this->demo();
        $this->post(route('certificates.prepare-word',$c),['version'=>99])->assertNotFound();
        $this->post(route('certificates.prepare-word',$c),['version'=>1])->assertSessionHasNoErrors();
        foreach (['guru','siswa','admin'] as $role) {
            $this->actingAs(User::where('role',$role)->firstOrFail())->post(route('certificates.prepare-word',$c),['version'=>1])->assertForbidden();
        }
        $this->actingAs(User::where('role','siswa')->where('id','!=',$c->placement->student->user_id)->firstOrFail())
            ->get(route('certificates.docx',[$c,'front']))->assertNotFound();
        $this->actingAs($c->placement->student->user)->get(route('certificates.docx',[$c,'front']))->assertOk();
        DB::table('certificate_word_exports')->update(['front_docx'=>base64_encode('tampered')]);
        $this->get(route('certificates.docx',[$c,'front']))->assertConflict();
    }
    public function test_no_draft_or_incomplete_legacy_export_and_archive_cannot_be_overwritten(): void
    {
        $legacy=Certificate::where('status','awaiting_print')->firstOrFail();
        $this->post(route('certificates.prepare-word',$legacy),['version'=>1])->assertSessionHasErrors('certificate');
        $pending=Certificate::where('status','awaiting_approval')->firstOrFail();
        $this->post(route('certificates.prepare-word',$pending),['version'=>1])->assertForbidden();
        $c=$this->demo(); $this->seed(CertificateDemoWordSeeder::class);
        $this->expectException(\LogicException::class);
        CertificateWordExport::firstOrFail()->update(['front_hash'=>str_repeat('0',64)]);
    }
    public function test_full_http_flow_from_industry_scores_to_issued_word_with_free_text_aspects(): void
    {
        $p=Placement::whereDoesntHave('assessment')->firstOrFail();
        $fields=[['label'=>'Kompetensi praktik','description'=>'Uji kemampuan industri.','type'=>'score','weight'=>100],
            ['label'=>'Uraian pekerjaan','description'=>'Kegiatan yang dinilai.','type'=>'text','weight'=>0]];
        $this->post(route('assessment-templates.store'),['name'=>'Uji alur penuh','major'=>$p->roster->major,
            'certificate_heading'=>'SERTIFIKAT UJI','certificate_statement'=>'Contoh alur pengujian.','fields'=>$fields])->assertSessionHasNoErrors();
        $template=\App\Models\AssessmentTemplate::latest('id')->firstOrFail();
        $score=['placement_id'=>$p->id,'template_id'=>$template->id,'industry_assessor'=>'Penilai industri uji',
            'assessed_on'=>now('Asia/Jakarta')->toDateString(),'final_score'=>88,'values'=>['field_1'=>88,'field_2'=>'Teks bebas & pekerjaan khusus.']];
        $this->post(route('assessments.store'),$score+['revision'=>0,'intent'=>'draft'])->assertSessionHasNoErrors();
        $this->post(route('assessments.store'),$score+['revision'=>1,'intent'=>'submitted'])->assertSessionHasNoErrors();
        $a=$p->assessment()->firstOrFail();
        $this->put(route('assessments.approve',$a),['revision'=>2,'decision'=>'approved'])->assertSessionHasNoErrors();
        $settings=[]; foreach (config('certificate_print.settings') as $key=>$label) $settings[$key]=$label.' uji';
        $this->post(route('certificate-print-templates.store'),['name'=>'Cetak alur penuh','assessment_template_id'=>$template->id,
            'front_variant'=>'kepala_sekolah','back_variant'=>'pembimbing_sekolah','settings'=>$settings,'grade_format'=>'custom','renderer'=>'builtin'])->assertSessionHasNoErrors();
        $print=\App\Models\CertificatePrintTemplate::latest('id')->firstOrFail();
        $this->post(route('certificates.create'),['placement_id'=>$p->id,'print_template_id'=>$print->id,'print_options'=>[
            'birth_place'=>'Gresik','bidang_pekerjaan'=>'Pekerjaan uji','tanggal_pengesahan'=>now('Asia/Jakarta')->toDateString(),
            'nisn_nipd'=>'00001','nama_pembimbing'=>'Pembimbing sekolah uji','nip_pembimbing'=>'000002']])->assertSessionHasNoErrors();
        $c=$p->certificate()->firstOrFail();
        $this->post(route('certificates.prepare-word',$c),['version'=>1])->assertForbidden();
        $this->put(route('certificates.approve',$c),['version'=>1,'decision'=>'approved'])->assertSessionHasNoErrors();
        $this->post(route('certificates.prepare-word',$c),['version'=>1])->assertSessionHasNoErrors();
        $back=$this->get(route('certificates.docx',[$c,'back']))->assertOk()->getContent();
        $this->assertStringContainsString('Teks bebas &amp; pekerjaan khusus.',$this->xml($back));
        $this->assertStringContainsString('000002',$this->xml($back));
        $this->assertSame('88.00',$a->fresh()->final_score);
        $this->get(route('certificates.pdf',$c))->assertOk();
        // Revision does not erase or silently regenerate the already-issued Word version.
        $this->put(route('certificates.resubmit',$c),['version'=>1,'note'=>'Uji revisi tanpa menghapus arsip.'])->assertSessionHasNoErrors();
        $this->assertSame($back,$this->get(route('certificates.docx',[$c,'back','version'=>1]))->assertOk()->getContent());
        $this->get(route('certificates.docx',[$c,'back']))->assertForbidden();
    }
}
