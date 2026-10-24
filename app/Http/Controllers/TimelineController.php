<?php

namespace App\Http\Controllers;

use App\Models\{InternshipPeriod, InternshipRoster, TimelineEvent, User};
use App\Support\Access;
use Illuminate\Http\Request;

class TimelineController
{
    public function index(Request $request)
    {
        $data=$request->validate([
            'period_id'=>'nullable|integer|min:1','roster_id'=>'nullable|integer|min:1',
            'placement_id'=>'nullable|integer|min:1','teacher_id'=>'nullable|integer|min:0',
            'major'=>'nullable|string|max:150','q'=>'nullable|string|max:100',
            'students_page'=>'nullable|integer|min:1','majors_page'=>'nullable|integer|min:1',
        ]);
        $user=$request->user(); $search=trim($data['q']??'');
        $today=now('Asia/Jakarta')->startOfDay();
        $base=Access::rosters($user);
        $selectedRoster=null;
        if ($request->filled('roster_id')) $selectedRoster=(clone $base)->findOrFail($data['roster_id']);
        // Compatibility with existing links, without requiring placement for a timeline.
        if ($request->filled('placement_id')) {
            $placement=Access::placement($user,$data['placement_id']);
            abort_unless($placement->internship_roster_id,404);
            $linked=(clone $base)->findOrFail($placement->internship_roster_id);
            abort_if((int)$linked->student_id!==(int)$placement->student_id || (int)$linked->internship_period_id!==(int)$placement->internship_period_id,404);
            abort_if($selectedRoster && $selectedRoster->id!==$linked->id,404);
            $selectedRoster=$linked;
        }
        $periods=InternshipPeriod::whereIn('id',(clone $base)->select('internship_period_id'))
            ->orderByDesc('academic_year')->orderByDesc('id')->get();
        $chosen=(int)($data['period_id']??$selectedRoster?->internship_period_id
            ??$periods->firstWhere('academic_year',InternshipRoster::currentAcademicYear())?->id??$periods->first()?->id);
        abort_if($chosen && !$periods->contains('id',$chosen),404);
        abort_if($selectedRoster && (int)$selectedRoster->internship_period_id!==$chosen,404);
        $base->where('internship_period_id',$chosen);
        $selectedTeacher=null;
        if ($request->filled('teacher_id')) {
            $teacherId=(int)$data['teacher_id'];
            abort_if($user->isRole('guru') && $teacherId!==$user->id,404);
            if ($teacherId===0) {
                $base->where(fn($q)=>$q->whereDoesntHave('placement')->orWhereHas('placement',fn($p)=>$p->whereNull('teacher_id')));
                $selectedTeacher=(new User())->forceFill(['id'=>0,'name'=>'Tanpa guru pendamping','nip'=>null]);
            } else {
                $base->whereHas('placement',fn($p)=>$p->where('teacher_id',$teacherId));
                abort_unless((clone $base)->exists(),404);
                $selectedTeacher=User::whereKey($teacherId)->where('role','guru')->firstOrFail();
            }
            abort_if($selectedRoster && !(clone $base)->whereKey($selectedRoster->id)->exists(),404);
        }
        if ($user->isRole('siswa') && !$selectedRoster) $selectedRoster=(clone $base)->orderBy('id')->first();
        $selectedMajor=$selectedRoster?->major??($data['major']??null);

        // Count phases in SQL, without hydrating the school's entire roster.
        $day=$today->toDateString();
        $phase="CASE WHEN internship_status = 'not_internship' THEN 'not_internship'
            WHEN internship_starts_at IS NULL OR internship_ends_at IS NULL OR internship_ends_at < internship_starts_at THEN 'unscheduled'
            WHEN DATE(internship_starts_at) > ? THEN 'scheduled'
            WHEN DATE(internship_ends_at) >= ? THEN 'pkl' ELSE 'school' END";
        $groups=(clone $base)->select('major')->selectRaw('COUNT(*) AS total');
        foreach (array_keys(InternshipRoster::lifecycleLabels()) as $state) {
            $groups->selectRaw("SUM(CASE WHEN ($phase) = ? THEN 1 ELSE 0 END) AS {$state}_count",[$day,$day,$state]);
        }
        $majorDirectory=$groups->when($search!=='',fn($q)=>$q->where('major','like','%'.$search.'%'))
            ->groupBy('major')->orderBy('major')->paginate(12,['*'],'majors_page')->withQueryString();

        $showStudents=!$selectedRoster && ($selectedMajor!==null || $search!=='' || $selectedTeacher || $user->isRole('guru'));
        $students=(clone $base)->when($selectedMajor!==null,fn($q)=>$q->where('major',$selectedMajor))
            ->when(!$showStudents,fn($q)=>$q->whereRaw('1=0'));
        if ($search!=='') {
            $like='%'.$search.'%';
            $students->where(fn($q)=>$q->where('name','like',$like)->orWhere('nis','like',$like)->orWhere('nisn','like',$like)
                ->orWhere('major','like',$like)
                ->orWhereHas('student',fn($s)=>$s->where('nis','like',$like)->orWhere('nisn','like',$like)
                    ->orWhereHas('user',fn($u)=>$u->where('name','like',$like)))
                ->orWhereHas('placement.teacher',fn($t)=>$t->where('name','like',$like)->orWhere('nip','like',$like)));
        }
        $studentRows=$students->with(['student.user','placement.company','placement.teacher'])
            ->orderBy('major')->orderBy('class_name')->orderBy('name')->orderBy('id')
            ->paginate(20,['*'],'students_page')->withQueryString();
        $selectedStudentRow=null;
        if ($selectedRoster) {
            $selectedRoster->load(['student.user','placement.company','placement.teacher','updater']);
            $state=$selectedRoster->lifecycleState($today);
            $selectedStudentRow=['roster'=>$selectedRoster,'placement'=>$selectedRoster->placement,'schedule'=>[
                'starts_at'=>$selectedRoster->internship_starts_at,'ends_at'=>$selectedRoster->internship_ends_at,
                'return_at'=>in_array($state,['not_internship','unscheduled'])?null:$selectedRoster->returnToSchoolDate(),
                'state'=>$state,'label'=>InternshipRoster::lifecycleLabels()[$state],
                'source'=>!$selectedRoster->schedule_source && !in_array($state,['unscheduled','not_internship']) ? 'Kontrol PKL (sumber lama)' : $selectedRoster->scheduleSourceLabel(),
                'editor'=>$selectedRoster->updater?->name,'editor_id'=>$selectedRoster->updated_by,
            ]];
        }
        return view('timeline.index',compact('periods','chosen','majorDirectory','selectedMajor','selectedTeacher',
            'selectedStudentRow','studentRows','showStudents','search','today'));
    }

    public function update(Request $request, TimelineEvent $event) { return $this->retired(); }
    public function store(Request $request) { return $this->retired(); }
    public function complete(Request $request, TimelineEvent $event) { return $this->retired(); }
    private function retired(): never
    {
        abort(410,'Tahapan dan konfirmasi manual telah digantikan oleh jadwal PKL berbasis tanggal.');
    }
}
