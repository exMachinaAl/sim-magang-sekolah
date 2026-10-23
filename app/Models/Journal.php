<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Journal extends Model {
 protected $fillable=['placement_id','activity_date','activity','outcome','obstacles','status','review_note','reviewed_by','reviewed_at',
 'work_area','starts_time','ends_time','equipment_notes','industry_score','revision','reviewed_revision','validated_revision'];
 protected function casts():array{return ['activity_date'=>'date','reviewed_at'=>'datetime','industry_score'=>'decimal:2','revision'=>'integer','reviewed_revision'=>'integer','validated_revision'=>'integer'];}
 public function placement(){return $this->belongsTo(Placement::class);}
 public function attachments(){return $this->hasMany(JournalAttachment::class);}
 public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');}
 public function events(){return $this->hasMany(JournalEvent::class);}
 public function industryValidations(){return $this->hasMany(JournalIndustryValidation::class);}
 public function industryValidated():bool{return $this->validated_revision === $this->revision;}
 public function scoreCategory():?string {
  if ($this->industry_score === null) return null;
  $score=(float)$this->industry_score;
  return match(true){$score>=96=>'A',$score>=91=>'A-',$score>=86=>'B+',$score>=81=>'B',$score>=75=>'B-',default=>'Di bawah 75'};
 }
 public function contentSnapshot():array {
  return $this->only(['activity','work_area','starts_time','ends_time','equipment_notes','outcome','obstacles','industry_score','revision'])
   + ['activity_date'=>$this->activity_date->toDateString(),'attachment_ids'=>$this->attachments()->pluck('id')->all()];
 }
 public static function statuses():array{return ['draft'=>'Draf','submitted'=>'Tercatat','revision'=>'Catatan revisi (arsip)','approved'=>'Ditinjau sekolah','rejected'=>'Penolakan (arsip)'];}
 public function statusLabel():string{return self::statuses()[$this->status]??$this->status;}
}
