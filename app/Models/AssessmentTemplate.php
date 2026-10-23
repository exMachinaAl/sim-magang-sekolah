<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentTemplate extends Model
{
    protected $fillable = ['name', 'major', 'certificate_heading', 'certificate_statement', 'fields', 'based_on_id', 'created_by', 'is_active'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'is_active' => 'boolean'];
    }

    public function snapshot(): array
    {
        return $this->only(['id', 'name', 'major', 'certificate_heading', 'certificate_statement', 'fields']);
    }
}
