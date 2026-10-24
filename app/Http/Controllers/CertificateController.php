<?php
namespace App\Http\Controllers;
use App\Models\{Certificate,CertificateMovement,InternshipRoster,Placement};
use App\Support\{Access,Audit,CertificateArchive};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CertificateController {
 public function index(Request $r){
  $filters=$r->validate(['q'=>'nullable|string|max:100','status'=>['nullable',\Illuminate\Validation\Rule::in(array_keys(Certificate::statuses()))],
   'period_id'=>'nullable|integer|exists:internship_periods,id','major'=>'nullable|string|max:150']);
  $user=$r->user(); $search=trim($filters['q']??'');
  $scope=Access::placements($user)->when($search!=='',fn($q)=>$q->whereHas('student',fn($s)=>$s->where('nis','like','%'.$search.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$search.'%'))))
   ->when($filters['period_id']??null,fn($q,$id)=>$q->where('internship_period_id',$id))
   ->when($filters['major']??null,fn($q,$major)=>$q->whereHas('roster',fn($r)=>$r->where('major',$major)));
  $items=Access::certificates($user)->whereIn('placement_id',(clone $scope)->select('id'))->with(['placement.student.user','placement.company','placement.period','approvals'])
   ->when($filters['status']??null,fn($q,$status)=>$q->where('status',$status))->latest()->paginate(15)->withQueryString();
  $placements=$user->isRole('koordinator') && $search!==''?(clone $scope)
   ->doesntHave('certificate')->with(['student.user','assessment','roster','period','company'])->latest()->paginate(10,['*'],'candidates_page')->withQueryString():collect();
  $periods=\App\Models\InternshipPeriod::whereIn('id',Access::placements($user)->select('internship_period_id'))->latest()->get();
  $demoCertificate=null;
  if(app()->environment(['local','testing']) && $user->isRole('koordinator')) {
   $demoCertificate=Certificate::whereHas('placement.roster',fn($q)=>$q->where('nis','DEMO-SERT-001')
    ->where('notes','DATA DEMO FIKTIF: sertifikat siap uji, bukan dokumen resmi.'))
    ->select('id','placement_id','print_template_id')->first();
  }
  return view('certificates.index',compact('items','placements','periods','demoCertificate'));
 }
 public function create(Request $r){
  abort_unless($r->user()->isRole('koordinator'),403);
  $data=$r->validate(['placement_id'=>'required|integer','print_template_id'=>'nullable|integer|exists:certificate_print_templates,id',
   'number'=>'nullable|string|max:100|unique:certificates,number']);
  $printTemplate=isset($data['print_template_id'])?\App\Models\CertificatePrintTemplate::findOrFail($data['print_template_id']):null;
  $printOptions=$printTemplate?app(\App\Support\CertificatePrint::class)->options($r,$printTemplate):null;
  $p=Access::placement($r->user(),$data['placement_id']);
  DB::transaction(function()use($p,$r,$printTemplate,$printOptions,$data){
   $p=$this->lockPlacement($p->id);
   if($p->certificate()->exists())throw ValidationException::withMessages(['placement_id'=>'Sertifikat untuk penempatan ini sudah dibuat.']);
   $c=Certificate::create(['placement_id'=>$p->id,'status'=>'awaiting_approval','print_template_id'=>$printTemplate?->id,'print_options'=>$printOptions]);
   $c->refresh();
   $c->update(['number'=>$data['number']??('SIMMAG/'.now()->year.'/'.str_pad($c->id,5,'0',STR_PAD_LEFT))]);
   app(CertificateArchive::class)->capture($c,$r->user()->id);
   $c->approvals()->create(['party'=>'coordinator','status'=>'pending']);
   Audit::record('certificate.created',$c);
  },3);
  return back()->with('success','Sertifikat dibuat dan dikirim untuk persetujuan.');
 }
 public function show(Request $r,Certificate $certificate){
  $certificate=Access::certificates($r->user())->with(['placement.student.user','placement.company','placement.period','approvals','approvalEvents.approver','movements.actor',
   'documents'=>fn($q)=>$q->select('id','certificate_id','version','snapshot','source','note','sha256','issued_at','created_at','front_hash','back_hash')
    ->with('wordExport:id,certificate_document_id,front_hash,back_hash,layout,created_at')])->findOrFail($certificate->id);
  $document=$certificate->documents->firstWhere('version',$certificate->version);
  return view('certificates.show',compact('certificate','document'));
 }
 public function approve(Request $r,Certificate $certificate){
  $user=$r->user();abort_unless($user->isRole('koordinator'),403);
  $c=Access::certificates($user)->findOrFail($certificate->id);
  $party='coordinator';
  abort_unless($c->status==='awaiting_approval',422,'Sertifikat ini tidak sedang menunggu persetujuan.');
  $d=$r->validate(['version'=>'required|integer|min:1','decision'=>'required|in:approved,rejected','note'=>'required_if:decision,rejected|nullable|string|max:2500']);
  DB::transaction(function()use($c,$party,$d,$user){
   $this->lockPlacement($c->placement_id);
   $c=Certificate::whereKey($c->id)->lockForUpdate()->firstOrFail();
   abort_unless($c->status==='awaiting_approval' && $c->version===(int)$d['version'],409,'Versi/status berubah. Muat ulang sebelum memutuskan.');
   $a=$c->approvals()->where('party',$party)->lockForUpdate()->firstOrFail();
   abort_unless($a->status==='pending',422,'Persetujuan sudah diproses.');
   if($d['decision']==='approved') {
    $document=$c->documents()->where('version',$c->version)->first();
    if(!$document)throw ValidationException::withMessages(['certificate'=>'Dokumen lama belum punya arsip. Ajukan versi baru dari data yang sudah diperiksa.']);
    $source=app(CertificateArchive::class)->source($c);
    if(!app(CertificateArchive::class)->sourcesMatch($document->snapshot,$source))throw ValidationException::withMessages(['certificate'=>'Data sumber berubah sejak pengajuan. Periksa perubahan lalu ajukan versi baru; proposal lama tidak ditimpa.']);
    app(CertificateArchive::class)->bytes($document);
   }
   $a->update(['status'=>$d['decision'],'note'=>$d['note']??null,'approver_id'=>$user->id,'decided_at'=>now()]);
   $c->approvalEvents()->create(['version'=>$c->version,'party'=>$party,'decision'=>$d['decision'],'note'=>$d['note']??null,'approver_id'=>$user->id]);
   if($d['decision']==='rejected')$c->update(['status'=>'revision_required']);
   elseif(!$c->approvals()->where('status','!=','approved')->exists()) {
    $c->update(['status'=>'awaiting_print','issued_at'=>now()]);
    $document->update(['issued_at'=>$c->issued_at]);
   }
   Audit::record('certificate.approval',$c,['party'=>$party,'decision'=>$d['decision'],'note'=>$d['note']??null]);
  },3);
  return back()->with('success','Keputusan persetujuan berhasil direkam.');
 }
 public function resubmit(Request $r,Certificate $certificate){
  abort_unless($r->user()->isRole('koordinator'),403);
  $c=Access::certificates($r->user())->findOrFail($certificate->id);
  $d=$r->validate(['version'=>'required|integer|min:1','note'=>'required|string|max:2500','print_template_id'=>'nullable|integer|exists:certificate_print_templates,id']);
  $printTemplate=isset($d['print_template_id'])?\App\Models\CertificatePrintTemplate::findOrFail($d['print_template_id']):null;
  $printOptions=$printTemplate?app(\App\Support\CertificatePrint::class)->options($r,$printTemplate):null;
  DB::transaction(function()use($c,$d,$r,$printTemplate,$printOptions){
   $this->lockPlacement($c->placement_id);
   $c=Certificate::whereKey($c->id)->lockForUpdate()->firstOrFail();
   abort_unless($c->version===(int)$d['version'],409,'Versi berubah. Muat ulang.');
   if($c->issued_at && !$c->documents()->where('version',$c->version)->whereNotNull('issued_at')->exists()) {
    throw ValidationException::withMessages(['certificate'=>'Arsipkan dahulu dokumen lama secara eksplisit sebelum memulai revisi.']);
   }
   $c->update(['status'=>'awaiting_approval','version'=>$c->version+1,'issued_at'=>null,'pickup_at'=>null,'pickup_location'=>null,'pickup_requirements'=>null]);
   if($printTemplate)$c->update(['print_template_id'=>$printTemplate->id,'print_options'=>$printOptions]);
   app(CertificateArchive::class)->capture($c,$r->user()->id,'revision',$d['note']);
   $c->approvals()->update(['status'=>'pending','approver_id'=>null,'note'=>null,'decided_at'=>null]);
   Audit::record('certificate.resubmitted',$c,['version'=>$c->version,'note'=>$d['note']]);
  },3);return back()->with('success','Versi baru diajukan. Arsip versi lama tetap utuh; distribusi versi baru dimulai setelah disahkan.');
 }
 public function movement(Request $r,Certificate $certificate){
  abort_unless($r->user()->isRole('koordinator'),403);
  $c=Access::certificates($r->user())->findOrFail($certificate->id);
  $transitions=['awaiting_print'=>'printed','printed'=>'sent_to_company','sent_to_company'=>'returned_to_school',
   'returned_to_school'=>'ready_pickup','ready_pickup'=>'handed_to_student','handed_to_student'=>'completed'];
  abort_unless(isset($transitions[$c->status]),422,'Tahap administrasi tidak dapat diproses sekarang.');
  $d=$r->validate(['version'=>'required|integer|min:1','expected_status'=>'required|string','recipient'=>'required|string|max:150','note'=>'nullable|string|max:2000',
   'pickup_at'=>($c->status==='returned_to_school'?'required':'nullable').'|date',
   'pickup_location'=>($c->status==='returned_to_school'?'required':'nullable').'|string|max:200',
   'pickup_requirements'=>'nullable|string|max:2000']);
  DB::transaction(function()use($c,$transitions,$d,$r){
   $locked=Certificate::whereKey($c->id)->lockForUpdate()->firstOrFail();
   abort_unless(isset($transitions[$locked->status]) && $locked->status===$d['expected_status'] && $locked->version===(int)$d['version'],422,'Versi/status dokumen berubah. Muat ulang halaman sebelum melanjutkan.');
   abort_unless($locked->documents()->where('version',$locked->version)->whereNotNull('issued_at')->exists(),409,'Arsipkan dokumen versi ini sebelum distribusi.');
   $before=$locked->status;$next=$transitions[$before];
   if($next==='ready_pickup') {
    $r->validate(['pickup_at'=>'required|date','pickup_location'=>'required|string|max:200']);
    $locked->fill(['pickup_at'=>$d['pickup_at'],'pickup_location'=>$d['pickup_location'],'pickup_requirements'=>$d['pickup_requirements']??null]);
   }
   $locked->status=$next;$locked->save();
   CertificateMovement::create(['certificate_id'=>$locked->id,'from_status'=>$before,'to_status'=>$next,'actor_id'=>$r->user()->id,
    'recipient'=>$d['recipient'],'note'=>$d['note']??null,'moved_at'=>now(),'version'=>$locked->version]);
   Audit::record('certificate.moved',$locked,['from'=>$before,'to'=>$next,'recipient'=>$d['recipient']]);
  });
  return back()->with('success','Status dan serah terima sertifikat berhasil diperbarui.');
 }
 public function pdf(Request $r,Certificate $certificate){
  $c=Access::certificates($r->user())->findOrFail($certificate->id);
  $d=$r->validate(['version'=>'nullable|integer|min:1']);
  $version=(int)($d['version']??$c->version);
  $document=$c->documents()->where('version',$version)->first();
  abort_unless($document,409,'Versi ini belum memiliki arsip PDF. Koordinator perlu memeriksa dan mengarsipkan dokumen lama, bukan mencetak ulang dari data hidup.');
  abort_unless($document->issued_at,403,'Versi dokumen belum disahkan.');
  return response(app(CertificateArchive::class)->bytes($document),200,[
   'Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="sertifikat-'.$c->id.'-v'.$version.'.pdf"',
   'Cache-Control'=>'no-store, private','X-Content-Type-Options'=>'nosniff',
  ]);
 }

 public function archiveLegacy(Request $r,Certificate $certificate){
  abort_unless($r->user()->isRole('koordinator'),403);
  Access::certificates($r->user())->findOrFail($certificate->id);
  $d=$r->validate(['version'=>'required|integer|min:1','note'=>'required|string|min:10|max:2500']);
  DB::transaction(function()use($certificate,$r,$d){
   $this->lockPlacement($certificate->placement_id);
   $c=Certificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
   abort_unless($c->version===(int)$d['version'] && $c->issued_at && !in_array($c->status,['awaiting_approval','revision_required']),409,'Versi/status dokumen berubah atau belum diterbitkan.');
   abort_unless($c->approvals()->where('party','coordinator')->where('status','approved')->exists(),422,'Persetujuan lama tidak ditemukan.');
   abort_if($c->documents()->where('version',$c->version)->exists(),409,'Versi ini sudah diarsipkan; arsip tidak ditimpa.');
   $document=app(CertificateArchive::class)->capture($c,$r->user()->id,'legacy_reconstructed',$d['note']);
   $document->update(['issued_at'=>$c->issued_at]);
   Audit::record('certificate.legacy_archived',$c,['version'=>$c->version,'note'=>$d['note'],'sha256'=>$document->sha256]);
  },3);
  return back()->with('success','Salinan berdasarkan data saat ini diarsipkan. Ini rekonstruksi eksplisit, bukan bukti bahwa isinya identik dengan cetakan lama.');
 }

 private function lockPlacement(int $id): Placement {
  $placement=Placement::findOrFail($id);
  if($placement->internship_roster_id)InternshipRoster::whereKey($placement->internship_roster_id)->lockForUpdate()->firstOrFail();
  return Placement::whereKey($id)->lockForUpdate()->firstOrFail();
 }

 public function prepare(Request $r,Placement $placement){
  $placement=Access::placement($r->user(),$placement->id);
  $placement->load(['student.user','roster','period','company','assessment','certificate']);
  $templates=\App\Models\CertificatePrintTemplate::select('id','name','front_variant','back_variant','front_hash','front_pdf_hash')->where('assessment_template_id',$placement->assessment?->assessment_template_id)->latest()->get();
  $r->validate(['print_template_id'=>'nullable|integer|min:1']);
  $selected=$r->integer('print_template_id')?$templates->firstWhere('id',$r->integer('print_template_id')):null;
  if($r->integer('print_template_id'))abort_unless($selected,422,'Template cetak tidak sesuai penilaian siswa.');
  return view('certificates.prepare',compact('placement','templates','selected'));
 }

 public function docx(Request $r,Certificate $certificate,string $side){
  abort_unless(in_array($side,['front','back'],true),404);
  $c=Access::certificates($r->user())->findOrFail($certificate->id);
  $d=$r->validate(['version'=>'nullable|integer|min:1']);
  $document=$c->documents()->where('version',$d['version']??$c->version)->firstOrFail();
  abort_unless($document->issued_at,403,'Versi belum disahkan.');
  $source=$document;
  if (!$document->front_hash && !$document->back_hash) {
   $source=$document->wordExport;
   abort_unless($source && hash_equals($document->sha256,$source->source_pdf_hash),409,'Salinan Word belum tersedia atau tidak cocok dengan arsip PDF. Koordinator dapat menyiapkannya dari detail sertifikat.');
  }
  $bytes=base64_decode($source->{$side.'_docx'}??'',true);
  abort_unless($bytes && $source->{$side.'_hash'} && hash_equals($source->{$side.'_hash'},hash('sha256',$bytes)),409,'DOCX arsip tidak tersedia atau integritasnya gagal.');
  return response($bytes,200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
   'Content-Disposition'=>'attachment; filename="sertifikat-'.$c->id.'-v'.$document->version.'-'.$side.'.docx"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }

 public function prepareWord(Request $r,Certificate $certificate){
  abort_unless($r->user()->isRole('koordinator'),403);
  $c=Access::certificates($r->user())->findOrFail($certificate->id);
  $data=$r->validate(['version'=>'required|integer|min:1']);
  $document=$c->documents()->where('version',$data['version'])->firstOrFail();
  app(\App\Support\CertificateWordArchive::class)->create($document,$r->user()->id);
  return back()->with('success','DOCX depan dan belakang siap diunduh dari arsip versi '.$document->version.'. PDF lama tidak diubah. Periksa jumlah halaman dan tata letak di Word sebelum mencetak.');
 }
}
