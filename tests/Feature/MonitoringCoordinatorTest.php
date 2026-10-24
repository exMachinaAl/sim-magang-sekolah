<?php

namespace Tests\Feature;

use App\Models\{MonitoringRecord, Placement, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class MonitoringCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 12:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->seed();
    }

    private function payload(Placement $placement): array
    {
        return ['placement_id' => $placement->id, 'visit_date' => '2026-10-23',
            'notes' => 'Menangani kendala teknis dari laporan siswa.', 'follow_up' => 'Hubungi pihak industri.'];
    }

    public function test_coordinators_record_their_own_identity_without_requiring_or_impersonating_teacher(): void
    {
        $placement = Placement::whereNull('teacher_id')->firstOrFail();
        foreach (User::where('role', 'koordinator')->get() as $actor) {
            $this->actingAs($actor)->post(route('monitoring.store'), $this->payload($placement) + [
                'created_by' => User::where('role', 'guru')->firstOrFail()->id, 'teacher_id' => $actor->id,
            ])->assertSessionHasNoErrors()->assertRedirect(route('monitoring.index', ['placement_id' => $placement->id]));
            $record = MonitoringRecord::latest('id')->firstOrFail();
            $this->assertSame($actor->id, $record->created_by);
            $this->assertNull($record->teacher_id);
            $this->assertDatabaseHas('audit_logs', ['action' => 'monitoring.created', 'subject_id' => $record->id, 'user_id' => $actor->id]);
            $this->get(route('monitoring.index', ['placement_id' => $placement->id]))->assertOk()
                ->assertSee('ID: '.$actor->id)->assertSee('Tidak ditetapkan')->assertSee($record->notes);
        }
        $assigned = Placement::whereNotNull('teacher_id')->firstOrFail();
        $this->post(route('monitoring.store'), $this->payload($assigned))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('monitoring_records', ['placement_id' => $assigned->id,
            'created_by' => $actor->id, 'teacher_id' => $assigned->teacher_id]);
    }

    public function test_teacher_scope_is_enforced_for_search_selection_and_writes_and_students_cannot_access(): void
    {
        $teacher = User::where('role', 'guru')->firstOrFail();
        $own = Placement::where('teacher_id', $teacher->id)->firstOrFail();
        $other = Placement::whereNull('teacher_id')->firstOrFail();
        $this->actingAs($teacher)->post(route('monitoring.store'), $this->payload($own))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('monitoring_records', ['placement_id' => $own->id, 'created_by' => $teacher->id]);
        $this->post(route('monitoring.store'), $this->payload($other))->assertNotFound();
        $this->get(route('monitoring.index', ['placement_id' => $other->id]))->assertNotFound();
        $this->get(route('monitoring.index', ['q' => $other->student->nis]))->assertOk()
            ->assertViewHas('candidates', fn ($rows) => $rows->total() === 0);
        $this->get(route('monitoring.index'))->assertOk()
            ->assertViewHas('items', fn ($rows) => $rows->total() === 1);
        $this->actingAs(User::where('role', 'admin')->firstOrFail())
            ->post(route('monitoring.store'), $this->payload($own))->assertForbidden();
        $this->actingAs($own->student->user)->get(route('monitoring.index'))->assertForbidden();
        $this->post(route('monitoring.store'), $this->payload($own))->assertForbidden();
    }

    public function test_search_and_year_major_filters_apply_to_both_candidates_and_history_with_historical_labels(): void
    {
        $actor = User::where('role', 'koordinator')->firstOrFail();
        $placement = Placement::firstOrFail();
        $historical = $placement->roster->major;
        $placement->student->update(['major' => 'Profil Baru', 'class_name' => 'KELAS BARU']);
        $this->actingAs($actor)->post(route('monitoring.store'), $this->payload($placement))->assertRedirect();
        $query = ['q' => $placement->student->nis, 'period_id' => $placement->internship_period_id, 'major' => $historical];
        $this->get(route('monitoring.index', $query))->assertOk()->assertSee($historical)->assertDontSee('Profil Baru')
            ->assertDontSee('KELAS BARU')->assertViewHas('candidates', fn ($rows) => $rows->total() === 1)
            ->assertViewHas('items', fn ($rows) => $rows->total() === 1)->assertDontSee('<select name="placement_id"', false);
        $this->get(route('monitoring.index', ['q' => $placement->student->user->name]))->assertOk()
            ->assertViewHas('candidates', fn ($rows) => $rows->contains('id', $placement->id));
        foreach ([['major' => 'Jurusan Tidak Ada'], ['period_id' => 99999], ['q' => 'NIS-TIDAK-ADA']] as $filter) {
            $this->get(route('monitoring.index', array_replace($query, $filter)))->assertOk()
                ->assertViewHas('candidates', fn ($rows) => $rows->total() === 0)
                ->assertViewHas('items', fn ($rows) => $rows->total() === 0);
        }
        $this->get(route('monitoring.index'))->assertOk()->assertViewHas('candidates', fn ($rows) => $rows->total() === 0);
    }

    public function test_validation_preserves_form_and_does_not_create_invalid_notes(): void
    {
        $placement = Placement::firstOrFail();
        $this->actingAs(User::where('role', 'koordinator')->firstOrFail());
        $url = route('monitoring.index', ['placement_id' => $placement->id]);
        foreach (['notes' => 'Singkat', 'visit_date' => '2026-10-25', 'follow_up' => ['not a string']] as $key => $value) {
            $this->from($url)->post(route('monitoring.store'), array_replace($this->payload($placement), [$key => $value]))
                ->assertSessionHasErrors($key)->assertRedirect($url);
        }
        $this->get($url)->assertOk()->assertSee('Menangani kendala teknis dari laporan siswa.');
        $this->assertDatabaseCount('monitoring_records', 0);
        $this->getJson(route('monitoring.index', ['q' => ['bad']]))->assertUnprocessable();
        $this->getJson(route('monitoring.index', ['page' => -1]))->assertUnprocessable();
    }

    public function test_lists_use_separate_database_pagination_and_preserve_filters(): void
    {
        $placement = Placement::firstOrFail();
        $actor = User::where('role', 'koordinator')->firstOrFail();
        for ($i = 0; $i < 18; $i++) {
            MonitoringRecord::create($this->payload($placement) + ['created_by' => $actor->id, 'teacher_id' => null]);
        }
        $this->actingAs($actor)->get(route('monitoring.index', ['period_id' => $placement->internship_period_id]))->assertOk()
            ->assertViewHas('candidates', fn ($rows) => $rows->total() === 18 && $rows->count() === 10)
            ->assertViewHas('items', fn ($rows) => $rows->total() === 18 && $rows->count() === 15);
        $this->get(route('monitoring.index', ['period_id' => $placement->internship_period_id, 'page' => 2, 'candidates_page' => 2]))
            ->assertOk()->assertViewHas('candidates', fn ($rows) => $rows->count() === 8)
            ->assertViewHas('items', fn ($rows) => $rows->count() === 3 && str_contains($rows->previousPageUrl(), 'period_id='));
    }

    public function test_legacy_migration_preserves_notes_and_backfills_actual_teacher_author(): void
    {
        $placement = Placement::whereNotNull('teacher_id')->firstOrFail();
        $migration = require database_path('migrations/2026_10_24_000002_add_monitoring_author.php');
        $migration->down();
        $id = DB::table('monitoring_records')->insertGetId($this->payload($placement) + ['teacher_id' => $placement->teacher_id]);
        $migration->up();
        $this->assertDatabaseHas('monitoring_records', ['id' => $id, 'created_by' => $placement->teacher_id,
            'teacher_id' => $placement->teacher_id, 'notes' => $this->payload($placement)['notes']]);
        $this->actingAs(User::where('role', 'koordinator')->firstOrFail())
            ->get(route('monitoring.index'))->assertOk()->assertSee('ID: '.$placement->teacher_id);
    }
}
