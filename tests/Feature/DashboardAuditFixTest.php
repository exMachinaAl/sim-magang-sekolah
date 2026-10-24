<?php

namespace Tests\Feature;

use App\Models\{Certificate, InternshipPeriod, InternshipRoster, Journal, Placement, User};
use App\Support\Access;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardAuditFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->seed();
    }

    public function test_school_totals_include_unplaced_students_and_historical_majors(): void
    {
        $roster=InternshipRoster::firstOrFail();
        $roster->student->update(['major'=>'Profil baru bukan jurusan roster']);
        foreach (['admin','koordinator'] as $role) {
            $response=$this->actingAs(User::where('role',$role)->firstOrFail())->get(route('dashboard'))
                ->assertOk()->assertViewHas('studentsCount',30)->assertViewHas('rosterCount',30)
                ->assertViewHas('placementsCount',18)->assertViewHas('companiesCount',3)
                ->assertDontSee('Profil baru bukan jurusan roster')->assertSee('Status PKL Sekolah');
            $this->assertSame(array_fill_keys(array_keys(InternshipRoster::lifecycleLabels()),6),$response->viewData('phaseTotals'));
            $this->assertSame(8,$response->viewData('majorSummaries')->count());
        }
    }

    public function test_year_filter_scopes_all_counters_lists_and_unique_students(): void
    {
        $source=Placement::whereNotNull('teacher_id')->firstOrFail();
        $roster=$source->roster->replicate();
        $roster->academic_year='2025/2026';
        $roster->class_name='XI Historis';
        $roster->major='Jurusan Historis';
        $roster->save();
        $older=$source->replicate();
        $older->internship_period_id=$roster->internship_period_id;
        $older->internship_roster_id=$roster->id;
        $older->save();
        Journal::create(['placement_id'=>$older->id,'activity_date'=>'2025-10-01','activity'=>'Jurnal tahun lama','outcome'=>'Selesai','status'=>'submitted']);
        Certificate::create(['placement_id'=>$older->id,'number'=>'TAHUN-LAMA','status'=>'awaiting_approval']);
        $this->actingAs(User::where('role','koordinator')->firstOrFail());
        $this->get(route('dashboard'))->assertOk()->assertViewHas('studentsCount',30)
            ->assertViewHas('placementsCount',18)->assertDontSee('TAHUN-LAMA')->assertDontSee('Jurusan Historis');
        $response=$this->get(route('dashboard',['period_id'=>$roster->internship_period_id]))->assertOk()
            ->assertViewHas('studentsCount',1)->assertViewHas('rosterCount',1)->assertViewHas('placementsCount',1)
            ->assertViewHas('pendingJournals',1)->assertViewHas('pendingAssessments',0)
            ->assertViewHas('certificatesCount',1)->assertViewHas('companiesCount',1)
            ->assertSee('TAHUN-LAMA')->assertSee('Jurusan Historis');
        $this->assertSame([$older->id],$response->viewData('latestJournals')->modelKeys());
        foreach ([$source->teacher,$source->student->user] as $user) {
            $response=$this->actingAs($user)->get(route('dashboard',['period_id'=>$roster->internship_period_id]))
                ->assertOk()->assertViewHas('studentsCount',1)->assertViewHas('placementsCount',1);
            if ($user->isRole('siswa')) {
                $this->assertSame([$roster->id],$response->viewData('studentRosters')->modelKeys());
                $response->assertSee('XI Historis')->assertDontSee('data-dashboard-phases',false);
            }
        }
    }

    public function test_role_scope_empty_data_and_invalid_year_selection(): void
    {
        $teacher=User::where('role','guru')->firstOrFail();
        $response=$this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('Status Siswa Dampingan');
        $this->assertSame(Access::rosters($teacher)->count(),$response->viewData('rosterCount'));
        $this->assertSame(Access::placements($teacher)->distinct()->count('student_id'),$response->viewData('studentsCount'));
        $private=InternshipRoster::whereDoesntHave('placement')->firstOrFail();
        $this->actingAs($private->student->user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('studentsCount',1)->assertViewHas('placementsCount',0)->assertViewHas('rosterCount',1);
        $empty=User::create(['name'=>'Guru kosong','email'=>'empty-dashboard@test.local','role'=>'guru','password'=>'password123','is_active'=>true]);
        $this->actingAs($empty)->get(route('dashboard'))->assertOk()->assertViewHas('studentsCount',0)
            ->assertViewHas('rosterCount',0)->assertViewHas('chosen',0)->assertSee('Belum ada data tahun ajaran');
        $this->get(route('dashboard',['period_id'=>$private->internship_period_id]))->assertNotFound();
        $this->get(route('dashboard',['period_id'=>99999]))->assertNotFound();
        $this->getJson(route('dashboard',['period_id'=>['invalid']]))->assertUnprocessable();
        $this->getJson(route('dashboard',['period_id'=>0]))->assertUnprocessable();
    }

    public function test_date_boundaries_and_missing_or_invalid_dates_follow_control_pkl_in_jakarta(): void
    {
        // UTC is still yesterday; the dashboard must already use 25 October WIB.
        $this->travelTo(Carbon::parse('2026-10-24 17:01:00','UTC'));
        $rosters=InternshipRoster::orderBy('id')->take(6)->get();
        $schedules=[
            ['internship','2026-10-25','2026-10-25','pkl'],
            ['internship','2026-10-01','2026-10-24','school'],
            ['internship','2026-10-26','2026-10-30','scheduled'],
            ['pending',null,null,'unscheduled'],
            ['internship','2026-10-28','2026-10-25','unscheduled'],
            ['not_internship','2026-10-01','2026-10-30','not_internship'],
        ];
        foreach ($rosters as $i=>$roster) {
            [$status,$start,$end,$state]=$schedules[$i];
            $roster->update(['internship_status'=>$status,'internship_starts_at'=>$start,'internship_ends_at'=>$end]);
            $this->assertSame($state,$roster->lifecycleState(now('Asia/Jakarta')));
        }
        $response=$this->actingAs(User::where('role','koordinator')->firstOrFail())->get(route('dashboard'))->assertOk()->assertSee('25/10/2026');
        $expected=array_fill_keys(array_keys(InternshipRoster::lifecycleLabels()),0);
        foreach (InternshipRoster::all() as $row) $expected[$row->lifecycleState(now('Asia/Jakarta'))]++;
        $this->assertSame($expected,$response->viewData('phaseTotals'));
        $this->assertSame(30,array_sum($response->viewData('phaseTotals')));
    }
}
