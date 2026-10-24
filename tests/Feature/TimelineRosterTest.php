<?php
namespace Tests\Feature;

use App\Models\{InternshipPeriod, InternshipRoster, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class TimelineRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 12:00:00','Asia/Jakarta'));
        Storage::fake('local'); $this->seed();
        $this->actingAs(User::where('role','koordinator')->firstOrFail());
    }

    public function test_root_groups_all_thirty_rosters_without_rendering_every_student(): void
    {
        $response=$this->get(route('timeline.index'))->assertOk();
        $groups=$response->viewData('majorDirectory');
        $this->assertSame(8,$groups->total());
        $this->assertSame(30,(int)$groups->sum('total'));
        foreach (array_keys(InternshipRoster::lifecycleLabels()) as $state) $this->assertSame(6,(int)$groups->sum($state.'_count'));
        $this->assertCount(0,$response->viewData('studentRows'));
        foreach (InternshipRoster::all() as $roster) $response->assertDontSee($roster->name);
        $this->assertSame(8,substr_count($response->getContent(),'data-timeline-major='));
    }

    public function test_unplaced_rosters_are_searchable_and_detail_uses_historical_class_and_major(): void
    {
        $roster=InternshipRoster::whereDoesntHave('placement')->firstOrFail();
        $roster->student->update(['class_name'=>'KELAS PROFIL BARU','major'=>'JURUSAN PROFIL BARU']);
        $this->get(route('timeline.index',['q'=>$roster->nis]))->assertOk()->assertSee($roster->name)->assertSee('Belum ditempatkan');
        $this->get(route('timeline.index',['roster_id'=>$roster->id]))->assertOk()->assertSee($roster->class_name)->assertSee($roster->major)
            ->assertDontSee('KELAS PROFIL BARU')->assertDontSee('JURUSAN PROFIL BARU')->assertDontSee('Buka jurnal, validasi industri');
        $this->actingAs($roster->student->user)->get(route('timeline.index'))->assertOk()
            ->assertViewHas('selectedStudentRow',fn($row)=>$row['roster']->id===$roster->id);
    }

    public function test_teacher_and_student_cannot_open_other_rosters_or_years(): void
    {
        $own=InternshipRoster::whereHas('placement',fn($q)=>$q->whereNotNull('teacher_id'))->firstOrFail();
        $teacher=$own->placement->teacher;
        $other=InternshipRoster::whereDoesntHave('placement')->firstOrFail();
        $this->actingAs($teacher)->get(route('timeline.index'))->assertOk()
            ->assertViewHas('studentRows',fn($rows)=>$rows->every(fn($r)=>$r->placement->teacher_id===$teacher->id));
        $this->get(route('timeline.index',['roster_id'=>$other->id]))->assertNotFound();
        $this->get(route('timeline.index',['q'=>$other->nis]))->assertOk()->assertDontSee($other->name);
        $this->get(route('timeline.index',['teacher_id'=>0]))->assertNotFound();
        $this->actingAs($own->student->user)->get(route('timeline.index',['roster_id'=>$other->id]))->assertNotFound();
        $foreign=InternshipPeriod::forAcademicYear('2028/2029');
        $this->get(route('timeline.index',['period_id'=>$foreign->id]))->assertNotFound();
    }

    public function test_all_date_boundaries_and_exceptions_match_sql_counts(): void
    {
        $roster=InternshipRoster::firstOrFail();
        foreach ([
            ['internship','2026-10-25','2026-11-01','scheduled'],
            ['internship','2026-10-24','2026-10-24','pkl'],
            ['internship','2026-10-01','2026-10-24','pkl'],
            ['internship','2026-10-01','2026-10-23','school'],
            ['pending',null,null,'unscheduled'],
            ['internship','2026-10-25','2026-10-23','unscheduled'],
            ['not_internship','2026-10-01','2026-10-23','not_internship'],
        ] as [$status,$start,$end,$expected]) {
            $roster->update(['internship_status'=>$status,'internship_starts_at'=>$start,'internship_ends_at'=>$end]);
            $detail=$this->get(route('timeline.index',['roster_id'=>$roster->id]))->assertOk();
            $this->assertSame($expected,$detail->viewData('selectedStudentRow')['schedule']['state']);
            if ($expected==='not_internship') $detail->assertDontSee('Sudah dimulai')->assertDontSee('Sudah masuk');
            $groups=$this->get(route('timeline.index'))->assertOk()->viewData('majorDirectory');
            $group=$groups->firstWhere('major',$roster->major);
            $counts=InternshipRoster::where('major',$roster->major)->get()->countBy(fn($r)=>$r->lifecycleState());
            foreach (array_keys(InternshipRoster::lifecycleLabels()) as $state) $this->assertSame($counts->get($state,0),(int)$group->{$state.'_count'});
        }
        $roster->update(['internship_status'=>'internship','internship_starts_at'=>'2026-10-24','internship_ends_at'=>'2026-10-24']);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 23:59:59','Asia/Jakarta'));
        $this->get(route('timeline.index',['roster_id'=>$roster->id]))->assertViewHas('selectedStudentRow',fn($r)=>$r['schedule']['state']==='pkl');
        $this->travel(1)->seconds();
        $this->get(route('timeline.index',['roster_id'=>$roster->id]))->assertViewHas('selectedStudentRow',fn($r)=>$r['schedule']['state']==='school');
    }

    public function test_search_and_pagination_are_done_in_database_even_for_large_roster(): void
    {
        $first=InternshipRoster::firstOrFail(); $rows=[];
        for($i=0;$i<240;$i++) $rows[]=['academic_year'=>$first->academic_year,'internship_period_id'=>$first->internship_period_id,
            'nis'=>'LOAD'.str_pad($i,4,'0',STR_PAD_LEFT),'name'=>'Siswa Beban '.str_pad($i,4,'0',STR_PAD_LEFT),
            'class_name'=>'XII','major'=>$first->major,'internship_status'=>'pending','created_at'=>now(),'updated_at'=>now()];
        DB::table('internship_rosters')->insert($rows);
        $queries=[]; DB::listen(function($query)use(&$queries){$queries[]=$query->sql;});
        $response=$this->get(route('timeline.index',['q'=>'Siswa Beban','students_page'=>2]))->assertOk();
        $page=$response->viewData('studentRows');
        $this->assertSame(240,$page->total()); $this->assertCount(20,$page);
        $this->assertSame('LOAD0020',$page->first()->nis);
        $this->assertTrue(collect($queries)->contains(fn($sql)=>str_contains(strtolower($sql),'limit 20 offset 20')));
        $this->assertTrue(collect($queries)->contains(fn($sql)=>str_contains(strtolower($sql),'group by')));
        $this->assertStringContainsString('q=Siswa%20Beban',$page->nextPageUrl());
    }

    public function test_legacy_links_teacher_search_input_validation_and_read_only_rendering(): void
    {
        $roster=InternshipRoster::whereHas('placement',fn($q)=>$q->whereNotNull('teacher_id'))->firstOrFail();
        $teacher=$roster->placement->teacher;
        $this->get(route('timeline.index',['placement_id'=>$roster->placement->id]))->assertOk()->assertViewHas('selectedStudentRow',fn($r)=>$r['roster']->id===$roster->id);
        $this->get(route('timeline.index',['q'=>$teacher->nip]))->assertOk()->assertSee('NIP '.$teacher->nip)->assertSee($roster->name);
        foreach (['q'=>str_repeat('a',101),'period_id'=>'abc','roster_id'=>-1,'students_page'=>0] as $key=>$value) {
            $this->get(route('timeline.index',[$key=>$value]))->assertSessionHasErrors($key);
        }
        $queries=[]; DB::listen(function($q)use(&$queries){$queries[]=$q->sql;});
        $this->get(route('timeline.index',['roster_id'=>$roster->id]))->assertOk();
        foreach($queries as $sql) $this->assertMatchesRegularExpression('/^\s*select\b/i',$sql);
    }

    public function test_historical_year_selection_does_not_mix_rosters_or_allow_mismatched_deep_links(): void
    {
        $first=InternshipRoster::firstOrFail();
        $past=InternshipRoster::create(['academic_year'=>'2025/2026','student_id'=>$first->student_id,'nis'=>$first->nis,'name'=>$first->name,
            'class_name'=>'XI HISTORIS','major'=>'Kuliner','internship_status'=>'internship',
            'internship_starts_at'=>'2025-08-01','internship_ends_at'=>'2025-10-01']);
        $this->actingAs($first->student->user)->get(route('timeline.index'))
            ->assertOk()->assertViewHas('chosen',$first->internship_period_id);
        $this->get(route('timeline.index',['roster_id'=>$past->id]))->assertOk()->assertSee('XI HISTORIS')
            ->assertViewHas('chosen',$past->internship_period_id)->assertViewHas('selectedStudentRow',fn($r)=>$r['schedule']['state']==='school');
        $this->get(route('timeline.index',['roster_id'=>$past->id,'period_id'=>$first->internship_period_id]))->assertNotFound();
    }
}
