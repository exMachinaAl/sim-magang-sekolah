<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalIndustryValidation extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['snapshot'=>'array','scanned_at'=>'datetime']; }
    public function mentor() { return $this->belongsTo(IndustryMentor::class, 'industry_mentor_id'); }
    public function device() { return $this->belongsTo(IndustryDevice::class, 'industry_device_id'); }
}
