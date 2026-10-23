<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model {
 const UPDATED_AT=null;
 protected $fillable=['user_id','action','subject_type','subject_id','context','ip_address'];
 protected function casts():array{return ['context'=>'array'];}
 public function actor(){return $this->belongsTo(User::class,'user_id');}
}
