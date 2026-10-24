<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MonitoringRecord extends Model {
 protected $fillable=['placement_id','teacher_id','created_by','visit_date','notes','follow_up','case_status','category','coordinator_id','revision','resolved_at'];
 protected function casts():array{return ['visit_date'=>'date','revision'=>'integer','resolved_at'=>'datetime'];}
 public function placement(){return $this->belongsTo(Placement::class);}
 public function teacher(){return $this->belongsTo(User::class,'teacher_id');}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
 public function coordinator(){return $this->belongsTo(User::class,'coordinator_id');}
 public function updates(){return $this->hasMany(MonitoringUpdate::class);}
 public function latestUpdate(){return $this->hasOne(MonitoringUpdate::class)->latestOfMany();}
 public static function statuses():array{return ['unclassified'=>'Belum diklasifikasi (catatan lama)','open'=>'Terbuka','in_progress'=>'Ditindaklanjuti','resolved'=>'Selesai'];}
 public static function categories():array{return ['introduction'=>'Pengantaran / koordinasi awal','technical'=>'Kendala teknis','attendance'=>'Kehadiran / kedisiplinan','safety'=>'Keselamatan / kondisi kerja','other'=>'Lainnya'];}
 public function caseState():array{return $this->only(['case_status','category','coordinator_id','teacher_id','revision','resolved_at']);}
}
