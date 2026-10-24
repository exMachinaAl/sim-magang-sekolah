<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificatePrintTemplate extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['front_docx', 'back_docx'];
    protected function casts(): array { return ['settings'=>'array', 'score_mapping'=>'array']; }
    public function assessmentTemplate() { return $this->belongsTo(AssessmentTemplate::class); }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Template cetak tersimpan tidak boleh ditimpa; buat versi baru.'));
        static::deleting(fn () => throw new \LogicException('Template cetak tidak boleh dihapus karena digunakan oleh arsip.'));
    }
}
