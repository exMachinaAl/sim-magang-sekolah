<?php
namespace Tests\Feature;

use App\Models\{Assessment, AssessmentEvidence, AuditLog, Placement, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class AssessmentAuditFixTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local'); $this->seed();
        $this->actingAs(User::where('role','koordinator')->firstOrFail());
    }
    private function upload(Assessment $a,array $extra=[])
    {
        return $this->post(route('assessments.evidence.store',$a),array_replace([
            'revision'=>$a->revision,'file'=>UploadedFile::fake()->image('nilai-industri.png',40,40),
            'note'=>'Lembar nilai industri untuk pengujian.'], $extra));
    }
    public function test_historical_labels_and_filters_are_shared_by_candidates_and_recap(): void
    {
        $a=Assessment::firstOrFail(); $p=$a->placement; $major=$p->roster->major;
        $p->student->update(['major'=>'PROFIL TERKINI BERBEDA','class_name'=>'KELAS TERKINI']);
        $query=['q'=>$p->student->nis,'period_id'=>$p->internship_period_id,'major'=>$major];
        $this->get(route('assessments.index',$query))->assertOk()->assertSee($major)->assertSee($p->roster->class_name)
            ->assertDontSee('PROFIL TERKINI BERBEDA')->assertDontSee('KELAS TERKINI')
            ->assertViewHas('placements',fn($rows)=>$rows->total()===1)->assertViewHas('items',fn($rows)=>$rows->total()===1);
        $this->get(route('assessments.index',['placement_id'=>$p->id]))->assertOk()->assertSee($major)->assertDontSee('PROFIL TERKINI BERBEDA');
        $this->get(route('assessments.index',['q'=>$p->student->user->name]))->assertOk()->assertViewHas('items',fn($rows)=>$rows->contains('id',$a->id));
        foreach ([['major'=>'Bukan Jurusan'],['period_id'=>99999],['q'=>'NIS tidak ditemukan']] as $filter) {
            $this->get(route('assessments.index',array_replace($query,$filter)))->assertOk()
                ->assertViewHas('placements',fn($rows)=>$rows->total()===0)->assertViewHas('items',fn($rows)=>$rows->total()===0);
        }
        $this->get(route('assessments.evidence.index',$a))->assertOk()->assertSee($major)->assertDontSee('PROFIL TERKINI BERBEDA');
        $this->get(route('assessments.index'))->assertOk()->assertViewHas('placements',fn($rows)=>$rows->isEmpty());
        $this->getJson(route('assessments.index',['q'=>['bad']]))->assertUnprocessable();
        $this->getJson(route('assessments.index',['page'=>-1]))->assertUnprocessable();
    }
    public function test_evidence_is_private_immutable_and_does_not_change_approved_scores_or_certificate_bytes(): void
    {
        $a=Assessment::whereHas('placement.certificate',fn($q)=>$q->where('status','awaiting_print'))->firstOrFail();
        $before=$a->toArray(); $c=$a->placement->certificate;
        $pdf=$this->get(route('certificates.pdf',$c))->assertOk()->getContent();
        $this->upload($a,['uploaded_by'=>99999,'assessment_revision'=>999])->assertSessionHasNoErrors()->assertRedirect();
        $e=AssessmentEvidence::firstOrFail();
        $this->assertSame(auth()->id(),$e->uploaded_by); $this->assertSame($a->revision,$e->assessment_revision);
        $this->assertSame('approved',$e->assessment_status); $this->assertSame($before,$a->fresh()->toArray());
        $this->assertSame($pdf,$this->get(route('certificates.pdf',$c))->getContent());
        $download=$this->get(route('assessments.evidence.download',$e))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->assertSame($e->sha256,hash('sha256',$download->getContent()));
        $this->assertStringContainsString('attachment;',$download->headers->get('Content-Disposition'));
        $this->get(route('assessments.evidence.index',$a))->assertOk()->assertSee('Lembar nilai industri untuk pengujian.');
        $this->assertDatabaseHas('audit_logs',['action'=>'assessment.evidence_added','subject_id'=>$e->id,'user_id'=>auth()->id()]);
        $this->expectException(\LogicException::class); $e->update(['note'=>'Overwrite']);
    }
    public function test_access_limits_and_student_approved_visibility_remain_enforced(): void
    {
        $a=Assessment::whereHas('placement',fn($q)=>$q->whereNotNull('teacher_id'))->firstOrFail();
        $this->upload($a)->assertSessionHasNoErrors(); $e=AssessmentEvidence::firstOrFail();
        $teacher=$a->placement->teacher;
        $other=Placement::whereNull('teacher_id')->whereHas('assessment')->firstOrFail();
        $this->actingAs($teacher)->get(route('assessments.index',['q'=>$other->student->nis]))->assertOk()->assertViewHas('items',fn($rows)=>$rows->total()===0);
        foreach ([$teacher,$a->placement->student->user] as $user) {
            $this->actingAs($user)->get(route('assessments.evidence.index',$a))->assertForbidden();
            $this->get(route('assessments.evidence.download',$e))->assertForbidden();
            $this->upload($a)->assertForbidden();
        }
        $this->actingAs($a->placement->student->user)->get(route('assessments.index'))->assertOk()
            ->assertViewHas('items',fn($items)=>$items->every(fn($item)=>$item->status==='approved' && $item->placement_id===$a->placement_id));
        $a->update(['status'=>'draft']);
        $this->get(route('assessments.index'))->assertOk()->assertViewHas('items',fn($rows)=>$rows->total()===0);
        $this->actingAs(User::where('role','admin')->firstOrFail())->get(route('assessments.evidence.download',$e))->assertOk();
        $this->upload($a)->assertForbidden();
    }
    public function test_invalid_upload_stale_revision_and_corrupted_file_are_rejected(): void
    {
        $a=Assessment::firstOrFail();
        $this->upload($a,['file'=>UploadedFile::fake()->create('script.html',1,'text/html')])->assertSessionHasErrors('file');
        $this->upload($a,['file'=>UploadedFile::fake()->image('large.png')->size(2049)])->assertSessionHasErrors('file');
        $this->upload($a,['note'=>'short'])->assertSessionHasErrors('note');
        $this->upload($a,['revision'=>$a->revision+1])->assertConflict();
        $this->assertDatabaseCount('assessment_evidence',0);
        $this->upload($a)->assertSessionHasNoErrors(); $e=AssessmentEvidence::firstOrFail();
        DB::table('assessment_evidence')->where('id',$e->id)->update(['file_base64'=>base64_encode('tampered')]);
        $this->get(route('assessments.evidence.download',$e))->assertConflict();
    }
    public function test_evidence_pagination_omits_blob_and_audit_failure_rolls_back_attachment(): void
    {
        $a=Assessment::firstOrFail();
        for($i=0;$i<11;$i++) $this->upload($a)->assertSessionHasNoErrors();
        $this->get(route('assessments.evidence.index',[$a,'page'=>2]))->assertOk()->assertViewHas('items',function($rows) {
            return $rows->total()===11 && $rows->count()===1 && !array_key_exists('file_base64',$rows->first()->getAttributes());
        });
        $this->withoutExceptionHandling();
        AuditLog::creating(function($log) { if($log->action==='assessment.evidence_added') throw new \RuntimeException('Audit failed'); });
        try { $this->upload($a); $this->fail('Expected audit failure'); }
        catch(\RuntimeException $e) { $this->assertSame('Audit failed',$e->getMessage()); }
        finally { AuditLog::flushEventListeners(); }
        $this->assertDatabaseCount('assessment_evidence',11);
    }
    public function test_year_filter_distinguishes_same_student_in_two_periods_and_no_profile_fallback_on_new_assessment(): void
    {
        $a=Assessment::firstOrFail(); $p=$a->placement;
        $r=\App\Models\InternshipRoster::create(['academic_year'=>'2025/2026','nis'=>$p->student->nis,'student_id'=>$p->student_id,
            'name'=>$p->student->user->name,'class_name'=>'XI HISTORIS','major'=>$p->roster->major,'internship_status'=>'internship']);
        $old=Placement::create(['student_id'=>$p->student_id,'internship_period_id'=>$r->internship_period_id,'internship_roster_id'=>$r->id,
            'company_id'=>$p->company_id,'teacher_id'=>$p->teacher_id]);
        $copy=$a->replicate(); $copy->placement_id=$old->id; $copy->save();
        $this->get(route('assessments.index',['q'=>$p->student->nis,'period_id'=>$r->internship_period_id]))->assertOk()
            ->assertSee('XI HISTORIS')->assertViewHas('items',fn($rows)=>$rows->total()===1 && $rows->first()->placement_id===$old->id);
        $blank=Placement::whereDoesntHave('assessment')->firstOrFail();
        $template=\App\Models\AssessmentTemplate::where('major',$blank->roster->major)->firstOrFail();
        DB::table('placements')->where('id',$blank->id)->update(['internship_roster_id'=>null]);
        $this->post(route('assessments.store'),['placement_id'=>$blank->id,'template_id'=>$template->id,'revision'=>0,'intent'=>'draft',
            'industry_assessor'=>'Penilai industri','assessed_on'=>now()->toDateString(),'values'=>['field_1'=>90]])->assertSessionHasErrors('placement_id');
    }
}
