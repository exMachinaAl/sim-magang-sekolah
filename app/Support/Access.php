<?php
namespace App\Support;
use App\Models\Placement;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
class Access {
 public static function rosters(User $user):Builder {
  $q=\App\Models\InternshipRoster::query();
  if($user->isRole('admin','koordinator')) return $q;
  if($user->isRole('siswa')) return $q->whereHas('student',fn($s)=>$s->where('user_id',$user->id));
  if($user->isRole('guru')) return $q->whereHas('placement',fn($p)=>$p->where('teacher_id',$user->id)
   ->whereColumn('placements.student_id','internship_rosters.student_id')->whereColumn('placements.internship_period_id','internship_rosters.internship_period_id'));
  return $q->whereRaw('1 = 0');
 }
 public static function placements(User $user):Builder {
  $q=Placement::query();
  if($user->isRole('admin','koordinator')) return $q;
  if($user->isRole('guru')) return $q->where('teacher_id',$user->id);
  if($user->isRole('siswa')) return $q->whereHas('student',fn($x)=>$x->where('user_id',$user->id));
  return $q->whereRaw('1 = 0');
 }
 public static function placement(User $user,int $id):Placement {
  return self::placements($user)->findOrFail($id);
 }
 public static function certificates(User $user):Builder {
  return Certificate::query()->whereIn('placement_id',self::placements($user)->select('id'));
 }
}
