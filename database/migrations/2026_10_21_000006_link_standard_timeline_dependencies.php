<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $periodIds = DB::table('timeline_events')->distinct()->pluck('internship_period_id');

        foreach ($periodIds as $periodId) {
            $events = DB::table('timeline_events')
                ->where('internship_period_id', $periodId)
                ->orderBy('starts_at')
                ->get();
            $preparation = $events->first(fn ($event) => $event->scope === 'period'
                && str_contains(strtolower($event->title), 'pembekalan'));
            $bySource = $events->where('scope', 'placement')->keyBy('progress_source');

            $links = [
                'placement' => $preparation?->id,
                'journals' => $bySource->get('placement')?->id,
                'monitoring' => $bySource->get('placement')?->id,
                'assessment' => $bySource->get('journals')?->id,
                'certificate_approval' => $bySource->get('assessment')?->id,
                'certificate_distribution' => $bySource->get('certificate_approval')?->id,
            ];

            foreach ($links as $source => $dependencyId) {
                if (! $dependencyId) {
                    continue;
                }
                DB::table('timeline_events')
                    ->where('internship_period_id', $periodId)
                    ->where('progress_source', $source)
                    ->whereNull('dependency_event_id')
                    ->update(['dependency_event_id' => $dependencyId]);
            }
        }
    }

    public function down(): void
    {
        // Dependency choices may have been edited after deployment, so rollback keeps the data intact.
    }
};
