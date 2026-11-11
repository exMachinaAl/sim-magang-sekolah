<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentSubmission extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['photo_base64'];

    protected function casts(): array
    {
        return [
            'template_snapshot' => 'array', 'field_values' => 'array',
            'assessed_on' => 'date', 'calculated_score' => 'decimal:2', 'final_score' => 'decimal:2',
            'photo_size' => 'integer', 'applied_at' => 'datetime',
        ];
    }

    public function placement() { return $this->belongsTo(Placement::class); }
    public function template() { return $this->belongsTo(AssessmentTemplate::class, 'assessment_template_id'); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function applier() { return $this->belongsTo(User::class, 'applied_by'); }
    public function hasPhoto(): bool { return $this->photo_sha256 !== null; }
    public function scopeWithoutPhotoData($query)
    {
        return $query->select([
            'id','placement_id','assessment_template_id','submitted_by','applied_by','template_snapshot','field_values',
            'industry_assessor','assessed_on','calculated_score','final_score','final_score_reason','notes','status',
            'photo_original_name','photo_mime','photo_size','photo_sha256','applied_at','created_at','updated_at',
        ]);
    }
    public function statusLabel(): string
    {
        return ['submitted'=>'Menunggu diterapkan','applied'=>'Sudah diterapkan','superseded'=>'Diganti setoran baru'][$this->status] ?? $this->status;
    }

    protected static function booted(): void
    {
        static::updating(function (self $submission): void {
            $allowed = ['status', 'applied_by', 'applied_at', 'updated_at'];
            if (array_diff(array_keys($submission->getDirty()), $allowed)) {
                throw new \LogicException('Isi setoran penilaian siswa tidak boleh ditimpa. Buat setoran baru.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Setoran penilaian siswa tidak boleh dihapus melalui aplikasi.'));
    }
}
