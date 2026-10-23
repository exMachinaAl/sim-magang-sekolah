<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternshipScheduleBatch extends Model
{
    protected $fillable = [
        'academic_year', 'name', 'scope', 'selected_majors', 'starts_at', 'ends_at',
        'student_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'selected_majors' => 'array',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rosters()
    {
        return $this->hasMany(InternshipRoster::class, 'schedule_batch_id');
    }
}
