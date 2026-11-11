<?php

namespace App\Support;

use App\Models\{AssessmentTemplateDefault, Placement};

class AssessmentSubmissionSource
{
    public function for(Placement $placement): array
    {
        $placement->loadMissing(['roster', 'assessment']);
        if (! $placement->roster) {
            return ['template_id'=>null, 'snapshot'=>null, 'locked'=>false, 'issue'=>'Roster tahun PKL belum terhubung.'];
        }
        if ($placement->assessment?->status === 'approved') {
            return ['template_id'=>$placement->assessment->assessment_template_id, 'snapshot'=>$placement->assessment->template_snapshot,
                'locked'=>true, 'issue'=>'Nilai resmi sudah disahkan dan tidak menerima setoran baru.'];
        }
        if ($placement->assessment?->template_snapshot) {
            return ['template_id'=>$placement->assessment->assessment_template_id, 'snapshot'=>$placement->assessment->template_snapshot,
                'locked'=>false, 'issue'=>null];
        }
        $default = AssessmentTemplateDefault::with('template')->where('major', $placement->roster->major)->first();
        if (! $default?->template?->is_active) {
            return ['template_id'=>null, 'snapshot'=>null, 'locked'=>false,
                'issue'=>'Template penilaian aktif untuk jurusan ini belum ditetapkan koordinator.'];
        }
        return ['template_id'=>$default->template->id, 'snapshot'=>$default->template->snapshot(), 'locked'=>false, 'issue'=>null];
    }
}
