<?php

namespace Database\Seeders;

use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateDefault;
use App\Models\InternshipRoster;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssessmentDefaultTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $coordinatorId = User::query()->where('role', 'koordinator')->where('is_active', true)
                ->orderBy('id')->value('id');

            foreach (InternshipRoster::officialMajors() as $major) {
                $template = AssessmentTemplate::query()->firstOrCreate(
                    ['name' => 'Default 80/20 — '.$major, 'major' => $major],
                    [
                        'certificate_heading' => 'SERTIFIKAT PRAKTIK KERJA LAPANGAN',
                        'certificate_statement' => 'Telah melaksanakan Praktik Kerja Lapangan dan penilaian kompetensi dari industri.',
                        'fields' => AssessmentTemplate::defaultFields($major),
                        'created_by' => $coordinatorId,
                        'is_active' => true,
                    ]
                );

                $default = AssessmentTemplateDefault::query()->where('major', $major)->lockForUpdate()->first();
                if (! $default) {
                    AssessmentTemplateDefault::query()->create([
                        'major' => $major,
                        'assessment_template_id' => $template->id,
                        'updated_by' => $coordinatorId,
                    ]);
                } elseif ($default->updated_by === null) {
                    // The migration backfill has no editor. Install the agreed standard once,
                    // then preserve every later choice made explicitly by a coordinator.
                    $default->update([
                        'assessment_template_id' => $template->id,
                        'updated_by' => $coordinatorId,
                    ]);
                }
            }
        });
    }
}
