<?php

namespace App\Http\Controllers;

use App\Models\{InternshipPeriod, InternshipRoster, Placement, TimelineEvent, User};
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TimelineController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $allowedPeriodIds = Access::placements($user)->select('internship_period_id');
        $periods = InternshipPeriod::query()
            ->when(! $user->isRole('admin', 'koordinator'), fn ($query) => $query->whereIn('id', $allowedPeriodIds))
            ->latest()->get();
        $chosen = $request->integer('period_id') ?: $periods->first()?->id;
        abort_if($chosen && ! $periods->contains('id', $chosen), 403);

        $placements = Access::placements($user)
            ->with(['student.user', 'teacher', 'company', 'period', 'roster.scheduleBatch.creator', 'roster.updater'])
            ->where('internship_period_id', $chosen ?? 0)
            ->orderBy('id')->get();
        $allRows = $placements->map(fn (Placement $placement) => ['placement'=>$placement, 'schedule'=>$this->dateSchedule($placement, $placement->roster)]);

        $teachers = User::query()->whereIn('id', $placements->pluck('teacher_id')->unique())
            ->orderBy('name')->get(['id', 'name', 'nip']);
        if ($placements->contains(fn ($p) => $p->teacher_id === null)) {
            $teachers->push((new User())->forceFill(['id'=>0,'name'=>'Tanpa guru pendamping / Koordinator','nip'=>null]));
        }
        $teacherGroups = $teachers->map(function (User $teacher) use ($allRows) {
            $rows = $allRows->filter(fn ($row) => (int)$row['placement']->teacher_id === (int)$teacher->id);
            return [
                'teacher' => $teacher,
                'students_count' => $rows->count(),
                'pkl_count' => $rows->where('schedule.state', 'pkl')->count(),
                'school_count' => $rows->where('schedule.state', 'school')->count(),
                'unscheduled_count' => $rows->where('schedule.state', 'unscheduled')->count(),
            ];
        })->values();

        $selectedTeacher = null;
        $selectedStudentRow = null;
        if ($request->integer('placement_id')) {
            $selectedStudentRow = $allRows->first(fn ($row) => $row['placement']->id === $request->integer('placement_id'));
            abort_unless($selectedStudentRow, 403);
            $selectedTeacher = $teachers->firstWhere('id', $selectedStudentRow['placement']->teacher_id ?? 0);
        } elseif ($user->isRole('siswa')) {
            $selectedStudentRow = $allRows->first();
            $selectedTeacher = $selectedStudentRow
                ? $teachers->firstWhere('id', $selectedStudentRow['placement']->teacher_id ?? 0)
                : null;
        } elseif ($user->isRole('guru')) {
            $selectedTeacher = $teachers->firstWhere('id', $user->id);
        } elseif ($request->filled('teacher_id')) {
            $selectedTeacher = $teachers->firstWhere('id', $request->integer('teacher_id'));
            abort_unless($selectedTeacher, 403);
        }

        $teacherStudentRows = $selectedTeacher
            ? $allRows->filter(fn ($row) => (int)$row['placement']->teacher_id === (int)$selectedTeacher->id)->values()
            : collect();
        $studentRows = $this->paginateCollection($teacherStudentRows, 20, 'students_page', $request);
        $teacherDirectory = $this->paginateCollection($teacherGroups, 15, 'teachers_page', $request);

        $search = trim((string) $request->q);
        $searchTeachers = collect();
        $searchStudents = collect();
        if ($search !== '') {
            $needle = mb_strtolower($search);
            if ($user->isRole('admin', 'koordinator')) {
                $searchTeachers = $teacherGroups->filter(function ($group) use ($needle) {
                    $teacher = $group['teacher'];
                    return str_contains(mb_strtolower($teacher->name), $needle)
                        || ($teacher->nip && str_contains(mb_strtolower($teacher->nip), $needle));
                })->take(10)->values();
            }
            $searchStudents = $allRows->filter(function ($row) use ($needle) {
                $placement = $row['placement'];
                return str_contains(mb_strtolower($placement->student->user->name), $needle)
                    || str_contains(mb_strtolower($placement->student->nis), $needle)
                    || ($placement->student->nisn && str_contains(mb_strtolower($placement->student->nisn), $needle));
            })->take(20)->values();
        }

        return view('timeline.index', compact(
            'periods', 'chosen', 'teachers', 'teacherDirectory', 'selectedTeacher', 'selectedStudentRow',
            'studentRows', 'search', 'searchTeachers', 'searchStudents'
        ));
    }

    public function update(Request $request, TimelineEvent $event)
    {
        return $this->retired();
    }

    public function store(Request $request)
    {
        return $this->retired();
    }

    public function complete(Request $request, TimelineEvent $event)
    {
        return $this->retired();
    }

    private function retired(): never
    {
        abort(410, 'Tahapan dan konfirmasi manual telah digantikan oleh jadwal PKL berbasis tanggal.');
    }

    private function dateSchedule(Placement $placement, ?InternshipRoster $roster): array
    {
        $startsAt = $roster?->internship_starts_at;
        $endsAt = $roster?->internship_ends_at;
        $state = $roster?->lifecycleState();
        return [
            'roster' => $roster,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'return_at' => $endsAt?->copy()->addDay(),
            'state' => $state ?: 'unscheduled',
            'label' => InternshipRoster::lifecycleLabels()[$state] ?? 'Belum dijadwalkan',
            'source' => $roster?->scheduleSourceLabel() ?? 'Roster belum terhubung',
            'editor' => $roster?->updater?->name,
        ];
    }

    private function paginateCollection(Collection $items, int $perPage, string $pageName, Request $request): LengthAwarePaginator
    {
        $page = max(1, $request->integer($pageName, 1));
        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $pageName]
        );
    }
}
