<?php

namespace App\Http\Controllers;

use App\Models\{AssessmentTemplate, InternshipRoster};
use App\Support\Audit;
use Illuminate\Http\Request;
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
        $template = AssessmentTemplate::create($data + ['created_by' => $request->user()->id]);
        Audit::record('assessment_template.created', $template, ['template' => $template->snapshot()]);
        return redirect()->route('assessment-templates.index')->with('success', 'Template tersimpan. Seluruh siswa jurusan ini dapat menggunakan ketentuan yang sama.');
    }

    public function toggle(Request $request, AssessmentTemplate $template)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $template->update($data);
        Audit::record('assessment_template.availability_changed', $template, $data);
        return back()->with('success', 'Ketersediaan template diperbarui. Penilaian tersimpan tetap menggunakan ketentuan semula.');
    }
}
