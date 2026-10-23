<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CertificateApproval extends Model {
 protected $fillable=['certificate_id','party','approver_id','status','note','decided_at'];
 protected function casts():array{return ['decided_at'=>'datetime'];}
}
