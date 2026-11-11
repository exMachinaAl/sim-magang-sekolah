<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
 use Notifiable;
 protected $fillable=['name','nip','email','password','role','is_active','archived_at','archived_by'];
 protected $hidden=['password','remember_token'];
 protected function casts(): array {return ['password'=>'hashed','is_active'=>'boolean','archived_at'=>'datetime'];}
 public function student(){return $this->hasOne(Student::class);}
 public function archiver(){return $this->belongsTo(User::class,'archived_by');}
 public function isRole(string ...$roles): bool{return in_array($this->role,$roles,true);}
 public function isArchived():bool{return $this->archived_at!==null;}
 public static function activeRoleLabels():array{return ['admin'=>'Admin Sistem','koordinator'=>'Koordinator PKL','guru'=>'Guru Pembimbing','siswa'=>'Siswa'];}
 public static function staffRoleLabels():array{return ['admin'=>'Admin Sistem','koordinator'=>'Koordinator PKL','guru'=>'Guru Pembimbing'];}
 public static function roleLabels():array{return self::activeRoleLabels()+['legacy_industri'=>'Pembimbing Industri (Arsip)','legacy_kepala'=>'Kepala Sekolah (Arsip)','legacy_petugas'=>'Petugas Sertifikat (Arsip)'];}
 public function roleLabel():string{return self::roleLabels()[$this->role]??$this->role;}
}
