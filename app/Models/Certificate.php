<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Certificate extends Model {
 protected $fillable=['placement_id','number','version','status','pickup_at','pickup_location','pickup_requirements','issued_at'];
 protected function casts():array{return ['pickup_at'=>'datetime','issued_at'=>'datetime'];}
 public function placement(){return $this->belongsTo(Placement::class);}
 public function approvals(){return $this->hasMany(CertificateApproval::class);}
 public function approvalEvents(){return $this->hasMany(CertificateApprovalEvent::class);}
 public function movements(){return $this->hasMany(CertificateMovement::class);}
 public static function statuses():array {return [
  'awaiting_approval'=>'Menunggu validasi','revision_required'=>'Perlu revisi persetujuan','awaiting_print'=>'Menunggu pencetakan',
  'printed'=>'Dicetak','sent_to_company'=>'Dikirim ke perusahaan','returned_to_school'=>'Kembali ke sekolah',
  'ready_pickup'=>'Siap diambil','handed_to_student'=>'Diserahkan ke siswa','completed'=>'Selesai'];}
 public function statusLabel():string{return self::statuses()[$this->status]??$this->status;}
}
