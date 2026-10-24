<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assessment extends Model {
 protected $fillable=['placement_id','assessor_id','status','notes','final_score','approved_by','approved_at',
  'assessment_template_id','template_snapshot','field_values','industry_assessor','assessed_on','calculated_score','final_score_reason','revision'];
 protected function casts():array{return ['approved_at'=>'datetime','final_score'=>'decimal:2',
  'template_snapshot'=>'array','field_values'=>'array','assessed_on'=>'date','calculated_score'=>'decimal:2','revision'=>'integer'];}
 public function placement(){return $this->belongsTo(Placement::class);}
 public function scores(){return $this->hasMany(AssessmentScore::class);}
 public function assessor(){return $this->belongsTo(User::class,'assessor_id');}
 public function approver(){return $this->belongsTo(User::class,'approved_by');}
 public function evidence(){return $this->hasMany(AssessmentEvidence::class);}
}
