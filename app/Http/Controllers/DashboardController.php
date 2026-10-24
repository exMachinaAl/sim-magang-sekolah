<?php
namespace App\Http\Controllers;
use App\Models\{Journal,Assessment,Certificate,Student,InternshipRoster,InternshipPeriod};
use App\Support\Access;
use Illuminate\Http\Request;
class DashboardController {
 public function __invoke(Request $request){
  $data=$request->validate(['period_id'=>'nullable|integer|min:1']);
  $user=$request->user();
  $today=now('Asia/Jakarta')->startOfDay();
  $rosters=Access::rosters($user);
  $placements=Access::placements($user);
  $periods=InternshipPeriod::where(function($q) use ($rosters,$placements) {
   $q->whereIn('id',(clone $rosters)->select('internship_period_id'))
    ->orWhereIn('id',(clone $placements)->select('internship_period_id'));
  })->orderByDesc('academic_year')->orderByDesc('id')->get();
  $chosen=(int)($data['period_id']??$periods->firstWhere('academic_year',InternshipRoster::currentAcademicYear())?->id??$periods->first()?->id);
  abort_if($chosen && !$periods->contains('id',$chosen),404);
  $rosters->where('internship_period_id',$chosen);
  $placements->where('internship_period_id',$chosen);
  // Count unique identities, including linked students without a placement.
  $studentsCount=Student::where(fn($q)=>$q->whereIn('id',(clone $rosters)->select('student_id'))
   ->orWhereIn('id',(clone $placements)->select('student_id')))->count();
  $day=$today->toDateString();
  $phase="CASE WHEN internship_status = 'not_internship' THEN 'not_internship'
   WHEN internship_starts_at IS NULL OR internship_ends_at IS NULL OR internship_ends_at < internship_starts_at THEN 'unscheduled'
   WHEN DATE(internship_starts_at) > ? THEN 'scheduled'
   WHEN DATE(internship_ends_at) >= ? THEN 'pkl' ELSE 'school' END";
  $groups=(clone $rosters)->select('major')->selectRaw('COUNT(*) AS total');
  $phaseLabels=InternshipRoster::lifecycleLabels();
  foreach (array_keys($phaseLabels) as $state) {
   $groups->selectRaw("SUM(CASE WHEN ($phase) = ? THEN 1 ELSE 0 END) AS {$state}_count",[$day,$day,$state]);
  }
  $majorSummaries=$groups->groupBy('major')->orderBy('major')->get();
  $phaseTotals=[];
  foreach (array_keys($phaseLabels) as $state) $phaseTotals[$state]=(int)$majorSummaries->sum($state.'_count');
  $placementCount=(clone $placements)->count();
  $ids=(clone $placements)->select('id');
  $journalSummaries=(clone $placements)->with('student.user')
   ->withCount(['journals','journals as approved_journals_count'=>fn($q)=>$q->where('status','approved'),
    'journals as submitted_journals_count'=>fn($q)=>$q->where('status','submitted'),
    'journals as revision_journals_count'=>fn($q)=>$q->whereIn('status',['revision','rejected']),
    'journals as industry_validated_count'=>fn($q)=>$q->whereColumn('validated_revision','revision')])
   ->withMax('journals','activity_date')->withMax('journals','updated_at')
   ->orderByDesc('journals_max_updated_at')->take(5)->get();
  return view('dashboard.index',[
   'studentProfile'=>$user->isRole('siswa') ? $user->student : null,
   'studentRosters'=>$user->isRole('siswa') ? (clone $rosters)->get() : collect(),
   'studentPlacements'=>$user->isRole('siswa') ? (clone $placements)->with(['company','period'])->latest()->get() : collect(),
   'placementsCount'=>$placementCount,
   'studentsCount'=>$studentsCount,
   'companiesCount'=>(clone $placements)->distinct()->count('company_id'),
   'periods'=>$periods,'chosen'=>$chosen,'today'=>$today,'phaseLabels'=>$phaseLabels,
   'majorSummaries'=>$majorSummaries,'phaseTotals'=>$phaseTotals,'rosterCount'=>(int)$majorSummaries->sum('total'),
   'pendingJournals'=>Journal::whereIn('placement_id',(clone $ids))->where('status','submitted')->count(),
   'pendingAssessments'=>Assessment::whereIn('placement_id',(clone $ids))->where('status','submitted')->count(),
   'certificatesCount'=>Certificate::whereIn('placement_id',(clone $ids))->count(),
   'pendingApproval'=>$user->isRole('koordinator')?Certificate::where('status','awaiting_approval')->whereIn('placement_id',(clone $ids))->whereHas('approvals',fn($q)=>$q->where('party','coordinator')->where('status','pending'))->count():0,
   'latestJournals'=>$journalSummaries,
   'latestCertificates'=>Certificate::with('placement.student.user')->whereIn('placement_id',(clone $ids))->latest()->take(5)->get(),
  ]);
 }
}
