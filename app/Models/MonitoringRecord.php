<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MonitoringRecord extends Model {
 protected $fillable=['placement_id','teacher_id','visit_date','notes','follow_up'];
 protected function casts():array{return ['visit_date'=>'date'];}
 public function placement(){return $this->belongsTo(Placement::class);}
 public function teacher(){return $this->belongsTo(User::class,'teacher_id');}
}
