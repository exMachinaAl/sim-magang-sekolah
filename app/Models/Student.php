<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Student extends Model {
 protected $fillable=['user_id','nis','nisn','class_name','major','birth_date'];
 protected function casts():array{return ['birth_date'=>'date'];}
 public function user(){return $this->belongsTo(User::class);}
 public function placements(){return $this->hasMany(Placement::class);}
 public function rosters(){return $this->hasMany(InternshipRoster::class);}
}
