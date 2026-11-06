<?php

namespace App\Http\Controllers;

use App\Models\{AssessmentTemplate, AssessmentTemplateDefault, InternshipRoster};
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentTemplateController
{
    public function index(Request $request)
    {
        $copy = $request->integer('copy') ? AssessmentTemplate::findOrFail($request->integer('copy')) : null;
        return view('assessments.templates', [
            'templates' => AssessmentTemplate::orderBy('major')->latest('id')->paginate(20),
            'majors' => InternshipRoster::officialMajors(), 'copy' => $copy,
            'defaultIds' => AssessmentTemplateDefault::pluck('assessment_template_id', 'major'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'major' => ['required', Rule::in(InternshipRoster::officialMajors())],
            'certificate_heading' => ['required', 'string', 'max:100'],
            'certificate_statement' => ['required', 'string', 'max:400'],
            'based_on_id' => ['nullable', 'exists:assessment_templates,id'],
            'is_default' => ['nullable', 'boolean'],
            'fields' => ['required', 'array', 'min:1', 'max:12'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.description' => ['required', 'string', 'max:600'],
            'fields.*.type' => ['required', Rule::in(['score', 'text'])],
            'fields.*.weight' => ['required', 'integer', 'between:0,100'],
        ]);
        $fields = collect($data['fields'])->values();
        if ($fields->where('type', 'score')->sum('weight') !== 100
            || $fields->contains(fn ($field) => ($field['type'] === 'text' && (int) $field['weight'] !== 0)
                || ($field['type'] === 'score' && (int) $field['weight'] < 1))) {
            throw ValidationException::withMessages(['fields' => 'Total bobot aspek angka harus 100%. Aspek teks memiliki bobot 0%.']);
        }
        $data['fields'] = $fields->map(fn ($field, $index) => [
            'key' => 'field_'.($index + 1), 'label' => $field['label'],
            'description' => $field['description'], 'type' => $field['type'], 'weight' => (int) $field['weight'],
        ])->all();
        $makeDefault = $request->boolean('is_default') || ! AssessmentTemplateDefault::where('major', $data['major'])->exists();
        unset($data['is_default']);
        $template = DB::transaction(function () use ($data, $request, $makeDefault) {
            $template = AssessmentTemplate::create($data + ['created_by' => $request->user()->id]);
            if ($makeDefault) {
                AssessmentTemplateDefault::updateOrCreate(['major' => $template->major], [
                    'assessment_template_id' => $template->id,
                    'updated_by' => $request->user()->id,
                ]);
            }
            Audit::record('assessment_template.created', $template, [
                'template' => $template->snapshot(),
                'is_default' => $makeDefault,
            ]);

            return $template;
        });
        return redirect()->route('assessment-templates.index')->with('success', $makeDefault
            ? 'Template tersimpan dan menjadi default jurusan. Penilaian lama tidak berubah.'
            : 'Template tersimpan sebagai pilihan tambahan. Default jurusan tidak berubah.');
    }

    public function toggle(Request $request, AssessmentTemplate $template)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($template, $data, $request) {
            $template->update($data);
            $default = AssessmentTemplateDefault::where('major', $template->major)->lockForUpdate()->first();
            if ($data['is_active'] && ! $default) {
                AssessmentTemplateDefault::create([
                    'major' => $template->major,
                    'assessment_template_id' => $template->id,
                    'updated_by' => $request->user()->id,
                ]);
            } elseif (! $data['is_active'] && $default?->assessment_template_id === $template->id) {
                $replacement = AssessmentTemplate::where('major', $template->major)->where('is_active', true)
                    ->where('id', '!=', $template->id)->latest('id')->first();
                if ($replacement) {
                    $default->update(['assessment_template_id' => $replacement->id, 'updated_by' => $request->user()->id]);
                } else {
                    $default->delete();
                }
            }
            Audit::record('assessment_template.availability_changed', $template, $data + [
                'default_template_id' => AssessmentTemplateDefault::where('major', $template->major)->value('assessment_template_id'),
            ]);
        });
        return back()->with('success', 'Ketersediaan template diperbarui. Penilaian tersimpan tetap menggunakan ketentuan semula.');
    }

    public function makeDefault(Request $request, AssessmentTemplate $template)
    {
        DB::transaction(function () use ($request, $template): void {
            $template = AssessmentTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_unless($template->is_active, 422, 'Hanya template aktif yang dapat dijadikan default jurusan.');

            AssessmentTemplateDefault::query()->updateOrCreate(
                ['major' => $template->major],
                ['assessment_template_id' => $template->id, 'updated_by' => $request->user()->id]
            );
            Audit::record('assessment_template.default_changed', $template, [
                'major' => $template->major,
                'default_template_id' => $template->id,
            ]);
        });

        return back()->with('success', 'Template default jurusan diperbarui. Penilaian yang sudah tersimpan tidak berubah.');
    }
}
