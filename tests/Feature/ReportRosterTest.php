<?php

namespace Tests\Feature;

use App\Models\{Assessment, Certificate, InternshipPeriod, InternshipRoster, Journal, Placement, User};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class ReportRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00','Asia/Jakarta'));
        Storage::fake('local');
        $this->seed();
        $this->actingAs(User::where('role','koordinator')->firstOrFail());
    }

    private function csv(string $type, array $filters=[]): array
    {
        $response=$this->get(route('reports.export',['type'=>$type]+$filters))->assertOk()
            ->assertHeader('Content-Type','text/csv; charset=UTF-8')->assertHeader('X-Content-Type-Options','nosniff');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $body=$response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF",$body);
        $file=fopen('php://temp','w+');
        fwrite($file,substr($body,3)); rewind($file);
        $headers=fgetcsv($file,0,',','"',''); $rows=[];
        while (($row=fgetcsv($file,0,',','"',''))!==false) $rows[]=array_combine($headers,$row);
        fclose($file);
        return $rows;
    }

    public function test_full_roster_includes_unplaced_and_non_participants_with_control_dates(): void
    {
        $this->get(route('reports.index'))->assertOk()->assertViewHas('rosterCount',30)->assertViewHas('placementCount',18);
        $rows=collect($this->csv('rosters'));
        $this->assertCount(30,$rows);
        $this->assertSame(12,$rows->where('Status Penempatan','Belum ditempatkan')->count());
        foreach (InternshipRoster::lifecycleLabels() as $label) $this->assertSame(6,$rows->where('Fase PKL',$label)->count());
        foreach ($rows as $row) {
            $this->assertSame('2026/2027',$row['Tahun Ajaran']);
            $this->assertSame('2026-10-24',$row['Tanggal Rekap WIB']);
            if ($row['Status Penempatan']==='Belum ditempatkan') $this->assertSame('',$row['Perusahaan']);
        }
        $this->assertCount(18,$this->csv('placements'));
        $this->assertDatabaseCount('internship_rosters',30);
    }

    public function test_every_export_uses_year_and_historical_major_and_identifies_the_student(): void
    {
        $source=Placement::whereHas('assessment')->whereHas('journals')->whereHas('certificate')->firstOrFail();
        $roster=$source->roster->replicate();
        $roster->academic_year='2025/2026'; $roster->major='Jurusan Lama'; $roster->class_name='XI Lama';
        $roster->name='Identitas Tahun Lama'; $roster->save();
        $older=$source->replicate(); $older->internship_roster_id=$roster->id;
        $older->internship_period_id=$roster->internship_period_id; $older->save();
        foreach ([$source->journals()->firstOrFail(),$source->assessment,$source->certificate] as $item) {
            $copy=$item->replicate(); $copy->placement_id=$older->id;
            if ($copy instanceof Certificate) $copy->number='LAPORAN-TAHUN-LAMA';
            $copy->save();
        }
        $source->student->update(['class_name'=>'Kelas Profil Baru','major'=>'Jurusan Profil Baru']);
        $filters=['period_id'=>$roster->internship_period_id,'major'=>'Jurusan Lama'];
        foreach (['rosters','placements','journals','assessments','certificates'] as $type) {
            $rows=$this->csv($type,$filters); $this->assertCount(1,$rows);
            $this->assertSame('XI Lama',$rows[0]['Kelas']);
            $this->assertSame('Jurusan Lama',$rows[0]['Jurusan']);
            $this->assertSame('Identitas Tahun Lama',$rows[0]['Nama']??$rows[0]['Siswa']);
            $this->assertSame('2025/2026',$rows[0]['Tahun Ajaran']??$rows[0]['Periode']);
            $this->assertSame("'".$roster->nis,$rows[0]['NIS']);
            $this->assertCount(0,$this->csv($type,['period_id'=>$source->internship_period_id,'major'=>'Jurusan Lama']));
        }
        $this->get(route('reports.index',$filters))->assertOk()->assertViewHas('rosterCount',1)->assertViewHas('placementCount',1)
            ->assertSee(route('reports.export',['type'=>'rosters']+$filters));
    }

    public function test_text_identifiers_formula_cells_and_multiline_csv_are_preserved_safely(): void
    {
        $roster=InternshipRoster::firstOrFail();
        $roster->update(['nis'=>'000001','nisn'=>'0000000000123','name'=>" \t=HYPERLINK(\"test\")",'class_name'=>"XI, A\nBaris kedua"]);
        $rows=collect($this->csv('rosters')); $row=$rows->firstWhere('ID Roster',(string)$roster->id);
        $this->assertSame("'000001",$row['NIS']); $this->assertSame("'0000000000123",$row['NISN']);
        $this->assertSame("' \t=HYPERLINK(\"test\")",$row['Nama']);
        $this->assertSame("XI, A\nBaris kedua",$row['Kelas']);
        $journal=Journal::firstOrFail(); $journal->update(['activity'=>"\n=1+1",'outcome'=>'@SUM(1)']);
        $row=collect($this->csv('journals'))->firstWhere('ID',(string)$journal->id);
        $this->assertSame("'\n=1+1",$row['Kegiatan']); $this->assertSame("'@SUM(1)",$row['Hasil']);
    }

    public function test_roles_validation_empty_year_and_unmatched_major_do_not_export_unfiltered_data(): void
    {
        $empty=InternshipPeriod::forAcademicYear('2028/2029');
        foreach (['rosters','placements','journals','assessments','certificates'] as $type) {
            $this->assertCount(0,$this->csv($type,['period_id'=>$empty->id]));
            $this->getJson(route('reports.export',['type'=>$type,'period_id'=>99999]))->assertUnprocessable();
            $this->getJson(route('reports.export',['type'=>$type,'major'=>['bad']]))->assertUnprocessable();
        }
        $this->get(route('reports.index',['major'=>'Tidak Ada']))->assertOk()->assertViewHas('rosterCount',0);
        $this->get(route('reports.export','invalid'))->assertNotFound();
        foreach (['guru','siswa','admin'] as $role) {
            $this->actingAs(User::where('role',$role)->firstOrFail())->get(route('reports.index'))->assertForbidden();
            foreach (['rosters','placements','journals','assessments','certificates'] as $type) $this->get(route('reports.export',$type))->assertForbidden();
        }
    }

    public function test_bad_legacy_links_are_marked_and_do_not_invent_historical_major(): void
    {
        $placement=Placement::firstOrFail(); $original=$placement->roster;
        $wrong=InternshipRoster::whereDoesntHave('placement')->firstOrFail();
        DB::table('placements')->where('id',$placement->id)->update(['internship_roster_id'=>$wrong->id]);
        $row=collect($this->csv('rosters'))->firstWhere('ID Roster',(string)$wrong->id);
        $this->assertSame('Tautan penempatan perlu diperiksa',$row['Status Penempatan']);
        $this->assertSame('',$row['Perusahaan']);
        $row=collect($this->csv('placements'))->firstWhere('ID',(string)$placement->id);
        $this->assertSame('Roster belum terhubung',$row['Jurusan']);
        $this->assertFalse(collect($this->csv('placements',['major'=>$wrong->major]))->contains('ID',(string)$placement->id));
        DB::table('placements')->where('id',$placement->id)->update(['internship_roster_id'=>null]);
        $row=collect($this->csv('placements'))->firstWhere('ID',(string)$placement->id);
        $this->assertSame('Roster belum terhubung',$row['Status']);
    }

    public function test_export_crosses_chunks_and_uses_end_date_inclusive_in_jakarta(): void
    {
        $roster=InternshipRoster::firstOrFail();
        $rows=[];
        for ($i=0;$i<251;$i++) $rows[]=['academic_year'=>'2026/2027','internship_period_id'=>$roster->internship_period_id,
            'nis'=>'BATCH'.$i,'name'=>'Siswa '.$i,'major'=>'Jurusan Batch','class_name'=>'XI',
            'internship_status'=>'pending'];
        DB::table('internship_rosters')->insert($rows);
        $this->assertCount(281,$this->csv('rosters'));
        $roster->update(['internship_status'=>'internship','internship_starts_at'=>'2026-10-24','internship_ends_at'=>'2026-10-24']);
        $row=collect($this->csv('rosters'))->firstWhere('ID Roster',(string)$roster->id);
        $this->assertSame('Sedang PKL',$row['Fase PKL']);
        $this->travelTo(Carbon::parse('2026-10-24 17:01:00','UTC'));
        $row=collect($this->csv('rosters'))->firstWhere('ID Roster',(string)$roster->id);
        $this->assertSame('Masuk sekolah',$row['Fase PKL']); $this->assertSame('2026-10-25',$row['Tanggal Rekap WIB']);
    }
}
