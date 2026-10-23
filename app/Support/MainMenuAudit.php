<?php

namespace App\Support;

use App\Models\{Certificate, InternshipRoster, Journal, Placement, Student};
use InvalidArgumentException;

class MainMenuAudit
{
    /**
     * Read-only indicators. They do not automatically clear the manual code-audit
     * tags, and missing placement/teacher is not by itself corrupt data.
     */
    public function inspect(string $year): array
    {
        if (! preg_match('/^(\d{4})\/(\d{4})$/', $year, $matches)
            || (int) $matches[2] !== (int) $matches[1] + 1) {
            throw new InvalidArgumentException('Tahun ajaran harus berurutan, contoh 2026/2027.');
        }

        $rosters = InternshipRoster::where('academic_year', $year);
        $placements = Placement::whereHas('period', fn ($q) => $q->where('academic_year', $year));
        $placementIds = (clone $placements)->select('placements.id');
        $certificates = Certificate::whereIn('placement_id', clone $placementIds);
        $phases = array_fill_keys(array_keys(InternshipRoster::lifecycleLabels()), 0);
        foreach ((clone $rosters)->select('id', 'internship_status', 'internship_starts_at', 'internship_ends_at')->lazyById(500) as $row) {
            $phases[$row->lifecycleState()]++;
        }

        return [
            'checked_at' => now()->toIso8601String(),
            'academic_year' => $year,
            'code_reviewed_at' => config('main_menu_health.reviewed_at'),
            'modules' => collect(config('main_menu_health.modules'))->sortBy('order')
                ->map(fn ($module) => array_intersect_key($module, array_flip(['name', 'status', 'priority', 'order'])))->all(),
            'metrics' => [
                'students_all_years' => Student::count(),
                'rosters_in_year' => (clone $rosters)->count(),
                'placements_in_year' => (clone $placements)->count(),
                'unique_placed_students_in_year' => (clone $placements)->distinct()->count('student_id'),
                'rosters_without_placement' => (clone $rosters)->whereDoesntHave('placement')->count(),
                'scheduled_rosters_without_placement' => (clone $rosters)->whereDoesntHave('placement')
                    ->where('internship_status', '!=', 'not_internship')
                    ->whereNotNull('internship_starts_at')->whereNotNull('internship_ends_at')->count(),
                'placements_without_roster' => (clone $placements)->whereDoesntHave('roster')->count(),
                'placement_roster_identity_mismatches' => (clone $placements)
                    ->join('internship_rosters as audit_roster', 'audit_roster.id', '=', 'placements.internship_roster_id')
                    ->where(fn ($q) => $q->whereNull('audit_roster.student_id')
                        ->orWhereNull('audit_roster.internship_period_id')
                        ->orWhereColumn('placements.student_id', '!=', 'audit_roster.student_id')
                        ->orWhereColumn('placements.internship_period_id', '!=', 'audit_roster.internship_period_id'))->count(),
                'placements_without_teacher' => (clone $placements)->whereNull('teacher_id')->count(),
                'submitted_journals_without_teacher' => Journal::whereIn('placement_id', clone $placementIds)
                    ->where('status', 'submitted')->whereHas('placement', fn ($q) => $q->whereNull('teacher_id'))->count(),
                'journals_industry_validated_current_version' => Journal::whereIn('placement_id',clone $placementIds)->whereColumn('validated_revision','revision')->count(),
                'certificates_in_year' => (clone $certificates)->count(),
                'certificates_missing_roster_dates' => (clone $certificates)->whereDoesntHave('placement.roster', fn ($q) => $q
                    ->whereNotNull('internship_starts_at')->whereNotNull('internship_ends_at'))->count(),
                'issued_certificates_live_data' => (clone $certificates)->whereNotNull('issued_at')->count(),
                // Legacy placements without an academic-year tag cannot be attributed
                // to the selected year: expose them separately instead of hiding them.
                'placements_without_year_tag_all_years' => Placement::whereDoesntHave('period', fn ($q) => $q->whereNotNull('academic_year'))->count(),
            ],
            'phases' => $phases,
        ];
    }
}
