<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssessmentScore extends Model {
 protected $fillable=['assessment_id','assessment_criterion_id','score'];
 public function criterion(){return $this->belongsTo(AssessmentCriterion::class,'assessment_criterion_id');}
}
