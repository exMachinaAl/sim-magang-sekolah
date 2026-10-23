<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IndustryDevice extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['token_hash'];
    protected function casts(): array { return ['expires_at'=>'datetime','revoked_at'=>'datetime','last_used_at'=>'datetime']; }
    public function mentor() { return $this->belongsTo(IndustryMentor::class, 'industry_mentor_id'); }
}
