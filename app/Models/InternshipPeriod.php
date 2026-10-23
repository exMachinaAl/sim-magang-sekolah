<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InternshipPeriod extends Model {
 protected $fillable=['name','academic_year','starts_at','ends_at','is_active'];
 protected function casts():array{return ['starts_at'=>'date','ends_at'=>'date','is_active'=>'boolean'];}
 public function placements(){return $this->hasMany(Placement::class);}
 public function events(){return $this->hasMany(TimelineEvent::class);}
 public function rosters(){return $this->hasMany(InternshipRoster::class);}
 public static function forAcademicYear(string $year): self {
  \Illuminate\Support\Facades\Validator::make(['academic_year'=>$year],['academic_year'=>['required','regex:/^\d{4}\/\d{4}$/']])->validate();
  if ((int)substr($year,5)!==(int)substr($year,0,4)+1) throw \Illuminate\Validation\ValidationException::withMessages(['academic_year'=>'Tahun ajaran harus berurutan, contoh 2026/2027.']);
  return self::firstOrCreate(['academic_year'=>$year],['name'=>$year,'is_active'=>true]);
 }
}
