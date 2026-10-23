<?php

namespace Tests\Feature;

use App\Models\{Certificate, Company, InternshipPeriod, InternshipRoster, Placement, Student, User};
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodTagPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-23 12:00:00','Asia/Jakarta'));
    }

    private function coordinator(): User
    {
        return User::create(['name'=>'Koordinator','role'=>'koordinator','email'=>'koord@test.local','password'=>'password123','is_active'=>true]);
    }

    private function roster(string $nis, string $year='2026/2027', string $major='Teknik Komputer dan Jaringan'): InternshipRoster
    {
        $student=app(StudentEnrollment::class)->register(['nis'=>$nis,'name'=>'Siswa '.$nis,'birth_date'=>'2008-11-07','class_name'=>'XII 1','major'=>$major,'academic_year'=>$year]);
        return $student->rosters()->where('academic_year',$year)->firstOrFail();
    }

    private function payload(array $rosters): array
    {
        return ['internship_period_id'=>$rosters[0]->internship_period_id,
            'company_id'=>Company::firstOrCreate(['name'=>'Mitra Uji'])->id,
            'roster_ids'=>array_map(fn($row)=>$row->id,$rosters)];
    }

    public function test_tag_has_no_dates_and_archiving_does_not_change_student_lifecycle(): void
    {
        $this->actingAs($this->coordinator())->post(route('periods.store'),['name'=>'2026/2027'])->assertSessionHasNoErrors();
        $tag=InternshipPeriod::firstOrFail();
        $this->assertNull($tag->starts_at); $this->assertNull($tag->ends_at);
        $this->post(route('periods.store'),['name'=>'2026/2027'])->assertSessionHasErrors('name');
        $this->post(route('periods.store'),['name'=>'2028/2030'])->assertSessionHasErrors('academic_year');
        $roster=$this->roster('TAG001');
        $roster->update(['internship_starts_at'=>'2026-10-01','internship_ends_at'=>'2026-11-01','internship_status'=>'internship']);
        $this->put(route('periods.update',$tag),['name'=>'2026/2027','is_active'=>0])->assertSessionHasNoErrors();
        $this->assertSame('pkl',$roster->fresh()->lifecycleState());
        $this->put(route('periods.update',$tag),['name'=>'2027/2028','is_active'=>1])->assertSessionHasErrors('name');
        $this->put(route('periods.update',$tag),['name'=>'2026/2027','is_active'=>1,'starts_at'=>'2000-01-01'])->assertSessionHasErrors('starts_at');
        $this->post(route('placements.store'),$this->payload([$roster]))->assertSessionHasErrors('internship_period_id');
        $this->assertDatabaseCount('placements',0);
    }

    public function test_bulk_placement_respects_checkbox_exclusions_and_keeps_distinct_student_dates(): void
    {
        $a=$this->roster('BULK01');$b=$this->roster('BULK02','2026/2027','Kuliner');$excluded=$this->roster('BULK03');
        $a->update(['internship_status'=>'internship','internship_starts_at'=>'2026-10-01','internship_ends_at'=>'2026-12-31']);
        $b->update(['internship_status'=>'internship','internship_starts_at'=>'2026-11-01','internship_ends_at'=>'2027-01-31']);
        $this->actingAs($this->coordinator())->post(route('placements.store'),$this->payload([$a,$b]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('placements',2);
        $this->assertDatabaseMissing('placements',['internship_roster_id'=>$excluded->id]);
        $this->assertSame('pkl',$a->placement->lifecycleState());
        $this->assertSame('scheduled',$b->placement->lifecycleState());
        $this->assertNull($a->placement->teacher_id);
        $this->assertSame('2026-12-31',$a->fresh()->internship_ends_at->toDateString());
        $this->get(route('placements.index',['q'=>'BULK01']))->assertOk()->assertSee('Siswa BULK01')->assertSee('Sedang PKL');
        $this->get(route('timeline.index',['teacher_id'=>0]))->assertOk()->assertSee('Siswa BULK01')->assertSee('Siswa BULK02');
        $this->get(route('timeline.index',['placement_id'=>$a->placement->id]))->assertOk()->assertSee('01 Oct')->assertSee('31 Dec');
        $this->get(route('placements.index'))->assertSee('name="roster_ids[]"',false)->assertDontSee('<select name="student_id"',false)->assertSee('Cari nama atau NIS');
    }

    public function test_mixed_year_duplicate_and_opted_out_selections_fail_atomically(): void
    {
        $a=$this->roster('SAFE01');$b=$this->roster('SAFE02','2027/2028');$c=$this->roster('SAFE03');
        $this->actingAs($this->coordinator())->post(route('placements.store'),$this->payload([$a,$b]))->assertSessionHasErrors('roster_ids');
        $this->assertDatabaseCount('placements',0);
        $c->update(['internship_status'=>'not_internship']);
        $this->post(route('placements.store'),$this->payload([$a,$c]))->assertSessionHasErrors('roster_ids');
        $this->assertDatabaseCount('placements',0);
        $this->post(route('placements.store'),$this->payload([$a,$a]))->assertSessionHasErrors('roster_ids.0');
        $this->post(route('placements.store'),$this->payload([$a]))->assertSessionHasNoErrors();
        $c->update(['internship_status'=>'pending']);
        $this->post(route('placements.store'),$this->payload([$c,$a]))->assertSessionHasErrors('roster_ids');
        $this->assertDatabaseCount('placements',1);
        $this->assertSame('unscheduled',$a->placement->lifecycleState());
    }

    public function test_journal_uses_roster_and_ignores_legacy_period_dates_and_manual_placement_status(): void
    {
        $roster=$this->roster('JURNAL01');
        $roster->update(['internship_status'=>'internship','internship_starts_at'=>'2026-10-10','internship_ends_at'=>'2026-11-10']);
        $this->actingAs($this->coordinator())->post(route('placements.store'),$this->payload([$roster]))->assertSessionHasNoErrors();
        $placement=$roster->placement;
        $placement->period->update(['starts_at'=>'2000-01-01','ends_at'=>'2000-01-31']);
        $placement->update(['status'=>'finished']); // Obsolete DB field must have no authority.
        $data=['placement_id'=>$placement->id,'activity_date'=>'2026-10-22','activity'=>'Menguji jadwal siswa','outcome'=>'Berhasil','intent'=>'draft'];
        $this->actingAs($roster->student->user)->post(route('journals.store'),$data)->assertSessionHasNoErrors();
        $this->post(route('journals.store'),array_replace($data,['activity_date'=>'2026-10-01']))->assertSessionHasErrors('activity_date');
        $roster->update(['internship_ends_at'=>'2026-10-22']);
        $this->post(route('journals.store'),$data+['revision'=>1])->assertSessionHasNoErrors();
        $this->assertSame('school',$placement->fresh()->lifecycleState());
    }

    public function test_no_manual_status_updates_and_no_cross_role_assignment(): void
    {
        $roster=$this->roster('ROLE01');$coordinator=$this->coordinator();
        $payload=$this->payload([$roster]);
        $this->actingAs($coordinator)->post(route('placements.store'),$payload+['teacher_id'=>$coordinator->id])->assertSessionHasErrors('teacher_id');
        $this->post(route('placements.store'),$payload)->assertSessionHasNoErrors();
        $this->put(route('placements.update',$roster->placement),['company_id'=>$payload['company_id'],'status'=>'finished'])->assertSessionHasErrors('status');
        $this->actingAs($roster->student->user)->post(route('placements.store'),$payload)->assertForbidden();
        $this->post(route('periods.store'),['name'=>'2028/2029'])->assertForbidden();
    }

    public function test_fresh_demo_has_connected_accounts_tags_placements_and_printable_certificate(): void
    {
        $this->seed();
        $this->assertDatabaseCount('students',30);
        $this->assertSame(30,InternshipRoster::whereNotNull('student_id')->whereNotNull('internship_period_id')->count());
        $this->assertDatabaseCount('internship_periods',1);
        $this->assertDatabaseCount('placements',18);
        $this->assertSame(18,Placement::whereNotNull('internship_roster_id')->count());
        $this->assertSame(6,InternshipRoster::whereDoesntHave('placement')->where('internship_status','pending')->count());
        $this->assertNull(InternshipPeriod::first()->starts_at);
        $this->assertSame(8,InternshipRoster::distinct()->count('major'));
        $coordinator=User::where('email','koordinator@simmag.test')->firstOrFail();
        $certificate=Certificate::where('status','awaiting_print')->firstOrFail();
        $this->actingAs($coordinator)->get(route('certificates.pdf',$certificate))->assertOk()->assertHeader('content-type','application/pdf');
        $this->get(route('timeline.index',['teacher_id'=>0]))->assertOk();
        $this->get(route('periods.index'))->assertOk()->assertDontSee('name="starts_at"',false);
    }
}
