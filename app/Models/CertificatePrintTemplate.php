<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificatePrintTemplate extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['front_docx', 'back_docx', 'front_pdf', 'back_pdf'];
    protected function casts(): array { return ['settings'=>'array', 'score_mapping'=>'array', 'pdf_layout'=>'array']; }
    public function renderer(): string { return $this->front_pdf_hash ? 'pdf_background' : ($this->front_hash ? 'docx' : 'builtin'); }
    public function formatLabel(): string { return match($this->renderer()) {
        'pdf_background'=>'PDF berdekorasi', 'docx'=>'DOCX (ekstensi LibreOffice)', default=>'PDF bawaan',
    }; }
    public function assessmentTemplate() { return $this->belongsTo(AssessmentTemplate::class); }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Template cetak tersimpan tidak boleh ditimpa; buat versi baru.'));
        static::deleting(fn () => throw new \LogicException('Template cetak tidak boleh dihapus karena digunakan oleh arsip.'));
    }
}
