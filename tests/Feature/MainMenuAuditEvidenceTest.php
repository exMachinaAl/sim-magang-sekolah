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

    public function test_coordinator_can_review_teacherless_journals_but_monitoring_remains_open(): void
    {
        $journal = Journal::where('status', 'submitted')->whereHas('placement', fn ($q) => $q->whereNull('teacher_id'))->firstOrFail();
        $this->actingAs(User::where('role', 'koordinator')->firstOrFail())
            ->put(route('journals.review', $journal), ['revision'=>$journal->revision])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('monitoring.store'), [
            'placement_id' => $journal->placement_id, 'visit_date' => '2026-10-22',
            'notes' => 'Menindaklanjuti laporan kendala teknis.',
        ])->assertForbidden();
        $this->assertSame('approved', $journal->fresh()->status);
        $this->assertFalse($journal->fresh()->industryValidated());
        foreach (User::where('role', 'guru')->get() as $teacher) {
            $this->actingAs($teacher)->put(route('journals.review', $journal), ['status' => 'approved'])->assertNotFound();
        }
    }

    public function test_open_issue_scheduled_student_without_placement_has_dashboard_status_but_no_timeline_row(): void
    {
        $roster = InternshipRoster::whereDoesntHave('placement')->where('internship_status', 'pending')->firstOrFail();
        $roster->update(['internship_status' => 'internship', 'internship_starts_at' => '2026-10-01', 'internship_ends_at' => '2026-12-31']);
        $this->actingAs($roster->student->user)->get(route('dashboard'))->assertOk()->assertSee('Sedang PKL');
        $this->get(route('timeline.index'))->assertOk()->assertViewHas('selectedStudentRow', null)
            ->assertViewHas('periods', fn ($periods) => $periods->isEmpty());
        $this->assertSame(1, app(MainMenuAudit::class)->inspect('2026/2027')['metrics']['scheduled_rosters_without_placement']);
    }

    public function test_open_issue_certificate_can_be_created_and_approved_without_dates_but_pdf_fails(): void
    {
        $placement = Placement::whereHas('assessment', fn ($q) => $q->where('status', 'approved'))->whereDoesntHave('certificate')->firstOrFail();
        $placement->roster->update(['internship_starts_at' => null, 'internship_ends_at' => null]);
        $this->actingAs(User::where('role', 'koordinator')->firstOrFail())
            ->post(route('certificates.create'), ['placement_id' => $placement->id])->assertSessionHasNoErrors()->assertRedirect();
        $certificate = $placement->certificate()->firstOrFail();
        $this->put(route('certificates.approve', $certificate), ['decision' => 'approved'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('awaiting_print', $certificate->fresh()->status);
        $this->get(route('certificates.pdf', $certificate))->assertUnprocessable();
    }

    public function test_open_issue_issued_certificate_template_changes_without_a_new_document_version(): void
    {
        $certificate = Certificate::where('status', 'awaiting_print')->firstOrFail();
        $render = function () use ($certificate) {
            $copy = $certificate->fresh();
            return view('certificates.template-pdf', [
                'certificate' => $copy,
                'startsAt' => $copy->placement->roster->internship_starts_at,
                'endsAt' => $copy->placement->roster->internship_ends_at,
            ])->render();
        };
        $before = $render();
        $certificate->placement->student->user->update(['name' => 'Nama Berubah Setelah Terbit']);
        $certificate->placement->roster->update(['internship_ends_at' => '2026-10-17']);
        $after = $render();
        $this->assertNotSame($before, $after);
        $this->assertStringNotContainsString('Nama Berubah Setelah Terbit', $before);
        $this->assertStringContainsString('Nama Berubah Setelah Terbit', $after);
        $this->assertStringContainsString('17 Oktober 2026', $after);
        $this->assertEquals($certificate->version, $certificate->fresh()->version);
    }
}
