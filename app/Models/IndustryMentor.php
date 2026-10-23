<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IndustryMentor extends Model
{
    protected $fillable = ['name','company_id','created_by','is_active'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function company() { return $this->belongsTo(Company::class); }
    public function placements() { return $this->belongsToMany(Placement::class); }
    public function devices() { return $this->hasMany(IndustryDevice::class); }
}
