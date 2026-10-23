<?php
namespace Tests\Feature;

use App\Models\{IndustryDevice, IndustryMentor, Journal, JournalIndustryValidation, Placement, User};
use App\Support\JournalWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\TestCase;

class IndustryJournalTest extends TestCase
{
    use RefreshDatabase;
    private User $coordinator;
    private Placement $placement;
    private IndustryMentor $mentor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-23 12:00:00','Asia/Jakarta'));
        Storage::fake('local'); $this->seed();
        $this->coordinator=User::where('role','koordinator')->firstOrFail();
        $this->placement=Placement::whereHas('roster',fn($q)=>$q->where('internship_starts_at','<','2026-10-23')->where('internship_ends_at','>','2026-10-23'))->firstOrFail();
        $this->mentor=IndustryMentor::create(['name'=>'Dodi Uji','company_id'=>$this->placement->company_id,'created_by'=>$this->coordinator->id]);
        $this->mentor->placements()->attach($this->placement->id);
    }

    private function journal(): Journal
    {
        return Journal::create(['placement_id'=>$this->placement->id,'activity_date'=>'2026-10-21',
            'activity'=>'Memasang kabel panel','outcome'=>'','status'=>'submitted','industry_score'=>88,'revision'=>1,
            'work_area'=>'Bengkel','starts_time'=>'08:00','ends_time'=>'16:00','equipment_notes'=>'Tang dan kabel'])->fresh();
    }

    private function device(): string
    {
        $secret=str_repeat('d',64);
        IndustryDevice::create(['industry_mentor_id'=>$this->mentor->id,'token_hash'=>hash('sha256',$secret),'label'=>'Browser Uji','expires_at'=>now()->addDays(180)]);
        return $secret;
    }

    private function qr(Journal $journal): string
    {
        return $this->actingAs($this->placement->student->user)->post(route('journals.qr',$journal))->assertOk()->viewData('scanUrl');
    }

    public function test_student_can_submit_without_photos_and_coordinator_review_is_not_industry_validation(): void
    {
        $this->actingAs($this->placement->student->user)->post(route('journals.store'),[
            'placement_id'=>$this->placement->id,'activity_date'=>'2026-10-21','activity'=>'Merakit panel','intent'=>'submitted',
            'industry_score'=>88.25,'work_area'=>'Bengkel','starts_time'=>'08:00','ends_time'=>'16:00',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $journal=Journal::where('placement_id',$this->placement->id)->whereDate('activity_date','2026-10-21')->firstOrFail();
        $this->assertSame('88.25',$journal->industry_score);
        $this->assertSame('B+',$journal->scoreCategory());
        $this->assertSame(0,$journal->attachments()->count());
        $this->actingAs($this->coordinator)->put(route('journals.review',$journal),['revision'=>1,'review_note'=>'Sudah dibaca'])->assertSessionHasNoErrors();
        $this->assertSame('approved',$journal->fresh()->status);
        $this->assertFalse($journal->fresh()->industryValidated());
        $this->assertDatabaseCount('journal_industry_validations',0);
    }

    public function test_enrollment_get_is_read_only_post_registers_once_and_cookie_is_private(): void
    {
        $url=$this->actingAs($this->coordinator)->post(route('journals.mentors.invite',$this->mentor))->assertOk()->viewData('url');
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('scan-form');
        $this->assertDatabaseCount('industry_devices',0);
        $response=$this->post($url)->assertRedirect(route('industry.home'))->assertCookie('industry_device');
        $this->assertDatabaseCount('industry_devices',1);
        $this->assertNotNull(DB::table('industry_device_invites')->first()->used_at);
        $cookie=collect($response->headers->getCookies())->first(fn($c)=>$c->getName()==='industry_device');
        $this->assertTrue($cookie->isHttpOnly()); $this->assertSame('/industry',$cookie->getPath()); $this->assertSame('lax',$cookie->getSameSite());
        $secret=$response->getCookie('industry_device')->getValue();
        $this->assertSame(hash('sha256',$secret),IndustryDevice::first()->token_hash);
        $this->withCookie('industry_device',$secret)->get(route('industry.home'))->assertOk()->assertSee('Dodi Uji');
        $this->post($url)->assertGone();
        $this->assertDatabaseCount('industry_devices',1);
    }

    public function test_trusted_device_can_scan_later_and_duplicate_scan_is_idempotent(): void
    {
        $journal=$this->journal(); $url=$this->qr($journal); $secret=$this->device();
        auth()->logout();
        $this->withCookie('industry_device',$secret)->get($url)->assertOk()->assertSee('88.00');
        $this->assertDatabaseCount('journal_industry_validations',0); // GET/prefetch must not write.
        $this->withCookie('industry_device',$secret)->post($url)->assertRedirect(route('industry.home'));
        $validation=JournalIndustryValidation::firstOrFail();
        $this->assertSame('2026-10-21',$validation->snapshot['activity_date']);
        $this->assertSame('2026-10-23 12:00:00',$validation->scanned_at->format('Y-m-d H:i:s'));
        $this->assertTrue($journal->fresh()->industryValidated());
        $this->withCookie('industry_device',$secret)->post($url)->assertRedirect();
        $this->assertDatabaseCount('journal_industry_validations',1);
        $this->assertSame(1,DB::table('audit_logs')->where('action','journal.industry_validated')->count());
        $this->assertNull(DB::table('audit_logs')->where('action','journal.industry_validated')->first()->user_id);
        $this->withCookie('industry_device',$secret)->get(route('industry.home'))->assertOk()->assertSee('Dodi Uji');
    }

    public function test_untrusted_wrong_assignment_revoked_and_expired_devices_cannot_validate(): void
    {
        $journal=$this->journal(); $url=$this->qr($journal); auth()->logout();
        $this->post($url)->assertForbidden();
        $secret=$this->device();
        $this->mentor->placements()->detach();
        $this->withCookie('industry_device',$secret)->post($url)->assertForbidden();
        $this->mentor->placements()->attach($this->placement->id);
        IndustryDevice::query()->update(['revoked_at'=>now()]);
        $this->withCookie('industry_device',$secret)->post($url)->assertNotFound();
        IndustryDevice::query()->update(['revoked_at'=>null,'expires_at'=>now()->subMinute()]);
        $this->withCookie('industry_device',$secret)->post($url)->assertNotFound();
        $this->assertDatabaseCount('journal_industry_validations',0);
    }

    public function test_edit_creates_new_revision_and_preserves_old_validation_without_reusing_old_qr(): void
    {
        $journal=$this->journal(); $url=$this->qr($journal); $secret=$this->device();
        $this->withCookie('industry_device',$secret)->post($url)->assertRedirect();
        $data=['placement_id'=>$this->placement->id,'activity_date'=>'2026-10-21','activity'=>'Merakit panel baru','industry_score'=>91,
            'intent'=>'submitted','revision'=>1];
        $this->actingAs($this->placement->student->user)->post(route('journals.store'),$data)->assertSessionHasNoErrors();
        $journal->refresh();
        $this->assertSame(2,$journal->revision); $this->assertFalse($journal->industryValidated());
        $this->assertSame('88.00',JournalIndustryValidation::first()->snapshot['industry_score']);
        $this->withCookie('industry_device',$secret)->post($url)->assertConflict();
        $this->post(route('journals.store'),$data)->assertSessionHasErrors('revision');
        $newUrl=$this->qr($journal);
        $this->withCookie('industry_device',$secret)->post($newUrl)->assertRedirect();
        $this->assertDatabaseCount('journal_industry_validations',2);
        $this->assertTrue($journal->fresh()->industryValidated());
    }

    public function test_scan_after_pkl_finished_is_allowed_but_stale_qr_must_be_regenerated(): void
    {
        $journal=$this->journal(); $url=$this->qr($journal); $secret=$this->device();
        $this->travel(2)->days();
        $this->withCookie('industry_device',$secret)->post($url)->assertGone();
        $this->placement->roster->update(['internship_ends_at'=>'2026-10-22']);
        $newUrl=$this->qr($journal);
        $this->withCookie('industry_device',$secret)->post($newUrl)->assertRedirect();
        $this->assertSame('2026-10-25',JournalIndustryValidation::first()->scanned_at->toDateString());
    }

    public function test_invalid_score_time_missing_score_and_other_student_qr_are_rejected(): void
    {
        $data=['placement_id'=>$this->placement->id,'activity_date'=>'2026-10-21','activity'=>'Merakit panel','intent'=>'submitted'];
        $this->actingAs($this->placement->student->user)->post(route('journals.store'),$data+['industry_score'=>100.1])->assertSessionHasErrors('industry_score');
        $this->post(route('journals.store'),$data+['starts_time'=>'17:00','ends_time'=>'08:00'])->assertSessionHasErrors('ends_time');
        $journal=$this->journal(); $journal->update(['industry_score'=>null]);
        $this->post(route('journals.qr',$journal))->assertUnprocessable();
        $other=User::where('role','siswa')->where('id','!=',$this->placement->student->user_id)->firstOrFail();
        $this->actingAs($other)->post(route('journals.qr',$journal))->assertNotFound();
        $this->get(route('journals.excel',$this->placement))->assertNotFound();
        $this->get(route('journals.mentors'))->assertForbidden();
    }

    public function test_workbook_preserves_template_six_rows_overflow_exact_scores_and_safe_strings(): void
    {
        $this->placement->journals()->delete(); // This test's isolated seed has no validation records.
        foreach([75,81,88.25,91,100,0,74.99] as $i=>$score) {
            Journal::create(['placement_id'=>$this->placement->id,'activity_date'=>'2026-10-'.(12+$i),
                'activity'=>$i===0?'=HYPERLINK("https://example.test")':'Kegiatan '.($i+1),'outcome'=>'','status'=>'submitted','industry_score'=>$score]);
        }
        $book=app(JournalWorkbook::class)->build($this->placement);
        $this->assertSame(3,$book->getSheetCount()); // 2 print pages + data
        $first=$book->getSheet(0); $second=$book->getSheet(1);
        $this->assertSame(88.25,$first->getCell('I13')->getValue());
        $this->assertSame(DataType::TYPE_STRING,$first->getCell('E9')->getDataType());
        $this->assertStringContainsString('Kegiatan 7',$second->getCell('E9')->getValue());
        $this->assertStringContainsString('74.99',$second->getCell('F9')->getValue());
        $this->assertStringContainsString('0.00',$first->getCell('F19')->getValue());
        $this->assertSame('Belum divalidasi industri',$first->getCell('L9')->getValue());
        $this->assertContains('A1:L2',$first->getMergeCells());
        $this->assertSame('A1:L23',$first->getPageSetup()->getPrintArea());
        foreach($book->getAllSheets() as $sheet) foreach($sheet->toArray() as $row) foreach($row as $value) {
            $this->assertStringNotContainsString('{{',(string)$value);
        }
        $response=$this->actingAs($this->placement->student->user)->get(route('journals.excel',$this->placement))->assertOk();
        $this->assertStringStartsWith('PK',$response->streamedContent());
        $book->disconnectWorksheets();
    }

    public function test_only_coordinator_can_enroll_manage_assign_and_revoke_devices(): void
    {
        $teacher=User::where('role','guru')->firstOrFail();
        $this->actingAs($teacher)->post(route('journals.mentors.invite',$this->mentor))->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('journals.mentors',['company_id'=>$this->placement->company_id]))->assertOk()->assertSee('Dodi Uji');
        $wrong=Placement::where('company_id','!=',$this->placement->company_id)->firstOrFail();
        $this->put(route('journals.mentors.assignment',$this->mentor),['placement_id'=>$wrong->id,'action'=>'add'])->assertUnprocessable();
        $this->post(route('journals.mentors.store'),['name'=>'Pembimbing Salah','company_id'=>$this->placement->company_id,'placement_ids'=>[$wrong->id]])->assertUnprocessable();
        $secret=$this->device();
        $this->put(route('journals.devices.revoke',IndustryDevice::first()),[])->assertRedirect();
        $this->assertNotNull(IndustryDevice::first()->revoked_at);
    }

    public function test_new_enrollment_invite_invalidates_previous_invite_and_disabling_mentor_revokes_every_device(): void
    {
        $this->actingAs($this->coordinator);
        $old=$this->post(route('journals.mentors.invite',$this->mentor))->assertOk()->viewData('url');
        $new=$this->post(route('journals.mentors.invite',$this->mentor))->assertOk()->viewData('url');
        $this->get($old)->assertGone();
        $this->get($new)->assertOk();
        $secret=$this->device();
        $this->put(route('journals.mentors.toggle',$this->mentor))->assertRedirect();
        $this->get($new)->assertGone();
        $this->assertFalse($this->mentor->fresh()->is_active);
        $this->assertNotNull(IndustryDevice::first()->revoked_at);
        $this->put(route('journals.mentors.toggle',$this->mentor))->assertRedirect();
        $this->withCookie('industry_device',$secret)->get(route('industry.home'))->assertNotFound();
    }

    public function test_school_review_cannot_mark_industry_validation_and_stale_review_is_rejected(): void
    {
        $journal=$this->journal();
        $this->actingAs($this->coordinator)->put(route('journals.review',$journal),['revision'=>99])->assertUnprocessable();
        $this->put(route('journals.review',$journal),['revision'=>1,'status'=>'rejected'])->assertSessionHasErrors('status');
        $this->assertFalse($journal->fresh()->industryValidated());
        $teacher=$this->placement->teacher;
        $this->assertNotNull($teacher);
        $this->actingAs($teacher)->put(route('journals.review',$journal),['revision'=>1])->assertSessionHasNoErrors();
        $this->assertSame($teacher->id,$journal->fresh()->reviewed_by);
    }

    public function test_blank_and_filtered_workbooks_have_no_placeholders_or_cross_student_rows(): void
    {
        $journal=$this->journal();
        $draft=Journal::create(['placement_id'=>$this->placement->id,'activity_date'=>'2026-10-20','activity'=>'DRAF RAHASIA','outcome'=>'','status'=>'draft']);
        $book=app(JournalWorkbook::class)->build($this->placement,'2026-10-20','2026-10-21');
        $this->assertSame(2,$book->getSheetCount());
        $this->assertSame('Memasang kabel panel',$book->getSheet(0)->getCell('E9')->getValue());
        $this->assertSame(2,$book->getSheetByName('Data Laravel')->getHighestDataRow());
        $this->assertStringNotContainsString('DRAF RAHASIA',json_encode($book->getSheet(0)->toArray()));
        $book->disconnectWorksheets();
        $empty=app(JournalWorkbook::class)->build($this->placement,'2020-01-01','2020-01-02');
        $this->assertSame(2,$empty->getSheetCount());
        $this->assertSame('',$empty->getSheet(0)->getCell('E9')->getValue());
        $this->assertSame(1,$empty->getSheetByName('Data Laravel')->getHighestDataRow());
        $empty->disconnectWorksheets();
    }

    public function test_score_boundaries_and_decimal_values_are_not_rounded_to_category_midpoints(): void
    {
        foreach([[null,null],[0,'Di bawah 75'],[74.99,'Di bawah 75'],[75,'B-'],[80.99,'B-'],[81,'B'],[85.99,'B'],[86,'B+'],[90.99,'B+'],[91,'A-'],[95.99,'A-'],[96,'A'],[100,'A']] as [$score,$category]) {
            $journal=new Journal(['industry_score'=>$score]);
            $this->assertSame($category,$journal->scoreCategory());
        }
    }
}
