<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AssessmentEvidence extends Model
{
    protected $table='assessment_evidence';
    protected $guarded=['id'];
    protected $hidden=['file_base64'];
    protected function casts(): array { return ['assessment_revision'=>'integer','size'=>'integer']; }
    public function assessment(){return $this->belongsTo(Assessment::class);}
    public function uploader(){return $this->belongsTo(User::class,'uploaded_by');}
    protected static function booted(): void
    {
        static::updating(fn()=>throw new \LogicException('Bukti nilai tidak boleh ditimpa. Unggah bukti tambahan dengan catatan.'));
        static::deleting(fn()=>throw new \LogicException('Bukti nilai tidak boleh dihapus melalui aplikasi.'));
    }
}
