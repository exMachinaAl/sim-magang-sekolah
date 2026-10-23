<?php

namespace Tests\Feature;

use App\Models\{InternshipRoster, Placement, User};
use App\Support\{MainMenuAudit, StudentEnrollment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, DB, Storage};
use Tests\TestCase;

class MainMenuAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-23 12:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->seed();
    }

    public function test_each_main_page_has_its_own_warning_and_six_sidebar_tags(): void
    {
        $this->actingAs(User::where('role', 'koordinator')->firstOrFail());
        foreach ([
            'dashboard' => 'dashboard', 'timeline.index' => 'timeline',
            'journals.index' => 'journals', 'assessments.index' => 'assessments',
            'monitoring.index' => 'monitoring', 'certificates.index' => 'certificates',
            'assessment-templates.index' => 'assessments',
        ] as $route => $module) {
            $response = $this->get(route($route))->assertOk()
                ->assertSee('data-module-health="'.$module.'"', false)
                ->assertSee(config('main_menu_health.modules.'.$module.'.summary'))
                ->assertSee('Sudah terhubung:')->assertSee('Perbaikan berikutnya:');
            $this->assertSame(6, substr_count($response->getContent(), 'class="module-health-badge '));
            $this->assertSame(1, substr_count($response->getContent(), 'data-module-health='));
        }
    }

    public function test_main_warnings_do_not_replace_management_flags_or_grant_student_access(): void
    {
        $this->actingAs(User::where('role', 'koordinator')->firstOrFail());
        foreach (['students.index', 'internships.index', 'placements.index', 'periods.index', 'companies.index', 'reports.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('data-module-health=', false);
        }
        $this->get(route('reports.index'))->assertSee('RED FLAG — modul belum sepenuhnya sinkron');
        $this->actingAs(User::where('role', 'siswa')->firstOrFail());
        foreach (['dashboard', 'timeline.index', 'journals.index', 'assessments.index', 'certificates.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('data-module-health=', false)
                ->assertDontSee('Periksa sumber jadwal di Kontrol PKL');
        }
        $this->get(route('monitoring.index'))->assertForbidden();
        $this->get(route('internships.index'))->assertForbidden();
        $this->actingAs(User::where('role', 'guru')->firstOrFail())->get(route('monitoring.index'))
            ->assertOk()->assertSee('data-module-health="monitoring"', false);
        $this->actingAs(User::where('role', 'admin')->firstOrFail())->get(route('dashboard'))
            ->assertOk()->assertSee('data-module-health="dashboard"', false);
    }

    public function test_audit_counts_are_scoped_read_only_and_do_not_mark_missing_placement_as_corruption(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $report = app(MainMenuAudit::class)->inspect('2026/2027');
        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $query);
        }
        $this->assertSame(30, $report['metrics']['rosters_in_year']);
        $this->assertSame(18, $report['metrics']['placements_in_year']);
        $this->assertSame(12, $report['metrics']['rosters_without_placement']);
        $this->assertSame(0, $report['metrics']['placement_roster_identity_mismatches']);
        $this->assertSame(2, $report['metrics']['submitted_journals_without_teacher']);
        $this->assertSame(1, $report['metrics']['issued_certificates_live_data']);
        $this->assertSame(30, array_sum($report['phases']));
        $this->assertSame([6], array_values(array_unique($report['phases'])));
        $this->assertSame(['certificates', 'journals', 'timeline', 'monitoring', 'assessments', 'dashboard'], array_keys($report['modules']));
        $other = app(MainMenuAudit::class)->inspect('2027/2028');
        $this->assertSame(30, $other['metrics']['students_all_years']);
        $this->assertSame(0, $other['metrics']['rosters_in_year']);
        $this->assertSame(0, $other['metrics']['placements_in_year']);
    }

    public function test_audit_detects_missing_schedule_and_mismatched_legacy_link(): void
    {
        $newStudent = app(StudentEnrollment::class)->register([
            'academic_year' => '2027/2028', 'nis' => 'AUDIT001', 'name' => 'Siswa Tahun Lain',
            'birth_date' => '2008-11-07', 'class_name' => 'XII 1', 'major' => 'Kuliner',
        ]);
        $otherRoster = $newStudent->rosters()->firstOrFail();
        $placement = Placement::whereHas('certificate')->firstOrFail();
        $placement->roster->update(['internship_starts_at' => null, 'internship_ends_at' => null]);
        $report = app(MainMenuAudit::class)->inspect('2026/2027');
        $this->assertSame(1, $report['metrics']['certificates_missing_roster_dates']);
        // Simulate imported/legacy damage without bypassing checks in production.
        DB::table('placements')->where('id', $placement->id)->update(['internship_roster_id' => $otherRoster->id]);
        $this->assertSame(1, app(MainMenuAudit::class)->inspect('2026/2027')['metrics']['placement_roster_identity_mismatches']);
        DB::table('placements')->where('id', $placement->id)->update(['internship_roster_id' => null]);
        $this->assertSame(1, app(MainMenuAudit::class)->inspect('2026/2027')['metrics']['placements_without_roster']);
    }

    public function test_command_supports_repeated_checks_json_and_invalid_years(): void
    {
        $this->assertSame(0, Artisan::call('simmag:audit-main-menu', ['--year' => '2026/2027', '--json' => true]));
        $output = Artisan::output();
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026/2027', $decoded['academic_year']);
        $this->assertSame(30, $decoded['metrics']['rosters_in_year']);
        $this->assertStringNotContainsString('@simmag.test', $output);
        $this->artisan('simmag:audit-main-menu')->expectsOutputToContain('Read-only.')
            ->expectsOutputToContain('Pemeriksaan ini tidak memasang jadwal otomatis.')->assertSuccessful();
        foreach (['2026', '2026/2028', 'invalid'] as $year) {
            $this->artisan('simmag:audit-main-menu', ['--year' => $year])
                ->expectsOutputToContain('Tahun ajaran harus berurutan')->assertExitCode(2);
        }
    }
}
