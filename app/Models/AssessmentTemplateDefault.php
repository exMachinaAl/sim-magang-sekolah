<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentTemplateDefault extends Model
{
    protected $fillable = ['major', 'assessment_template_id', 'updated_by'];

    public function template()
    {
        return $this->belongsTo(AssessmentTemplate::class, 'assessment_template_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
