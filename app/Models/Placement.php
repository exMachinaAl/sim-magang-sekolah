<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Placement extends Model {
 protected $fillable=['student_id','company_id','internship_period_id','internship_roster_id','teacher_id','status'];
 protected static function booted(): void {
  static::saving(function(self $placement){
   if (!$placement->internship_roster_id) return; // Read-only legacy records remain identifiable as unlinked.
   $roster=InternshipRoster::findOrFail($placement->internship_roster_id);
   if (!$roster->student_id || (int)$placement->student_id !== (int)$roster->student_id || (int)$placement->internship_period_id !== (int)$roster->internship_period_id) {
    throw \Illuminate\Validation\ValidationException::withMessages(['roster_ids'=>'Siswa, tag tahun ajaran, dan roster penempatan harus cocok.']);
   }
  });
 }
 public function roster(){return $this->belongsTo(InternshipRoster::class,'internship_roster_id');}
 public function lifecycleState(): string {return $this->roster?->lifecycleState() ?? 'unscheduled';}
 public function lifecycleLabel(): string {return InternshipRoster::lifecycleLabels()[$this->lifecycleState()];}
 public function student(){return $this->belongsTo(Student::class);}
 public function company(){return $this->belongsTo(Company::class);}
 public function period(){return $this->belongsTo(InternshipPeriod::class,'internship_period_id');}
 public function teacher(){return $this->belongsTo(User::class,'teacher_id');}
 public function journals(){return $this->hasMany(Journal::class);}
 public function assessment(){return $this->hasOne(Assessment::class);}
 public function certificate(){return $this->hasOne(Certificate::class);}
 public function monitoring(){return $this->hasMany(MonitoringRecord::class);}
 public function timelineCompletions(){return $this->hasMany(TimelineEventCompletion::class);}
}
