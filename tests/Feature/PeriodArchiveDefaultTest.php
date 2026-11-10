<?php

namespace Tests\Feature;

use App\Models\{InternshipPeriod, InternshipRoster, Placement, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodArchiveDefaultTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;
    private InternshipPeriod $active;
    private InternshipPeriod $archived;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->coordinator=User::where('role','koordinator')->firstOrFail();
        $this->active=InternshipPeriod::where('is_active',true)->forSelection()->firstOrFail();

        $source=Placement::with('roster')->whereNotNull('internship_roster_id')->firstOrFail();
        $this->archived=InternshipPeriod::create([
            'name'=>'2027/2028','academic_year'=>'2027/2028','is_active'=>false,
        ]);
        $roster=InternshipRoster::create([
            'academic_year'=>$this->archived->academic_year,
            'nis'=>$source->roster->nis,
            'nisn'=>$source->roster->nisn,
            'name'=>$source->roster->name,
            'email'=>$source->roster->email,
            'class_name'=>$source->roster->class_name,
            'major'=>$source->roster->major,
            'internship_status'=>'internship',
            'internship_starts_at'=>'2027-07-01',
            'internship_ends_at'=>'2027-12-31',
            'student_id'=>$source->student_id,
        ]);
        Placement::create([
            'student_id'=>$source->student_id,
            'company_id'=>$source->company_id,
            'internship_period_id'=>$this->archived->id,
            'internship_roster_id'=>$roster->id,
            'teacher_id'=>$source->teacher_id,
        ]);
        $this->actingAs($this->coordinator);
    }

    public function test_every_period_menu_defaults_to_latest_active_tag_and_keeps_archive_selectable(): void
    {
        $this->assertSame($this->active->id,InternshipPeriod::defaultFrom(InternshipPeriod::forSelection()->get())?->id);

        $this->get(route('dashboard'))->assertOk()->assertViewHas('chosen',$this->active->id)->assertSee('2027/2028 (arsip)');
        $this->get(route('timeline.index'))->assertOk()->assertViewHas('chosen',$this->active->id)->assertSee('2027/2028 (arsip)');
        $this->get(route('reports.index'))->assertOk()->assertViewHas('chosen',$this->active->id)->assertSee('2027/2028 (arsip)');
        $this->get(route('placements.index'))->assertOk()->assertViewHas('period',fn($period)=>$period?->id===$this->active->id)->assertSee('2027/2028 (arsip)');
        $this->get(route('monitoring.index'))->assertOk()->assertViewHas('periodId',$this->active->id)->assertSee('2027/2028 (arsip)');
        $this->get(route('assessments.index'))->assertOk()->assertViewHas('periodId',$this->active->id)->assertSee('2027/2028 (arsip)');
        $this->get(route('certificates.index'))->assertOk()->assertViewHas('periodId',$this->active->id)->assertSee('2027/2028 (arsip)');
        $this->get(route('journals.index'))->assertOk()->assertViewHas('periodId',$this->active->id)->assertSee('2027/2028 (arsip)');
        $this->get(route('internships.index'))->assertOk()->assertViewHas('academicYear',$this->active->academic_year)->assertSee('2027/2028 (arsip)');
        $this->get(route('students.index'))->assertOk()->assertViewHas('academicYear',$this->active->academic_year);
    }

    public function test_explicit_archive_selection_is_preserved_on_every_period_menu(): void
    {
        $id=$this->archived->id;
        $year=$this->archived->academic_year;

        $this->get(route('dashboard',['period_id'=>$id]))->assertOk()->assertViewHas('chosen',$id);
        $this->get(route('timeline.index',['period_id'=>$id]))->assertOk()->assertViewHas('chosen',$id);
        $this->get(route('reports.index',['period_id'=>$id]))->assertOk()->assertViewHas('chosen',$id);
        $this->get(route('placements.index',['period_id'=>$id]))->assertOk()->assertViewHas('period',fn($period)=>$period?->id===$id);
        $this->get(route('monitoring.index',['period_id'=>$id]))->assertOk()->assertViewHas('periodId',$id);
        $this->get(route('assessments.index',['period_id'=>$id]))->assertOk()->assertViewHas('periodId',$id);
        $this->get(route('certificates.index',['period_id'=>$id]))->assertOk()->assertViewHas('periodId',$id);
        $this->get(route('journals.index',['period_id'=>$id]))->assertOk()->assertViewHas('periodId',$id);
        $this->get(route('internships.index',['academic_year'=>$year]))->assertOk()->assertViewHas('academicYear',$year);
        $this->get(route('students.index',['academic_year'=>$year]))->assertOk()->assertViewHas('academicYear',$year);
    }

    public function test_no_archived_tag_is_silently_selected_when_no_active_tag_exists(): void
    {
        InternshipPeriod::query()->update(['is_active'=>false]);
        $this->assertNull(InternshipPeriod::defaultFrom(InternshipPeriod::forSelection()->get()));
        $this->assertNull(InternshipPeriod::defaultAcademicYear());

        $this->get(route('dashboard'))->assertOk()->assertViewHas('chosen',0)
            ->assertSee('Tidak ada tag aktif — pilih arsip bila diperlukan');
        $this->get(route('placements.index'))->assertOk()->assertViewHas('period',null)
            ->assertSee('Tidak ada tag aktif — pilih arsip bila diperlukan');
        $this->get(route('internships.index'))->assertOk()->assertViewHas('academicYear','')
            ->assertSee('Tidak ada tag aktif — pilih arsip bila diperlukan');
        $this->get(route('students.index'))->assertOk()->assertViewHas('academicYear','');
    }
}
