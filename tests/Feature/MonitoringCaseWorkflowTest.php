<?php
namespace Tests\Feature;

use App\Models\{AuditLog, MonitoringRecord, Placement, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class MonitoringCaseWorkflowTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local'); $this->seed();
        $this->actingAs(User::where('role','koordinator')->firstOrFail());
    }
    private function createCase(?Placement $p=null): MonitoringRecord
    {
        $p??=Placement::whereNull('teacher_id')->firstOrFail();
        $this->post(route('monitoring.store'),['placement_id'=>$p->id,'visit_date'=>now('Asia/Jakarta')->toDateString(),
            'notes'=>'Catatan awal kasus teknis untuk pengujian.','follow_up'=>'Catatan tindak lanjut awal tetap utuh.','category'=>'technical',
            'case_status'=>'resolved','coordinator_id'=>User::where('role','guru')->firstOrFail()->id])->assertSessionHasNoErrors();
        return MonitoringRecord::latest('id')->firstOrFail();
    }
    private function payload(MonitoringRecord $m,array $extra=[]): array
    {
        return array_replace(['revision'=>$m->revision,'case_status'=>'in_progress','category'=>'technical',
            'coordinator_id'=>User::where('role','koordinator')->firstOrFail()->id,'teacher_id'=>null,
            'note'=>'Menghubungi pihak industri untuk penanganan kendala.'],$extra);
    }
    public function test_coordinators_assign_close_and_reopen_with_immutable_original_notes_and_stale_tab_protection(): void
    {
        $actor=auth()->user(); $m=$this->createCase();
        $this->assertSame('open',$m->case_status); $this->assertSame($actor->id,$m->coordinator_id);
        $original=$m->only(['notes','follow_up','created_by','visit_date']);
        $second=User::where('role','koordinator')->where('id','!=',$actor->id)->firstOrFail();
        $this->put(route('monitoring.update',$m),$this->payload($m,['coordinator_id'=>$second->id]))->assertSessionHasNoErrors()->assertRedirect(route('monitoring.show',$m));
        $this->assertSame($second->id,$m->fresh()->coordinator_id);
        $this->actingAs($second)->put(route('monitoring.update',$m),$this->payload($m,['case_status'=>'resolved']))->assertConflict();
        $this->assertSame(1,$m->updates()->count());
        $m->refresh();
        $this->put(route('monitoring.update',$m),$this->payload($m,['case_status'=>'resolved','coordinator_id'=>$second->id]))->assertSessionHasNoErrors();
        $this->assertNotNull($m->fresh()->resolved_at);
        $m->refresh();
        $this->put(route('monitoring.update',$m),$this->payload($m,['case_status'=>'open','note'=>'Membuka kembali karena kendala muncul lagi.']))->assertSessionHasNoErrors();
        $this->assertNull($m->fresh()->resolved_at); $this->assertSame(4,$m->fresh()->revision);
        $this->assertEquals($original,$m->fresh()->only(array_keys($original)));
        $events=$m->updates()->orderBy('id')->get();
        $this->assertSame([$actor->id,$second->id,$second->id],$events->pluck('actor_id')->all());
        $this->assertSame('resolved',$events[2]->before_state['case_status']);
        $this->assertDatabaseHas('audit_logs',['action'=>'monitoring.updated','subject_id'=>$m->id,'user_id'=>$second->id]);
        $this->get(route('monitoring.show',$m))->assertOk()->assertSee('Membuka kembali karena kendala muncul lagi.')->assertSee('Catatan tindak lanjut awal tetap utuh.');
    }
    public function test_teacher_is_optional_and_only_placement_teacher_can_be_assigned_without_new_access(): void
    {
        $p=Placement::whereNotNull('teacher_id')->firstOrFail(); $m=$this->createCase($p);
        $other=User::where('role','guru')->where('id','!=',$p->teacher_id)->firstOrFail();
        $this->put(route('monitoring.update',$m),$this->payload($m,['teacher_id'=>$other->id]))->assertSessionHasErrors('teacher_id');
        $this->put(route('monitoring.update',$m),$this->payload($m,['teacher_id'=>$p->teacher_id]))->assertSessionHasNoErrors();
        $m->refresh();
        $this->put(route('monitoring.update',$m),$this->payload($m,['teacher_id'=>null]))->assertSessionHasNoErrors();
        $this->assertNull($m->fresh()->teacher_id);
        $this->actingAs($p->teacher)->get(route('monitoring.show',$m))->assertOk()->assertDontSee('Simpan Tindak Lanjut');
        $this->put(route('monitoring.update',$m),$this->payload($m))->assertForbidden();
        $this->actingAs($other)->get(route('monitoring.show',$m))->assertNotFound();
        $this->actingAs($p->student->user)->get(route('monitoring.show',$m))->assertForbidden();
        $this->actingAs(User::where('role','admin')->firstOrFail())->get(route('monitoring.show',$m))->assertOk()->assertDontSee('Simpan Tindak Lanjut');
        $this->put(route('monitoring.update',$m),$this->payload($m))->assertForbidden();
    }
    public function test_invalid_states_owners_and_notes_are_rejected_and_filters_do_not_hide_student_candidates(): void
    {
        $m=$this->createCase();
        $inactive=User::where('role','koordinator')->where('id','!=',auth()->id())->firstOrFail(); $inactive->update(['is_active'=>false]);
        foreach ([['case_status'=>'unclassified'],['category'=>'invalid'],['note'=>'short'],['coordinator_id'=>$inactive->id],
            ['coordinator_id'=>User::where('role','guru')->firstOrFail()->id]] as $invalid) {
            $this->put(route('monitoring.update',$m),$this->payload($m,$invalid))->assertSessionHasErrors(array_key_first($invalid));
        }
        $this->assertSame(0,$m->updates()->count());
        $this->get(route('monitoring.index',['q'=>$m->placement->student->nis,'case_status'=>'resolved','category'=>'technical']))->assertOk()
            ->assertViewHas('items',fn($items)=>$items->total()===0)->assertViewHas('candidates',fn($rows)=>$rows->total()===1);
        $this->get(route('monitoring.index',['case_status'=>'open','category'=>'technical']))->assertOk()->assertViewHas('items',fn($items)=>$items->total()===1);
        $this->getJson(route('monitoring.index',['case_status'=>'unknown']))->assertUnprocessable();
    }
    public function test_legacy_migration_does_not_guess_status_owner_or_erase_notes(): void
    {
        $migration=require database_path('migrations/2026_10_24_000005_add_monitoring_case_workflow.php');
        $migration->down();
        $p=Placement::whereNull('teacher_id')->firstOrFail();
        $id=DB::table('monitoring_records')->insertGetId(['placement_id'=>$p->id,'teacher_id'=>null,'created_by'=>auth()->id(),
            'visit_date'=>'2026-10-01','notes'=>'Catatan lama yang perlu diklasifikasi.','follow_up'=>'Teks lama tidak dihapus.']);
        $migration->up();
        $m=MonitoringRecord::findOrFail($id);
        $this->assertSame('unclassified',$m->case_status); $this->assertNull($m->coordinator_id); $this->assertNull($m->resolved_at);
        $this->assertSame('Teks lama tidak dihapus.',$m->follow_up);
        $this->get(route('monitoring.show',$m))->assertOk()->assertSee('Catatan lama belum ditentukan statusnya.');
        $this->put(route('monitoring.update',$m),$this->payload($m,['case_status'=>'resolved']))->assertSessionHasNoErrors();
        $this->assertSame('unclassified',$m->updates()->firstOrFail()->before_state['case_status']);
    }
    public function test_update_and_history_roll_back_together_if_audit_fails(): void
    {
        $m=$this->createCase(); $this->withoutExceptionHandling();
        AuditLog::creating(function($log) { if($log->action==='monitoring.updated') throw new \RuntimeException('Simulated audit failure'); });
        try {
            $this->put(route('monitoring.update',$m),$this->payload($m)); $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) { $this->assertSame('Simulated audit failure',$e->getMessage()); }
        finally { AuditLog::flushEventListeners(); }
        $this->assertSame(1,$m->fresh()->revision); $this->assertSame('open',$m->fresh()->case_status);
        $this->assertSame(0,$m->updates()->count());
    }
}
