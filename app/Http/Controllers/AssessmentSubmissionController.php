<?php

namespace App\Http\Controllers;

use App\Models\{AssessmentSubmission, Placement};
use App\Support\{Access, AssessmentSubmissionSource, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssessmentSubmissionController
{
    public function store(Request $request, AssessmentSubmissionSource $source)
    {
        abort_unless($request->user()->isRole('siswa'), 403);
        $base = $request->validate([
            'placement_id' => ['required','integer','min:1'],
            'industry_assessor' => ['required','string','max:150'],
            'assessed_on' => ['required','date_format:Y-m-d','before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'final_score' => ['required','numeric','between:0,100'],
            'final_score_reason' => ['nullable','string','max:1000'],
            'notes' => ['nullable','string','max:2000'],
            'values' => ['required','array'],
            'photo' => ['nullable','file','mimes:jpg,jpeg,png','max:2048'],
        ]);
        $placement = Access::placement($request->user(), (int) $base['placement_id']);
        $definition = $source->for($placement);
        if ($definition['locked'] || ! $definition['snapshot']) {
            throw ValidationException::withMessages(['placement_id'=>$definition['issue'] ?? 'Template penilaian tidak tersedia.']);
        }
        $expectedTemplateId = $definition['template_id'];
        $expectedSnapshot = $definition['snapshot'];
        $rules = [];
        foreach ($definition['snapshot']['fields'] as $field) {
            $rules['values.'.$field['key']] = $field['type'] === 'score'
                ? ['required','numeric','between:0,100'] : ['required','string','max:600'];
        }
        $values = $request->validate($rules)['values'];
        $normalized = [];
        $calculated = 0;
        foreach ($definition['snapshot']['fields'] as $field) {
            $value = $values[$field['key']];
            $normalized[$field['key']] = $field['type'] === 'score' ? round((float) $value, 2) : $value;
            if ($field['type'] === 'score') $calculated += $normalized[$field['key']] * $field['weight'] / 100;
        }
        $calculated = round($calculated, 2);
        if (abs((float) $base['final_score'] - $calculated) >= 0.005 && empty($base['final_score_reason'])) {
            throw ValidationException::withMessages(['final_score_reason'=>'Jelaskan perbedaan nilai akhir resmi dengan hasil hitung '.$calculated.'.']);
        }
        $photo = $request->file('photo');
        $photoData = ['photo_original_name'=>null,'photo_mime'=>null,'photo_size'=>null,'photo_sha256'=>null,'photo_base64'=>null];
        if ($photo) {
            $bytes = file_get_contents($photo->getRealPath());
            abort_unless($bytes !== false, 422, 'Foto bukti tidak dapat dibaca.');
            $mime = $photo->getMimeType();
            abort_unless(in_array($mime, ['image/jpeg','image/png'], true), 422, 'Format foto bukti tidak didukung.');
            $name = Str::limit(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $photo->getClientOriginalName()))), 180, '');
            $photoData = ['photo_original_name'=>$name,'photo_mime'=>$mime,'photo_size'=>strlen($bytes),
                'photo_sha256'=>hash('sha256',$bytes),'photo_base64'=>base64_encode($bytes)];
        }
        $submission = DB::transaction(function () use ($request, $base, $normalized, $calculated, $photoData, $source, $expectedTemplateId, $expectedSnapshot): AssessmentSubmission {
            $placement = Access::placements($request->user())->whereKey($base['placement_id'])->lockForUpdate()->firstOrFail();
            $definition = $source->for($placement);
            if ($definition['locked'] || ! $definition['snapshot']) {
                throw ValidationException::withMessages(['placement_id'=>$definition['issue'] ?? 'Template penilaian tidak tersedia.']);
            }
            if ((int)$definition['template_id'] !== (int)$expectedTemplateId || $definition['snapshot'] != $expectedSnapshot) {
                throw ValidationException::withMessages(['placement_id'=>'Kriteria penilaian berubah saat setoran diproses. Muat ulang dan periksa kembali sebelum mengirim.']);
            }
            foreach ($placement->assessmentSubmissions()->where('status','submitted')->lockForUpdate()->get() as $previous) {
                $previous->update(['status'=>'superseded']);
            }
            $submission = $placement->assessmentSubmissions()->create($photoData + [
                'assessment_template_id'=>$definition['template_id'], 'submitted_by'=>$request->user()->id,
                'template_snapshot'=>$definition['snapshot'], 'field_values'=>$normalized,
                'industry_assessor'=>$base['industry_assessor'], 'assessed_on'=>$base['assessed_on'],
                'calculated_score'=>$calculated, 'final_score'=>$base['final_score'],
                'final_score_reason'=>$base['final_score_reason'] ?? null, 'notes'=>$base['notes'] ?? null,
                'status'=>'submitted',
            ]);
            Audit::record('assessment_submission.created', $submission, [
                'placement_id'=>$placement->id, 'template_id'=>$definition['template_id'], 'has_photo'=>$photoData['photo_base64'] !== null,
            ]);
            return $submission;
        });
        return redirect()->route('assessments.index', ['period_id'=>$submission->placement->internship_period_id])
            ->with('success','Setoran nilai industri dikirim. Koordinator akan memeriksa dan menerapkannya; ini belum menjadi nilai resmi.');
    }

    public function photo(Request $request, AssessmentSubmission $submission)
    {
        Access::placement($request->user(), $submission->placement_id);
        abort_unless($submission->hasPhoto(), 404);
        $bytes = base64_decode($submission->photo_base64, true);
        abort_unless(is_string($bytes) && strlen($bytes) === $submission->photo_size
            && hash_equals($submission->photo_sha256, hash('sha256', $bytes)), 409, 'Integritas foto bukti gagal.');
        $extension = $submission->photo_mime === 'image/png' ? 'png' : 'jpg';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        return response($bytes, 200, ['Content-Type'=>$submission->photo_mime,
            'Content-Disposition'=>$disposition.'; filename="setoran-nilai-'.$submission->id.'.'.$extension.'"',
            'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff',
            'Content-Security-Policy'=>"sandbox; default-src 'none'; img-src 'self' data:"]);
    }
}
