<?php
namespace App\Http\Controllers;
use App\Models\{Journal,Assessment,Certificate,Company,Student,InternshipRoster};
use App\Support\Access;
use Illuminate\Http\Request;
class DashboardController {
 public function __invoke(Request $request){
  $user=$request->user();
  $placements=Access::placements($user);
  $ids=clone $placements;
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
   'studentRosters'=>$user->isRole('siswa') && $user->student ? InternshipRoster::where('student_id',$user->student->id)->orderByDesc('academic_year')->get() : collect(),
   'studentPlacements'=>$user->isRole('siswa') ? (clone $placements)->with(['company','period'])->latest()->get() : collect(),
   'placementsCount'=>$placementCount,
   'studentsCount'=>$user->isRole('admin','koordinator') ? Student::count() : $placementCount,
   'companiesCount'=>$user->isRole('admin','koordinator') ? Company::count() : (clone $placements)->distinct()->count('company_id'),
   'pendingJournals'=>Journal::whereIn('placement_id',(clone $ids))->where('status','submitted')->count(),
   'pendingAssessments'=>Assessment::whereIn('placement_id',(clone $ids))->where('status','submitted')->count(),
   'certificatesCount'=>Certificate::whereIn('placement_id',(clone $ids))->count(),
   'pendingApproval'=>$user->isRole('koordinator')?Certificate::where('status','awaiting_approval')->whereIn('placement_id',(clone $ids))->whereHas('approvals',fn($q)=>$q->where('party','coordinator')->where('status','pending'))->count():0,
   'latestJournals'=>$journalSummaries,
   'latestCertificates'=>Certificate::with('placement.student.user')->whereIn('placement_id',(clone $ids))->latest()->take(5)->get(),
  ]);
 }
}
