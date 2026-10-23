<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CertificateMovement extends Model {
 protected $fillable=['certificate_id','from_status','to_status','actor_id','recipient','note','moved_at'];
 protected function casts():array{return ['moved_at'=>'datetime'];}
 public function actor(){return $this->belongsTo(User::class,'actor_id');}
}
