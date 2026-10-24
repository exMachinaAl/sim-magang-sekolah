<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateWordExport extends Model
{
    protected $guarded=['id'];
    protected $hidden=['front_docx','back_docx'];
    protected function casts(): array { return ['template_hashes'=>'array']; }
    protected static function booted(): void
    {
        static::updating(fn()=>throw new \LogicException('Salinan Word versi ini tidak boleh ditimpa.'));
        static::deleting(fn()=>throw new \LogicException('Salinan Word versi ini tidak boleh dihapus.'));
    }
}
