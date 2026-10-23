<?php

namespace App\Http\Controllers;

use App\Models\{Assessment, AssessmentTemplate, Placement};
use App\Support\{Access, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentController
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) $request->input('q', ''));
        $user = $request->user();
        $items = Assessment::with(['placement.student.user', 'placement.company', 'scores.criterion', 'assessor'])
            ->whereIn('placement_id', Access::placements($user)->select('id'))
            ->when($user->isRole('siswa'), fn ($q) => $q->where('status', 'approved'))
            ->latest()->paginate(15);
        $placements = $user->isRole('koordinator') && $search !== ''
            ? Access::placements($user)->with(['student.user', 'period', 'company'])
                ->whereHas('student', fn ($query) => $query->where('nis', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$search.'%')))
                ->latest()->paginate(10, ['*'], 'search_page')->withQueryString() : collect();
        $selectedPlacement = null;
        $selectedTemplate = null;
        $assessment = null;
        $templates = collect();
        if ($user->isRole('koordinator') && $request->integer('placement_id')) {
            $selectedPlacement = Access::placement($user, $request->integer('placement_id'));
            $selectedPlacement->load(['student.user', 'company', 'assessment']);
            $assessment = $selectedPlacement->assessment;
            $templates = AssessmentTemplate::where('major', $selectedPlacement->roster?->major ?? $selectedPlacement->student->major)->where('is_active', true)->latest()->get();
            if ($request->integer('template_id')) {
                $selectedTemplate = $templates->firstWhere('id', $request->integer('template_id'));
                abort_unless($selectedTemplate, 422, 'Template tidak sesuai jurusan atau sudah dinonaktifkan.');
            }
        }
        $snapshot = $assessment?->template_snapshot ?: $selectedTemplate?->snapshot();
        return view('assessments.index', compact('items', 'placements', 'selectedPlacement', 'selectedTemplate', 'assessment', 'templates', 'snapshot', 'search'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isRole('koordinator'), 403);
        $data = $request->validate([
            'placement_id' => ['required', 'integer'],
            'template_id' => ['required', 'integer', 'exists:assessment_templates,id'],
            'revision' => ['required', 'integer', 'min:0'],
            'intent' => ['required', 'in:draft,submitted'],
            'industry_assessor' => ['required', 'string', 'max:150'],
            'assessed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'final_score' => ['nullable', 'required_if:intent,submitted', 'numeric', 'between:0,100'],
            'final_score_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'values' => ['required', 'array'],
        ]);
        Access::placement($request->user(), $data['placement_id']);
        DB::transaction(function () use ($request, $data) {
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
                abort_unless($template->major === ($placement->roster?->major ?? $placement->student->major), 422, 'Template tidak sesuai jurusan siswa pada tahun ajaran penempatan.');
                $snapshot = $template->snapshot();
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
                'notes' => $data['notes'] ?? null, 'status' => $data['intent'],
                'approved_by' => null, 'approved_at' => null, 'revision' => ($existing?->revision ?? 0) + 1,
            ]);
            Audit::record('assessment.'.$data['intent'], $assessment, ['before' => $before, 'values' => $values, 'calculated_score' => $calculated, 'final_score' => $assessment->final_score]);
        });
        return redirect()->route('assessments.index', ['placement_id' => $data['placement_id']])->with('success', 'Nilai industri dan ketentuan template tersimpan.');
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
