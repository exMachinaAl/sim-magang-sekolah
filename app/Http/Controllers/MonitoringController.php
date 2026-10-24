<?php

namespace App\Http\Controllers;

use App\Models\{InternshipPeriod, InternshipRoster, MonitoringRecord, User};
use App\Support\{Access, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MonitoringController
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100', 'major' => 'nullable|string|max:150',
            'period_id' => 'nullable|integer|min:1', 'placement_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1', 'candidates_page' => 'nullable|integer|min:1',
            'case_status' => ['nullable',Rule::in(array_keys(MonitoringRecord::statuses()))],
            'category' => ['nullable',Rule::in(array_keys(MonitoringRecord::categories()))],
        ]);
        $q = trim($data['q'] ?? '');
        $major = $data['major'] ?? '';
        $periodId = $data['period_id'] ?? null;
        $scope = Access::placements($request->user());
        $periods = InternshipPeriod::whereIn('id', (clone $scope)->select('internship_period_id'))
            ->orderByDesc('academic_year')->get();
        $majors = InternshipRoster::whereIn('id', (clone $scope)->select('internship_roster_id'))
            ->select('major')->distinct()->orderBy('major')->pluck('major');
        $filtered = (clone $scope)
            ->when($periodId, fn ($p) => $p->where('internship_period_id', $periodId))
            ->when($major !== '', fn ($p) => $p->whereHas('roster', fn ($r) => $r->where('major', $major)))
            ->when($q !== '', fn ($p) => $p->whereHas('student', fn ($s) => $s->where(function ($s) use ($q) {
                $s->where('nis', 'like', '%'.$q.'%')->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%'));
            })));

        // Old input is also scoped: a failed form must not expose another teacher's student.
        $selectedId = $data['placement_id'] ?? $request->old('placement_id');
        if (!is_scalar($selectedId) || !ctype_digit((string) $selectedId)) $selectedId = null;
        $selected = $selectedId ? (clone $scope)->with(['student.user', 'roster', 'period', 'teacher'])
            ->findOrFail($selectedId) : null;
        $candidates = (clone $filtered)->with(['student.user', 'roster', 'period'])
            ->when($q === '' && $major === '' && !$periodId, fn ($p) => $p->whereRaw('1 = 0'))
            ->orderBy('id')->paginate(10, ['*'], 'candidates_page')->withQueryString();
        $items = MonitoringRecord::with(['placement.student.user', 'placement.roster', 'placement.period', 'teacher', 'creator','coordinator','latestUpdate'])
            ->whereIn('placement_id', (clone $filtered)->select('id'))
            ->when($selected, fn ($m) => $m->where('placement_id', $selected->id))
            ->when($data['case_status']??null,fn($m,$status)=>$m->where('case_status',$status))
            ->when($data['category']??null,fn($m,$category)=>$m->where('category',$category))
            ->orderByDesc('visit_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('monitoring.index', compact('items', 'candidates', 'selected', 'periods', 'majors', 'q', 'major', 'periodId'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isRole('koordinator', 'guru'), 403);
        $data = $request->validate([
            'placement_id' => 'required|integer|min:1',
            'visit_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            'notes' => 'required|string|min:10|max:5000', 'follow_up' => 'nullable|string|max:3000',
            'category' => ['nullable',Rule::in(array_keys(MonitoringRecord::categories()))],
        ]);
        DB::transaction(function () use ($request, $data) {
            $placement = Access::placements($request->user())->lockForUpdate()->findOrFail($data['placement_id']);
            $record = MonitoringRecord::create(array_replace($data, [
                'created_by' => $request->user()->id,
                'teacher_id' => $placement->teacher_id,
                'category' => $data['category']??'other', 'case_status'=>'open','revision'=>1,
                'coordinator_id'=>$request->user()->isRole('koordinator')?$request->user()->id:null,
            ]));
            Audit::record('monitoring.created', $record);
        });

        return redirect()->route('monitoring.index', ['placement_id' => $data['placement_id']])
            ->with('success', 'Catatan monitoring tersimpan.');
    }

    public function show(Request $request, MonitoringRecord $record)
    {
        Access::placement($request->user(),$record->placement_id);
        $record->load(['placement.student.user','placement.roster','placement.period','placement.teacher','creator','coordinator','teacher']);
        $items=$record->updates()->with('actor')->orderByDesc('id')->paginate(15);
        $coordinators=$request->user()->isRole('koordinator')?User::where('role','koordinator')->where('is_active',true)->orderBy('name')->get(['id','name']):collect();
        return view('monitoring.show',compact('record','items','coordinators'));
    }

    public function update(Request $request, MonitoringRecord $record)
    {
        abort_unless($request->user()->isRole('koordinator'),403);
        $data=$request->validate([
            'revision'=>'required|integer|min:1',
            'case_status'=>['required',Rule::in(['open','in_progress','resolved'])],
            'category'=>['required',Rule::in(array_keys(MonitoringRecord::categories()))],
            'coordinator_id'=>['required','integer',Rule::exists('users','id')->where('role','koordinator')->where('is_active',true)],
            'teacher_id'=>'nullable|integer|min:1','note'=>'required|string|min:10|max:3000',
        ]);
        DB::transaction(function() use($request,$record,$data) {
            $placement=Access::placements($request->user())->whereKey($record->placement_id)->lockForUpdate()->firstOrFail();
            $locked=MonitoringRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->revision===(int)$data['revision'],409,'Kasus sudah diubah koordinator lain. Muat ulang sebelum menyimpan; perubahan Anda belum diterapkan.');
            if (!empty($data['teacher_id']) && ((int)$data['teacher_id']!==(int)$placement->teacher_id
                || !User::whereKey($data['teacher_id'])->where('role','guru')->where('is_active',true)->exists())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['teacher_id'=>'Guru pendamping harus guru aktif yang terkait penempatan siswa; boleh dikosongkan.']);
            }
            // Recheck assignment inside the transaction rather than trusting a stale select option.
            User::whereKey($data['coordinator_id'])->where('role','koordinator')->where('is_active',true)->lockForUpdate()->firstOrFail();
            $before=$locked->caseState();
            $locked->update([
                'case_status'=>$data['case_status'],'category'=>$data['category'],'coordinator_id'=>$data['coordinator_id'],
                'teacher_id'=>$data['teacher_id']??null,'revision'=>$locked->revision+1,
                'resolved_at'=>$data['case_status']==='resolved'?($locked->resolved_at??now()):null,
            ]);
            $event=$locked->updates()->create(['actor_id'=>$request->user()->id,'before_state'=>$before,'after_state'=>$locked->caseState(),'note'=>$data['note']]);
            Audit::record('monitoring.updated',$locked,['update_id'=>$event->id,'before'=>$before,'after'=>$locked->caseState()]);
        });
        return redirect()->route('monitoring.show',$record)->with('success','Tindak lanjut tersimpan. Riwayat dan ID editor tercatat.');
    }
}
