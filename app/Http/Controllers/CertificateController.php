<?php
namespace App\Http\Controllers;
use App\Models\{Certificate,CertificateApproval,CertificateMovement,Journal};
use App\Support\{Access,Audit};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CertificateController {
 public function index(Request $r){
  $user=$r->user();
  $items=Access::certificates($user)->with(['placement.student.user','placement.company','placement.period','approvals'])
   ->when($r->status,fn($q)=>$q->where('status',$r->status))->latest()->paginate(15)->withQueryString();
  $placements=$user->isRole('koordinator')?Access::placements($user)
   ->doesntHave('certificate')->with(['student.user','assessment','journals'])->get():collect();
  return view('certificates.index',compact('items','placements'));
 }
 public function create(Request $r){
  abort_unless($r->user()->isRole('koordinator'),403);
  $data=$r->validate(['placement_id'=>'required|integer']);
  $p=Access::placement($r->user(),$data['placement_id']);
  if($p->certificate)throw ValidationException::withMessages(['placement_id'=>'Sertifikat untuk penempatan ini sudah dibuat.']);
  if($p->assessment?->status!=='approved' || (!$p->assessment?->template_snapshot &&
    (!$p->journals()->where('status','approved')->exists() || $p->journals()->whereIn('status',['draft','submitted','revision','rejected'])->exists()))){
   throw ValidationException::withMessages(['placement_id'=>'Penilaian harus disahkan. Untuk skema lama, diperlukan minimal satu jurnal dan seluruh jurnal harus disetujui.']);
  }
  DB::transaction(function()use($p){
   $c=Certificate::create(['placement_id'=>$p->id,'status'=>'awaiting_approval']);
   $c->update(['number'=>'SIMMAG/'.now()->year.'/'.str_pad($c->id,5,'0',STR_PAD_LEFT)]);
   $c->approvals()->create(['party'=>'coordinator','status'=>'pending']);
   Audit::record('certificate.created',$c);
  });
  return back()->with('success','Sertifikat dibuat dan dikirim untuk persetujuan.');
 }
 public function show(Request $r,Certificate $certificate){
  $certificate=Access::certificates($r->user())->with(['placement.student.user','placement.company','placement.period','approvals','approvalEvents.approver','movements.actor'])->findOrFail($certificate->id);
  return view('certificates.show',compact('certificate'));
 }
 public function approve(Request $r,Certificate $certificate){
  $user=$r->user();abort_unless($user->isRole('koordinator'),403);
  $c=Access::certificates($user)->findOrFail($certificate->id);
  $party='coordinator';
  abort_unless($c->status==='awaiting_approval',422,'Sertifikat ini tidak sedang menunggu persetujuan.');
  $d=$r->validate(['decision'=>'required|in:approved,rejected','note'=>'required_if:decision,rejected|nullable|string|max:2500']);
  DB::transaction(function()use($c,$party,$d,$user){
   $a=$c->approvals()->where('party',$party)->lockForUpdate()->firstOrFail();
   abort_unless($a->status==='pending',422,'Persetujuan sudah diproses.');
   $a->update(['status'=>$d['decision'],'note'=>$d['note']??null,'approver_id'=>$user->id,'decided_at'=>now()]);
   $c->approvalEvents()->create(['version'=>$c->version,'party'=>$party,'decision'=>$d['decision'],'note'=>$d['note']??null,'approver_id'=>$user->id]);
   if($d['decision']==='rejected')$c->update(['status'=>'revision_required']);
   elseif(!$c->approvals()->where('status','!=','approved')->exists())$c->update(['status'=>'awaiting_print','issued_at'=>now()]);
   Audit::record('certificate.approval',$c,['party'=>$party,'decision'=>$d['decision'],'note'=>$d['note']??null]);
  });
  return back()->with('success','Keputusan persetujuan berhasil direkam.');
 }
 public function resubmit(Request $r,Certificate $certificate){
  abort_unless($r->user()->isRole('koordinator'),403);
  $c=Access::certificates($r->user())->findOrFail($certificate->id);
  abort_unless($c->status==='revision_required',422);
  DB::transaction(function()use($c){
   $c->update(['status'=>'awaiting_approval','version'=>$c->version+1,'issued_at'=>null]);
   $c->approvals()->update(['status'=>'pending','approver_id'=>null,'note'=>null,'decided_at'=>null]);
   Audit::record('certificate.resubmitted',$c,['version'=>$c->version]);
  });return back()->with('success','Sertifikat diajukan ulang kepada koordinator PKL.');
 }
 public function movement(Request $r,Certificate $certificate){
  abort_unless($r->user()->isRole('koordinator'),403);
  $c=Access::certificates($r->user())->findOrFail($certificate->id);
  $transitions=['awaiting_print'=>'printed','printed'=>'sent_to_company','sent_to_company'=>'returned_to_school',
   'returned_to_school'=>'ready_pickup','ready_pickup'=>'handed_to_student','handed_to_student'=>'completed'];
  abort_unless(isset($transitions[$c->status]),422,'Tahap administrasi tidak dapat diproses sekarang.');
  $d=$r->validate(['expected_status'=>'required|string','recipient'=>'required|string|max:150','note'=>'nullable|string|max:2000',
   'pickup_at'=>($c->status==='returned_to_school'?'required':'nullable').'|date',
   'pickup_location'=>($c->status==='returned_to_school'?'required':'nullable').'|string|max:200',
   'pickup_requirements'=>'nullable|string|max:2000']);
  DB::transaction(function()use($c,$transitions,$d,$r){
   $locked=Certificate::whereKey($c->id)->lockForUpdate()->firstOrFail();
   abort_unless(isset($transitions[$locked->status]) && $locked->status===$d['expected_status'],422,'Status dokumen berubah. Muat ulang halaman sebelum melanjutkan.');
   $before=$locked->status;$next=$transitions[$before];
   if($next==='ready_pickup')$locked->fill(['pickup_at'=>$d['pickup_at'],'pickup_location'=>$d['pickup_location'],
    'pickup_requirements'=>$d['pickup_requirements']??null]);
   $locked->status=$next;$locked->save();
   CertificateMovement::create(['certificate_id'=>$locked->id,'from_status'=>$before,'to_status'=>$next,'actor_id'=>$r->user()->id,
    'recipient'=>$d['recipient'],'note'=>$d['note']??null,'moved_at'=>now()]);
   Audit::record('certificate.moved',$locked,['from'=>$before,'to'=>$next,'recipient'=>$d['recipient']]);
  });
  return back()->with('success','Status dan serah terima sertifikat berhasil diperbarui.');
 }
 public function pdf(Request $r,Certificate $certificate){
  $c=Access::certificates($r->user())->with([
   'placement.student.user','placement.company','placement.period','placement.assessment.scores.criterion',
   'placement.assessment.assessor','placement.assessment.approver','approvals'
  ])->findOrFail($certificate->id);
  abort_unless(!in_array($c->status,['awaiting_approval','revision_required'],true),403,'Sertifikat belum disetujui koordinator PKL.');
  abort_unless($c->approvals->where('party','coordinator')->where('status','approved')->count()===1,403);
  abort_unless($c->placement->assessment?->status==='approved',422,'Penilaian yang disahkan tidak ditemukan.');
  $startsAt=$c->placement->roster?->internship_starts_at;
  $endsAt=$c->placement->roster?->internship_ends_at;
  abort_unless($startsAt && $endsAt,422,'Jadwal siswa di Kontrol PKL belum lengkap; tag periode tidak memiliki tanggal.');
  if($c->placement->assessment->template_snapshot){
   $pdf=Pdf::loadView('certificates.template-pdf',['certificate'=>$c,'startsAt'=>$startsAt,'endsAt'=>$endsAt])->setPaper('a4','portrait');
   $pdf->render();
   abort_unless($pdf->getDomPDF()->getCanvas()->get_page_count()===2,422,'Isi dokumen melebihi dua halaman. Tata letak perlu disesuaikan sebelum dicetak bolak-balik.');
   return $pdf->download('sertifikat-'.$c->id.'.pdf');
  }
  return Pdf::loadView('certificates.pdf',['certificate'=>$c])->setPaper('a4','landscape')->download('sertifikat-'.$c->id.'.pdf');
 }
}
