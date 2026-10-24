<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateDocument extends Model
{
    protected $guarded=['id'];
    protected $hidden=['pdf_base64','rendered_html','front_docx','back_docx'];
    protected function casts(): array { return ['snapshot'=>'array','version'=>'integer','issued_at'=>'datetime']; }
    public function certificate() { return $this->belongsTo(Certificate::class); }
    public function wordExport() { return $this->hasOne(CertificateWordExport::class); }

    protected static function booted(): void
    {
        static::updating(function (self $document) {
            // The only allowed update is first issuance of a captured proposal.
            if ($document->getOriginal('issued_at') || array_diff(array_keys($document->getDirty()),['issued_at','updated_at'])) {
                throw new \LogicException('Arsip sertifikat tidak boleh ditimpa. Buat versi baru.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Arsip sertifikat tidak boleh dihapus melalui aplikasi.'));
    }
}
