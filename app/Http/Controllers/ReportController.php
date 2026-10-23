<?php
namespace App\Http\Controllers;
use App\Models\{Placement,Journal,Assessment,Certificate,AuditLog};
use Illuminate\Http\Request;
class ReportController {
 public function index(){return view('reports.index',['audits'=>AuditLog::with('actor')->latest()->paginate(25)]);}
 public function export(Request $r,string $type){
  abort_unless(in_array($type,['placements','journals','assessments','certificates'],true),404);
  $filename='simmag-'.$type.'-'.now()->format('Ymd-His').'.csv';
  return response()->streamDownload(function()use($type){
   $out=fopen('php://output','w');fprintf($out,"\xEF\xBB\xBF");
   $rows=match($type){
    'placements'=>Placement::with('student.user','company','period','teacher','roster')->get(),
    'journals'=>Journal::with('placement.student.user')->get(),
    'assessments'=>Assessment::with('placement.student.user')->get(),
    'certificates'=>Certificate::with('placement.student.user')->get(),
   };
   $headers=match($type){
    'placements'=>['ID','NIS','Nama','Perusahaan','Periode','Guru Pembimbing','Status'],
    'journals'=>['ID','Siswa','Tanggal','Kegiatan','Hasil','Status','Catatan Review'],
    'assessments'=>['ID','Siswa','Nilai Akhir','Status','Catatan'],
    'certificates'=>['ID','Nomor','Siswa','Versi','Status','Jadwal Pengambilan'],
   };fputcsv($out,$headers);
   foreach($rows as $item){$values=match($type){
    'placements'=>[$item->id,$item->student->nis,$item->student->user->name,$item->company->name,$item->period->name,$item->teacher?->name,$item->lifecycleLabel()],
    'journals'=>[$item->id,$item->placement->student->user->name,$item->activity_date->format('Y-m-d'),$item->activity,$item->outcome,$item->status,$item->review_note],
    'assessments'=>[$item->id,$item->placement->student->user->name,$item->final_score,$item->status,$item->notes],
    'certificates'=>[$item->id,$item->number,$item->placement->student->user->name,$item->version,$item->status,$item->pickup_at?->format('Y-m-d H:i')],
   };fputcsv($out,array_map(function($v){$str=(string)($v??'');return preg_match('/^[=+\-@\t\r]/',$str)?"'".$str:$str;},$values));}
   fclose($out);
  },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
 }
}
