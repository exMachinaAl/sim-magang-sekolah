<?php

namespace Tests\Feature;

use App\Models\{Certificate, InternshipRoster, Journal, Placement, User};
use App\Support\MainMenuAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Characterization of OPEN defects, not acceptance tests of the desired design.
 * When fixing an issue, replace the corresponding assertion with the correct
 * behavior and update config/main_menu_health.php + docs/AUDIT_MENU_UTAMA.md.
 * Fixtures run only on SQLite :memory:; never mutate real school data to audit it.
 */
class MainMenuAuditEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-23 12:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->seed();
    }

    public function test_coordinator_can_review_and_monitor_students_without_teacher(): void
    {
        $journal = Journal::where('status', 'submitted')->whereHas('placement', fn ($q) => $q->whereNull('teacher_id'))->firstOrFail();
        $this->actingAs(User::where('role', 'koordinator')->firstOrFail())
            ->put(route('journals.review', $journal), ['revision'=>$journal->revision])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('monitoring.store'), [
            'placement_id' => $journal->placement_id, 'visit_date' => '2026-10-22',
            'notes' => 'Menindaklanjuti laporan kendala teknis.',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('monitoring_records', [
            'placement_id' => $journal->placement_id, 'teacher_id' => null,
            'created_by' => User::where('role', 'koordinator')->firstOrFail()->id,
        ]);
        $this->assertSame('approved', $journal->fresh()->status);
        $this->assertFalse($journal->fresh()->industryValidated());
        foreach (User::where('role', 'guru')->get() as $teacher) {
            $this->actingAs($teacher)->put(route('journals.review', $journal), ['status' => 'approved'])->assertNotFound();
        }
    }

    public function test_scheduled_student_without_placement_has_both_dashboard_and_timeline(): void
    {
        $roster = InternshipRoster::whereDoesntHave('placement')->where('internship_status', 'pending')->firstOrFail();
        $roster->update(['internship_status' => 'internship', 'internship_starts_at' => '2026-10-01', 'internship_ends_at' => '2026-12-31']);
        $this->actingAs($roster->student->user)->get(route('dashboard'))->assertOk()->assertSee('Sedang PKL');
        $this->get(route('timeline.index'))->assertOk()->assertSee('Sedang PKL')->assertSee('Belum ditempatkan')
            ->assertViewHas('selectedStudentRow',fn($row)=>$row['roster']->id===$roster->id)
            ->assertViewHas('periods', fn ($periods) => $periods->isNotEmpty());
        $this->assertSame(1, app(MainMenuAudit::class)->inspect('2026/2027')['metrics']['scheduled_rosters_without_placement']);
    }

    public function test_certificate_without_dates_is_rejected_before_creation(): void
    {
        $placement = Placement::whereHas('assessment', fn ($q) => $q->where('status', 'approved'))->whereDoesntHave('certificate')->firstOrFail();
        $placement->roster->update(['internship_starts_at' => null, 'internship_ends_at' => null]);
        $this->actingAs(User::where('role', 'koordinator')->firstOrFail())
            ->post(route('certificates.create'), ['placement_id' => $placement->id])->assertSessionHasErrors('certificate');
        $this->assertNull($placement->certificate()->first());
    }

    public function test_issued_pdf_is_unchanged_when_student_or_schedule_changes(): void
    {
        $certificate = Certificate::where('status', 'awaiting_print')->firstOrFail();
        $this->actingAs(User::where('role','koordinator')->firstOrFail());
        $before = $this->get(route('certificates.pdf',$certificate))->assertOk()->getContent();
        $certificate->placement->student->user->update(['name' => 'Nama Berubah Setelah Terbit']);
        $certificate->placement->roster->update(['internship_ends_at' => '2026-10-17']);
        $after = $this->get(route('certificates.pdf',$certificate))->assertOk()->getContent();
        $this->assertSame($before, $after);
        $this->assertStringNotContainsString('Nama Berubah Setelah Terbit', $certificate->documents()->firstOrFail()->rendered_html);
        $this->assertEquals($certificate->version, $certificate->fresh()->version);
    }
}
