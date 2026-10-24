<?php
namespace Tests\Feature;

use App\Models\{Certificate, CertificateDocument, Placement, User};
use App\Support\{CertificateArchive, MainMenuAudit};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class CertificateArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 10:00:00','Asia/Jakarta'));
        Storage::fake('local');
        $this->seed();
        $this->actingAs(User::where('email','koordinator@simmag.test')->firstOrFail());
    }

    private function candidate(): Placement
    {
        return Placement::whereHas('assessment',fn($q)=>$q->where('status','approved'))->whereDoesntHave('certificate')->firstOrFail();
    }

    public function test_new_proposal_validates_roster_and_scores_and_rolls_back_bad_layout(): void
    {
        $p=$this->candidate();
        $p->roster->update(['internship_ends_at'=>null]);
        $this->post(route('certificates.create'),['placement_id'=>$p->id])->assertSessionHasErrors('certificate');
        $p->roster->update(['internship_ends_at'=>'2026-10-01']);
        $p->assessment->update(['final_score'=>null]);
        $this->post(route('certificates.create'),['placement_id'=>$p->id])->assertSessionHasErrors('certificate');
        $p->assessment->update(['final_score'=>88]);
        $snapshot=$p->assessment->template_snapshot;
        $snapshot['certificate_statement']=str_repeat('Narasi yang sangat panjang untuk menguji ukuran dokumen. ',200);
        $p->assessment->update(['template_snapshot'=>$snapshot]);
        $this->post(route('certificates.create'),['placement_id'=>$p->id])->assertSessionHasErrors('certificate');
        $this->assertDatabaseMissing('certificates',['placement_id'=>$p->id]);
        $this->assertDatabaseCount('certificate_documents',2);
    }

    public function test_approval_rechecks_sources_and_requires_new_version_for_changes(): void
    {
        $c=Certificate::where('status','awaiting_approval')->firstOrFail();
        $original=$c->documents()->firstOrFail()->snapshot;
        $c->placement->company->update(['name'=>'Perusahaan dikoreksi']);
        $this->put(route('certificates.approve',$c),['decision'=>'approved','version'=>1])->assertSessionHasErrors('certificate');
        $this->assertSame('awaiting_approval',$c->fresh()->status);
        $this->assertSame($original,$c->documents()->firstOrFail()->snapshot);
        $this->put(route('certificates.resubmit',$c),['version'=>1,'note'=>'Perbaikan nama perusahaan sesuai dokumen industri.'])->assertSessionHasNoErrors();
        $this->assertSame(2,$c->fresh()->version);
        $this->put(route('certificates.approve',$c),['decision'=>'approved','version'=>1])->assertConflict();
        $this->put(route('certificates.approve',$c),['decision'=>'approved','version'=>2])->assertSessionHasNoErrors();
        $this->assertSame('awaiting_print',$c->fresh()->status);
        $this->assertDatabaseHas('certificate_approval_events',['certificate_id'=>$c->id,'version'=>2,'decision'=>'approved']);
    }

    public function test_pdf_bytes_are_frozen_even_when_all_live_sources_change_or_become_incomplete(): void
    {
        $c=Certificate::where('status','awaiting_print')->firstOrFail();
        $document=$c->documents()->firstOrFail();
        $before=$this->get(route('certificates.pdf',$c))->assertOk()->getContent();
        $p=$c->placement;
        $p->student->user->update(['name'=>'NAMA BARU']);
        $p->student->update(['nis'=>'NISBARU','class_name'=>'KELAS BARU','major'=>'JURUSAN BARU']);
        $p->company->update(['name'=>'INDUSTRI BARU']);
        $p->period->update(['name'=>'TAG BARU']);
        $p->roster->update(['internship_starts_at'=>null,'internship_ends_at'=>null]);
        $p->assessment->update(['status'=>'draft','final_score'=>1,'field_values'=>[]]);
        $this->assertSame($before,$this->get(route('certificates.pdf',$c))->assertOk()->getContent());
        $this->assertSame($document->sha256,hash('sha256',$before));
        $this->get(route('certificates.show',$c))->assertOk()->assertSee($document->snapshot['student']['name'])->assertDontSee('NAMA BARU');
    }

    public function test_revision_preserves_issued_archive_and_restarts_distribution_with_version_guard(): void
    {
        $c=Certificate::where('status','awaiting_print')->firstOrFail();
        $original=$this->get(route('certificates.pdf',$c))->assertOk()->getContent();
        $c->placement->student->user->update(['name'=>'Nama yang sudah dikoreksi']);
        $this->put(route('certificates.resubmit',$c),['version'=>1,'note'=>'Koreksi identitas dengan persetujuan koordinator.'])->assertSessionHasNoErrors();
        $this->get(route('certificates.pdf',$c))->assertForbidden();
        $this->assertSame($original,$this->get(route('certificates.pdf',['certificate'=>$c,'version'=>1]))->assertOk()->getContent());
        $this->put(route('certificates.resubmit',$c),['version'=>1,'note'=>'Tab kedua mengirim ulang.'])->assertConflict();
        $this->put(route('certificates.approve',$c),['version'=>2,'decision'=>'approved'])->assertSessionHasNoErrors();
        $this->assertNotSame($original,$this->get(route('certificates.pdf',$c))->assertOk()->getContent());
        $this->put(route('certificates.movement',$c),['version'=>1,'expected_status'=>'awaiting_print','recipient'=>'TU'])->assertUnprocessable();
        $this->put(route('certificates.movement',$c),['version'=>2,'expected_status'=>'awaiting_print','recipient'=>'TU'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('certificate_movements',['certificate_id'=>$c->id,'version'=>2]);
        $this->assertSame(2,$c->documents()->count());
    }

    public function test_legacy_document_is_not_silently_rebuilt_and_requires_explicit_coordinator_note(): void
    {
        $p=$this->candidate();
        $c=Certificate::create(['placement_id'=>$p->id,'number'=>'ARSIP-LAMA','status'=>'awaiting_print','issued_at'=>now()->subDay()]);
        $c->refresh();
        $c->approvals()->create(['party'=>'coordinator','status'=>'approved','approver_id'=>auth()->id(),'decided_at'=>now()->subDay()]);
        $this->get(route('certificates.pdf',$c))->assertConflict();
        $this->assertSame(1,app(MainMenuAudit::class)->inspect('2026/2027')['metrics']['issued_certificates_live_data']);
        $this->put(route('certificates.resubmit',$c),['version'=>1,'note'=>'Coba revisi sebelum arsip.'])->assertSessionHasErrors('certificate');
        $this->post(route('certificates.archive-legacy',$c),['version'=>1])->assertSessionHasErrors('note');
        $this->post(route('certificates.archive-legacy',$c),['version'=>1,'note'=>'Diperiksa dengan cetakan lama; rekonstruksi dari data terkini.'])->assertSessionHasNoErrors();
        $document=$c->documents()->firstOrFail();
        $this->assertSame('legacy_reconstructed',$document->source);
        $this->assertSame($c->issued_at->toDateTimeString(),$document->issued_at->toDateTimeString());
        $this->get(route('certificates.pdf',$c))->assertOk();
        $this->post(route('certificates.archive-legacy',$c),['version'=>1,'note'=>'Jangan boleh menimpa dokumen yang ada.'])->assertConflict();
        $this->assertSame(1,$c->documents()->count());
        $this->get(route('certificates.show',$c))->assertSee('Rekonstruksi dokumen lama');
    }

    public function test_permissions_and_search_are_scoped_without_student_dropdown(): void
    {
        $p=$this->candidate();
        $this->get(route('certificates.index'))->assertOk()->assertViewHas('placements',fn($rows)=>$rows->isEmpty())->assertDontSee('<select name="placement_id"',false);
        $this->get(route('certificates.index',['q'=>$p->student->nis,'period_id'=>$p->internship_period_id,'major'=>$p->roster->major]))
            ->assertOk()->assertSee($p->student->user->name)->assertSee('Lengkapi &amp; Ajukan Sertifikat',false);
        $c=Certificate::where('status','awaiting_print')->firstOrFail();
        $other=User::where('role','siswa')->where('id','!=',$c->placement->student->user_id)->firstOrFail();
        $this->actingAs($other)->get(route('certificates.pdf',$c))->assertNotFound();
        $this->get(route('certificates.index',['q'=>$p->student->nis]))->assertOk()->assertViewHas('items',fn($rows)=>$rows->isEmpty());
        $this->actingAs($c->placement->student->user)->get(route('certificates.pdf',$c))->assertOk();
        $this->post(route('certificates.archive-legacy',$c),['version'=>1,'note'=>'Tidak punya akses industri.'])->assertForbidden();
        $this->put(route('certificates.resubmit',$c),['version'=>1,'note'=>'Tidak boleh revisi siswa.'])->assertForbidden();
    }

    public function test_corrupted_archived_pdf_fails_closed_instead_of_regenerating(): void
    {
        $c=Certificate::where('status','awaiting_print')->firstOrFail();
        DB::table('certificate_documents')->where('certificate_id',$c->id)->update(['pdf_base64'=>base64_encode('CORRUPT')]);
        $this->get(route('certificates.pdf',$c))->assertConflict();
    }

    public function test_issued_archive_model_cannot_be_overwritten(): void
    {
        $document=CertificateDocument::whereNotNull('issued_at')->firstOrFail();
        $this->expectException(\LogicException::class);
        $document->update(['rendered_html'=>'Ditimpa']);
    }

    public function test_source_comparison_ignores_json_key_order_but_preserves_identity_strings(): void
    {
        $archive=app(CertificateArchive::class);
        $stored=['student'=>['nis'=>'001','name'=>'Siswa'],'score'=>88.0];
        $reordered=['score'=>88,'student'=>['name'=>'Siswa','nis'=>'001']];
        $this->assertTrue($archive->sourcesMatch($stored,$reordered));
        $reordered['student']['nis']='1';
        $this->assertFalse($archive->sourcesMatch($stored,$reordered));
    }
}
