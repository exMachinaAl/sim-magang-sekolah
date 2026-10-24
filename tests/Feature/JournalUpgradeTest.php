<?php

namespace Tests\Feature;

use App\Models\{Journal, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class JournalUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_upgrade_preserves_existing_data_and_backfills_only_real_school_reviews(): void
    {
        // Never run schema rollback checks against a developer's database.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Storage::fake('local');
        $this->seed();
        $migration=require database_path('migrations/2026_10_23_000002_add_industry_journal_validation.php');
        $migration->down();
        $before=[];
        foreach (['users','students','internship_rosters','placements','journals','journal_attachments','certificates'] as $table) {
            $before[$table]=DB::table($table)->orderBy('id')->get()->map(fn($row)=>(array)$row)->all();
        }
        $migration->up();
        foreach ($before as $table=>$rows) {
            $this->assertSame(count($rows),DB::table($table)->count(),$table);
            foreach ($rows as $row) $this->assertDatabaseHas($table,$row);
        }
        $this->assertDatabaseCount('industry_devices',0);
        $this->assertDatabaseCount('journal_industry_validations',0);
        foreach (Journal::all() as $journal) {
            $this->assertSame(1,$journal->revision);
            $this->assertNull($journal->industry_score);
            $this->assertFalse($journal->industryValidated());
            $this->assertSame($journal->status==='approved' && $journal->reviewed_at && $journal->reviewed_by ? 1 : null,$journal->reviewed_revision);
        }
    }

    public function test_demo_seeders_can_be_repeated_without_duplicate_students_or_fake_device_validation(): void
    {
        Storage::fake('local');
        $this->seed();
        $this->seed(\Database\Seeders\IndustryJournalDemoSeeder::class);
        $this->seed();
        $this->seed(\Database\Seeders\IndustryJournalDemoSeeder::class);
        $this->assertDatabaseCount('students',30);
        $this->assertDatabaseCount('internship_rosters',30);
        $this->assertDatabaseCount('placements',18);
        $this->assertDatabaseCount('industry_mentors',1);
        $this->assertDatabaseCount('industry_devices',0);
        $this->assertDatabaseCount('journal_industry_validations',0);
        $this->assertSame(2,User::where('role','koordinator')->count());
        $this->assertTrue(\App\Models\IndustryMentor::first()->placements()->exists());
        foreach (Journal::where('status','approved')->get() as $journal) $this->assertSame($journal->revision,$journal->reviewed_revision);
    }
}
