<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TimelineEvent extends Model {
 protected $fillable=['internship_period_id','title','description','controller_role','scope','progress_source','required_count','dependency_event_id','starts_at','ends_at','completed_by','completed_at'];
 protected function casts():array{return ['starts_at'=>'date','ends_at'=>'date','completed_at'=>'datetime','required_count'=>'integer'];}
 protected static function booted():void{static::creating(function(self $event):void{
  $event->scope=$event->scope?:($event->controller_role==='koordinator'?'period':'placement');
  $event->progress_source=$event->progress_source?:'manual';
  $event->required_count=$event->required_count?:1;
 });}
 public function period(){return $this->belongsTo(InternshipPeriod::class,'internship_period_id');}
 public function completer(){return $this->belongsTo(User::class,'completed_by');}
 public function completions(){return $this->hasMany(TimelineEventCompletion::class);}
 public function dependency(){return $this->belongsTo(self::class,'dependency_event_id');}
 public function dependents(){return $this->hasMany(self::class,'dependency_event_id');}
 public static function controllerLabels():array{return ['koordinator'=>'Koordinator PKL','guru'=>'Guru Pembimbing','siswa'=>'Siswa'];}
 public static function scopeLabels():array{return ['period'=>'Satu periode','placement'=>'Per siswa/penempatan'];}
 public static function progressSourceLabels():array{return [
  'manual'=>'Checklist manual','placement'=>'Data penempatan','journals'=>'Jurnal siswa','monitoring'=>'Catatan monitoring',
  'assessment'=>'Status penilaian','certificate_approval'=>'Validasi sertifikat','certificate_distribution'=>'Distribusi sertifikat',
 ];}
 public function controllerLabel():string{return self::controllerLabels()[$this->controller_role]??$this->controller_role;}
 public function scopeLabel():string{return self::scopeLabels()[$this->scope]??$this->scope;}
 public function progressSourceLabel():string{return self::progressSourceLabels()[$this->progress_source]??$this->progress_source;}
 public function isManual():bool{return $this->progress_source==='manual';}
}
