<?php
namespace App\Http\Controllers;
use App\Models\MonitoringRecord;
use App\Support\{Access,Audit};
use Illuminate\Http\Request;
class MonitoringController {
 public function index(Request $r){
  $items=MonitoringRecord::with(['placement.student.user','teacher'])
    ->whereIn('placement_id',Access::placements($r->user())->select('id'))->latest('visit_date')->paginate(15);
  return view('monitoring.index',['items'=>$items,'placements'=>Access::placements($r->user())->with('student.user')->get()]);
 }
 public function store(Request $r){
  abort_unless($r->user()->isRole('guru'),403);
  $d=$r->validate(['placement_id'=>'required|integer','visit_date'=>'required|date|before_or_equal:today','notes'=>'required|min:10|max:5000','follow_up'=>'nullable|max:3000']);
  $placement=Access::placement($r->user(),$d['placement_id']);
  $record=MonitoringRecord::create($d+['teacher_id'=>$r->user()->id]);Audit::record('monitoring.created',$record);
  return back()->with('success','Catatan monitoring tersimpan.');
 }
}
