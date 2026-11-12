<?php

namespace App\Http\Controllers;

use App\Models\{Assessment, AssessmentSubmission, AssessmentTemplate, AssessmentTemplateDefault, InternshipPeriod, InternshipRoster, Placement};
use App\Support\{Access, AssessmentSubmissionSource, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentController
{
    public function index(Request $request, AssessmentSubmissionSource $submissionSource)
    {
        $filters=$request->validate(['q'=>['nullable','string','max:100'], 'period_id'=>'nullable|integer|min:1',
            'major'=>'nullable|string|max:150','placement_id'=>'nullable|integer|min:1','template_id'=>'nullable|integer|min:1',
            'submission_id'=>'nullable|integer|min:1','page'=>'nullable|integer|min:1','search_page'=>'nullable|integer|min:1',
            'submission_page'=>'nullable|integer|min:1']);
        $search = trim((string) $request->input('q', ''));
        $user = $request->user();
        if ($user->isRole('koordinator') && ! empty($filters['placement_id'])) {
            Access::placement($user, (int) $filters['placement_id']);
            return redirect()->route('assessments.student', array_filter([
                'placement'=>$filters['placement_id'], 'template_id'=>$filters['template_id'] ?? null,
                'submission_id'=>$filters['submission_id'] ?? null,
            ]));
        }
        $base=Access::placements($user);
        $periods=InternshipPeriod::whereIn('id',(clone $base)->select('internship_period_id'))->forSelection()->get();
        $periodId=$request->has('period_id')?($filters['period_id']??null):InternshipPeriod::defaultFrom($periods)?->id;
        $majors=InternshipRoster::whereIn('id',(clone $base)->select('internship_roster_id'))->select('major')->distinct()->orderBy('major')->pluck('major');
        $scope=(clone $base)->when($periodId,fn($q,$id)=>$q->where('internship_period_id',$id))
            ->when($filters['major']??null,fn($q,$major)=>$q->whereHas('roster',fn($r)=>$r->where('major',$major)))
            ->when($search!=='',fn($q)=>$q->whereHas('student',fn($s)=>$s->where(function($s) use($search) {
                $s->where('nis','like','%'.$search.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$search.'%'));
            })));
        $items = Assessment::with(['placement.student.user', 'placement.company','placement.roster','placement.period', 'scores.criterion', 'assessor'])
            ->withCount('evidence')->whereIn('placement_id',(clone $scope)->select('id'))
            ->when($user->isRole('siswa'), fn ($q) => $q->where('status', 'approved'))
            ->latest('id')->paginate(15)->withQueryString();
        $placements = $user->isRole('koordinator')
            ? (clone $scope)->with(['student.user', 'period', 'company','roster','assessment','certificate'])
                ->withCount(['assessmentSubmissions as pending_submission_count'=>fn($q)=>$q->where('status','submitted')])
                ->latest()->paginate(10, ['*'], 'search_page')->withQueryString() : collect();
        $submissions = $user->isRole('koordinator')
            ? AssessmentSubmission::withoutPhotoData()->whereIn('placement_id', (clone $scope)->select('id'))->where('status','submitted')
                ->with(['placement.student.user','placement.roster','placement.period','placement.company','placement.assessment','submitter'])
                ->latest('id')->paginate(10, ['*'], 'submission_page')->withQueryString() : collect();
        $studentSubmissionPlacements = collect();
        $studentSubmissionSources = [];
        $studentLatestSubmissions = collect();
        if ($user->isRole('siswa')) {
            $studentSubmissionPlacements = (clone $scope)->with(['student.user','period','company','roster','assessment'])->latest('id')->get();
            foreach ($studentSubmissionPlacements as $placement) {
                $studentSubmissionSources[$placement->id] = $submissionSource->for($placement);
            }
            $studentLatestSubmissions = AssessmentSubmission::withoutPhotoData()->whereIn('placement_id', $studentSubmissionPlacements->pluck('id'))
                ->latest('id')->get()->unique('placement_id')->keyBy('placement_id');
        }
        return view('assessments.index', compact('items', 'placements', 'submissions', 'studentSubmissionPlacements',
            'studentSubmissionSources', 'studentLatestSubmissions', 'search','periods','periodId','majors'));
    }

    public function showStudent(Request $request, Placement $placement)
    {
        abort_unless($request->user()->isRole('admin','koordinator'),403);
        $data=$request->validate(['template_id'=>'nullable|integer|min:1','submission_id'=>'nullable|integer|min:1']);
        $placement=Access::placement($request->user(),$placement->id);
        $placement->load(['student.user','company','roster','period','assessment.assessor','assessment.approver',
            'assessment.scores.criterion','certificate']);
        $assessment=$placement->assessment;
        $defaultTemplateId=$placement->roster
            ? AssessmentTemplateDefault::where('major',$placement->roster->major)->value('assessment_template_id') : null;
        $templates=$placement->roster
            ? AssessmentTemplate::where('major',$placement->roster->major)->where('is_active',true)
                ->when($defaultTemplateId,fn($query,$id)=>$query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END',[$id]))
                ->latest('id')->get() : collect();
        $submissions=AssessmentSubmission::withoutPhotoData()->where('placement_id',$placement->id)
            ->with(['submitter','applier'])->latest('id')->get();
        $selectedSubmission=null;
        $submissionIssue=null;
        if ($data['submission_id']??null) {
            $selectedSubmission=$submissions->firstWhere('id',(int)$data['submission_id']);
            abort_unless($selectedSubmission,404);
        } else {
            $selectedSubmission=$submissions->firstWhere('status','submitted');
        }
        $selectedTemplate=null;
        if ($assessment) {
            if ($selectedSubmission && $selectedSubmission->status==='submitted'
                && (int)$assessment->assessment_template_id!==(int)$selectedSubmission->assessment_template_id) {
                $submissionIssue='Kriteria setoran siswa berbeda dari template penilaian yang sudah digunakan.';
            }
        } elseif ($data['template_id']??null) {
            $selectedTemplate=$templates->firstWhere('id',(int)$data['template_id']);
            abort_unless($selectedTemplate,422,'Template tidak sesuai jurusan atau sudah dinonaktifkan.');
        } elseif ($selectedSubmission?->status==='submitted') {
            $selectedTemplate=$templates->firstWhere('id',$selectedSubmission->assessment_template_id);
            if (!$selectedTemplate) $submissionIssue='Template setoran tidak lagi aktif/kompatibel. Periksa sumber dan input manual.';
        } else {
            $selectedTemplate=$templates->firstWhere('id',$defaultTemplateId)??$templates->first();
        }
        $snapshot=$assessment?->template_snapshot?:$selectedTemplate?->snapshot();
        if ($selectedSubmission?->status==='submitted' && !$submissionIssue && $snapshot!=$selectedSubmission->template_snapshot) {
            $submissionIssue='Snapshot kriteria setoran tidak sama dengan form resmi. Nilai tidak diterapkan otomatis.';
        }
        $evidenceItems=$assessment?->evidence()->select('id','assessment_id','uploaded_by','assessment_revision','assessment_status',
            'original_name','mime','size','sha256','note','created_at')->with('uploader')->latest('id')->get()??collect();
        $today=now('Asia/Jakarta')->startOfDay();
        $endsAt=$placement->roster?->internship_ends_at?->copy()->startOfDay();
        $startsAt=$placement->roster?->internship_starts_at?->copy()->startOfDay();
        $finished=$endsAt?->lte($today)??false;
        $daysSinceEnd=$finished?$endsAt->diffInDays($today):null;
        $daysUntilEnd=$endsAt&&!$finished?$today->diffInDays($endsAt):null;
        return view('assessments.show',compact('placement','assessment','templates','defaultTemplateId','selectedTemplate',
            'selectedSubmission','submissionIssue','snapshot','submissions','evidenceItems','today','startsAt','endsAt',
            'finished','daysSinceEnd','daysUntilEnd'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isRole('koordinator'), 403);
        $finalize = $request->boolean('finalize');
        $finalScoreRequired = $finalize || $request->input('intent') === 'submitted';
        $data = $request->validate([
            'placement_id' => ['required', 'integer'],
            'template_id' => ['required', 'integer', 'exists:assessment_templates,id'],
            'submission_id' => ['nullable','integer','exists:assessment_submissions,id'],
            'revision' => ['required', 'integer', 'min:0'],
            'intent' => ['required', 'in:draft,submitted'],
            'finalize' => ['nullable', 'boolean'],
            'industry_assessor' => ['required', 'string', 'max:150'],
            'assessed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'final_score' => [$finalScoreRequired ? 'required' : 'nullable', 'numeric', 'between:0,100'],
            'final_score_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'values' => ['required', 'array'],
        ]);
        Access::placement($request->user(), $data['placement_id']);
        DB::transaction(function () use ($request, $data, $finalize) {
            $placement = Placement::whereKey($data['placement_id'])->lockForUpdate()->firstOrFail();
            $existing = $placement->assessment;
            abort_if($existing?->status === 'approved', 403, 'Penilaian yang disahkan dikunci.');
            if (($existing?->revision ?? 0) !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'Penilaian telah diubah koordinator lain. Muat ulang sebelum menyimpan.']);
            }
            if ($existing && ! $existing->template_snapshot) {
                throw ValidationException::withMessages(['template_id' => 'Penilaian lama tetap disimpan dengan skema asal; tidak dikonversi otomatis.']);
            }
            if ($existing) {
                abort_unless((int) $existing->assessment_template_id === (int) $data['template_id'], 422, 'Template penilaian yang sudah dibuat tidak dapat diganti.');
                $snapshot = $existing->template_snapshot;
            } else {
                $template = AssessmentTemplate::whereKey($data['template_id'])->where('is_active', true)->firstOrFail();
                if (!$placement->roster || (int)$placement->roster->student_id!==(int)$placement->student_id
                    || (int)$placement->roster->internship_period_id!==(int)$placement->internship_period_id) {
                    throw ValidationException::withMessages(['placement_id'=>'Hubungan roster/penempatan belum sesuai. Perbaiki melalui manajemen sebelum membuat penilaian.']);
                }
                abort_unless($template->major === $placement->roster->major, 422, 'Template tidak sesuai jurusan siswa pada tahun ajaran penempatan.');
                $snapshot = $template->snapshot();
            }
            $submission = null;
            if (! empty($data['submission_id'])) {
                $submission = AssessmentSubmission::whereKey($data['submission_id'])->where('placement_id',$placement->id)
                    ->where('status','submitted')->lockForUpdate()->firstOrFail();
                abort_unless((int)$submission->assessment_template_id === (int)$data['template_id']
                    && $submission->template_snapshot == $snapshot, 422,
                    'Kriteria setoran siswa tidak kompatibel dengan template penilaian resmi.');
            }
            $rules = [];
            foreach ($snapshot['fields'] as $field) {
                $rules['values.'.$field['key']] = $field['type'] === 'score'
                    ? ['required', 'numeric', 'between:0,100'] : ['required', 'string', 'max:600'];
            }
            $validated = $request->validate($rules);
            $values = [];
            $calculated = 0;
            foreach ($snapshot['fields'] as $field) {
                $value = $validated['values'][$field['key']];
                $values[$field['key']] = $field['type'] === 'score' ? round((float) $value, 2) : $value;
                if ($field['type'] === 'score') $calculated += $values[$field['key']] * $field['weight'] / 100;
            }
            $calculated = round($calculated, 2);
            if (isset($data['final_score']) && abs((float) $data['final_score'] - $calculated) >= 0.005 && empty($data['final_score_reason'])) {
                throw ValidationException::withMessages(['final_score_reason' => 'Catat alasan perbedaan nilai akhir resmi dengan hasil hitung '.$calculated.' (misalnya pembulatan pada dokumen industri).']);
            }
            $before = $existing?->only(['field_values', 'final_score', 'industry_assessor', 'status', 'revision']);
            $assessment = Assessment::updateOrCreate(['placement_id' => $placement->id], [
                'assessment_template_id' => $data['template_id'], 'template_snapshot' => $snapshot,
                'field_values' => $values, 'industry_assessor' => $data['industry_assessor'],
                'assessed_on' => $data['assessed_on'], 'assessor_id' => $request->user()->id,
                'calculated_score' => $calculated, 'final_score' => $data['final_score'] ?? null,
                'final_score_reason' => $data['final_score_reason'] ?? null,
                'notes' => $data['notes'] ?? null, 'status' => $finalize ? 'approved' : $data['intent'],
                'approved_by' => $finalize ? $request->user()->id : null,
                'approved_at' => $finalize ? now() : null, 'revision' => ($existing?->revision ?? 0) + 1,
            ]);
            if ($submission) {
                $submission->update(['status'=>'applied','applied_by'=>$request->user()->id,'applied_at'=>now()]);
                Audit::record('assessment_submission.applied', $submission, ['assessment_id'=>$assessment->id,'coordinator_adjusted_values'=>$values != $submission->field_values]);
            }
            Audit::record('assessment.'.($finalize?'approved':$data['intent']), $assessment, ['before' => $before, 'values' => $values, 'calculated_score' => $calculated,
                'final_score' => $assessment->final_score, 'submission_id'=>$submission?->id,'validation_checkbox'=>$finalize]);
            if ($finalize) {
                Audit::record('assessment.reviewed',$assessment,['decision'=>'approved','revision'=>$assessment->revision,'method'=>'validation_checkbox']);
            }
        });
        if ($finalize) {
            return redirect()->route('certificates.prepare',$data['placement_id'])
                ->with('success','Nilai disimpan dan disahkan. Pilih desain serta lengkapi data sebelum membuat sertifikat.');
        }
        return redirect()->route('assessments.student', $data['placement_id'])->with('success', 'Nilai industri dan ketentuan template tersimpan.');
    }

    public function weights(Request $request)
    {
        abort(410, 'Bobot sekarang diatur melalui template penilaian per jurusan.');
    }

    public function approve(Request $request, Assessment $assessment)
    {
        abort_unless($request->user()->isRole('koordinator'), 403);
        $data = $request->validate(['decision' => ['required', 'in:approved,revision'], 'revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $assessment) {
            Placement::whereKey($assessment->placement_id)->lockForUpdate()->firstOrFail();
            $locked = Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'submitted' && $locked->revision === (int) $data['revision'], 422, 'Penilaian telah berubah. Muat ulang halaman.');
            abort_if($data['decision'] === 'approved' && $locked->final_score === null, 422, 'Nilai akhir resmi belum diisi.');
            $locked->update(['status' => $data['decision'], 'approved_by' => $data['decision'] === 'approved' ? $request->user()->id : null,
                'approved_at' => $data['decision'] === 'approved' ? now() : null, 'revision' => $locked->revision + 1]);
            Audit::record('assessment.reviewed', $locked, $data);
        });
        return back()->with('success', 'Validasi nilai tersimpan.');
    }
}
