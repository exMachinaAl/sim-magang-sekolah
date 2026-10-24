<?php
namespace App\Http\Controllers;

use App\Models\{Company, IndustryDevice, IndustryMentor, Journal, JournalIndustryValidation, Placement};
use App\Support\{Access, Audit, JournalQr};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cookie, DB};
use Illuminate\Support\Str;

class IndustryJournalController
{
    public function index(Request $request)
    {
        $filters=$request->validate(['q'=>'nullable|string|max:100','company_id'=>'nullable|integer|exists:companies,id']);
        $placements=Placement::with(['student.user','period'])->when($filters['company_id'] ?? null,fn($q,$id)=>$q->where('company_id',$id))
            ->when($filters['q'] ?? null,fn($q,$term)=>$q->whereHas('student',fn($s)=>$s->where('nis','like','%'.$term.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$term.'%'))))
            ->when(empty($filters['company_id']),fn($q)=>$q->whereRaw('1=0'))->orderBy('id')->paginate(50)->withQueryString();
        $mentors=IndustryMentor::with(['company','devices'])->withCount('placements')->latest()->paginate(10,['*'],'mentors_page')->withQueryString();
        return view('journals.mentors',['placements'=>$placements,'mentors'=>$mentors,'companies'=>Company::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:150','company_id'=>'required|integer|exists:companies,id',
            'placement_ids'=>'required|array|min:1|max:1000','placement_ids.*'=>'required|integer|distinct|exists:placements,id']);
        DB::transaction(function()use($data,$request){
            $placements=Placement::whereIn('id',$data['placement_ids'])->lockForUpdate()->get();
            abort_unless($placements->every(fn($p)=>(int)$p->company_id===(int)$data['company_id']),422,'Siswa harus berada pada perusahaan pembimbing.');
            $mentor=IndustryMentor::create(['name'=>$data['name'],'company_id'=>$data['company_id'],'created_by'=>$request->user()->id]);
            $mentor->placements()->sync($data['placement_ids']);
            Audit::record('industry_mentor.created',$mentor,['placement_ids'=>$data['placement_ids']]);
        });
        return back()->with('success','Pembimbing industri dibuat. Buat QR pendaftaran perangkat untuk pembimbing tersebut.');
    }

    public function assignment(Request $request, IndustryMentor $mentor)
    {
        $data=$request->validate(['placement_id'=>'required|integer|exists:placements,id','action'=>'required|in:add,remove']);
        DB::transaction(function()use($mentor,$data){
            $locked=IndustryMentor::whereKey($mentor->id)->lockForUpdate()->firstOrFail();
            $placement=Placement::findOrFail($data['placement_id']);
            abort_unless((int)$placement->company_id===(int)$locked->company_id,422,'Perusahaan tidak cocok.');
            if($data['action']==='add') $locked->placements()->syncWithoutDetaching([$placement->id]);
            else $locked->placements()->detach($placement->id);
            Audit::record('industry_mentor.assignment',$locked,$data);
        });
        return back()->with('success','Penugasan pembimbing industri diperbarui.');
    }

    public function invite(Request $request, IndustryMentor $mentor)
    {
        $token=Str::random(64);
        DB::transaction(function()use($mentor,$request,$token){
            $locked=IndustryMentor::whereKey($mentor->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->is_active,422);
            DB::table('industry_device_invites')->where('industry_mentor_id',$mentor->id)->whereNull('used_at')->update(['expires_at'=>now()]);
            DB::table('industry_device_invites')->insert(['industry_mentor_id'=>$mentor->id,'created_by'=>$request->user()->id,
                'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);
            Audit::record('industry_device.invited',$locked);
        });
        $url=route('industry.enroll',['token'=>$token]);
        return response()->view('journals.invite',['mentor'=>$mentor,'url'=>$url,'image'=>JournalQr::image($url)])
            ->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');
    }

    public function revoke(Request $request, IndustryDevice $device)
    {
        DB::transaction(function()use($device){
            IndustryMentor::whereKey($device->industry_mentor_id)->lockForUpdate()->firstOrFail();
            $locked=IndustryDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
            if ($locked->revoked_at) return;
            $locked->update(['revoked_at'=>now()]);
            Audit::record('industry_device.revoked',$locked);
        });
        return back()->with('success','Perangkat dicabut. Riwayat validasi lama tetap tersimpan.');
    }

    public function toggle(Request $request, IndustryMentor $mentor)
    {
        $data=$request->validate(['is_active'=>'required|boolean','expected_active'=>'required|boolean']);
        DB::transaction(function()use($mentor,$data){
            $locked=IndustryMentor::whereKey($mentor->id)->lockForUpdate()->firstOrFail();
            // Explicit target makes retries harmless instead of reversing the previous action.
            if ($locked->is_active === (bool)$data['is_active']) return;
            abort_unless($locked->is_active === (bool)$data['expected_active'],409,'Status pembimbing berubah. Muat ulang halaman.');
            $locked->update(['is_active'=>(bool)$data['is_active']]);
            if(!$locked->is_active) {
                $locked->devices()->update(['revoked_at'=>now()]);
                DB::table('industry_device_invites')->where('industry_mentor_id',$locked->id)->update(['expires_at'=>now()]);
            }
            Audit::record('industry_mentor.toggled',$locked,['is_active'=>$locked->is_active]);
        });
        return back()->with('success','Status pembimbing diperbarui. Aktivasi ulang membutuhkan QR perangkat baru.');
    }

    public function enroll(Request $request, string $token)
    {
        $invite=DB::table('industry_device_invites')->where('token_hash',hash('sha256',$token))->first();
        abort_unless($invite && !$invite->used_at && now()->lt($invite->expires_at),410,'QR pendaftaran sudah digunakan atau kedaluwarsa. Minta QR baru ke koordinator.');
        $mentor=IndustryMentor::whereKey($invite->industry_mentor_id)->where('is_active',true)->firstOrFail();
        if($request->isMethod('get') || $request->isMethod('head')) return view('industry.scan',['title'=>'Mendaftarkan perangkat','mentor'=>$mentor,'action'=>url()->current(),'automatic'=>true]);
        $secret=Str::random(64);
        DB::transaction(function()use($invite,$mentor,$secret,$request){
            $locked=IndustryMentor::whereKey($mentor->id)->lockForUpdate()->firstOrFail();
            $row=DB::table('industry_device_invites')->where('id',$invite->id)->lockForUpdate()->first();
            abort_unless($locked->is_active && !$row->used_at && now()->lt($row->expires_at),410,'QR tidak berlaku lagi.');
            $device=IndustryDevice::create(['industry_mentor_id'=>$mentor->id,'token_hash'=>hash('sha256',$secret),
                'label'=>mb_substr($request->userAgent() ?: 'Browser pembimbing',0,200),'registered_ip'=>$request->ip(),'expires_at'=>now()->addDays(180)]);
            DB::table('industry_device_invites')->where('id',$row->id)->update(['used_at'=>now(),'updated_at'=>now()]);
            $this->kioskAudit('industry_device.enrolled',$device,['industry_mentor_id'=>$mentor->id]);
        });
        return redirect()->route('industry.home')->withCookie(Cookie::make('industry_device',$secret,180*24*60,'/industry',null,$request->isSecure(),true,false,'lax'));
    }

    public function device(Request $request): IndustryDevice
    {
        $token=$request->cookie('industry_device');
        abort_unless(is_string($token) && strlen($token)===64,403,'Browser ini belum terdaftar sebagai perangkat pembimbing. Minta QR pendaftaran ke koordinator dan gunakan browser yang sama.');
        return IndustryDevice::with('mentor.company')->where('token_hash',hash('sha256',$token))->whereNull('revoked_at')
            ->where('expires_at','>',now())->whereHas('mentor',fn($q)=>$q->where('is_active',true))->firstOrFail();
    }

    public function home(Request $request)
    {
        $device=$this->device($request);
        return view('industry.home',['device'=>$device,'recent'=>JournalIndustryValidation::where('industry_device_id',$device->id)->latest('scanned_at')->limit(10)->get()]);
    }

    public function qr(Request $request, Journal $journal)
    {
        Access::placement($request->user(),$journal->placement_id);
        abort_unless($request->user()->isRole('siswa'),403);
        $token=Str::random(64);
        DB::transaction(function()use($journal,$token){
            $locked=Journal::whereKey($journal->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status!=='draft' && $locked->industry_score!==null,422,'Simpan kegiatan dan nilai dari industri sebelum menampilkan QR.');
            DB::table('journal_scan_tokens')->where('journal_id',$locked->id)->whereNull('used_at')->update(['expires_at'=>now()]);
            DB::table('journal_scan_tokens')->insert(['journal_id'=>$locked->id,'revision'=>$locked->revision,
                'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);
            $journal->setRawAttributes($locked->getAttributes(),true);
        });
        $journal->load('placement.student.user','placement.company');
        $url=route('industry.scan',['token'=>$token]);
        return response()->view('journals.qr',['journal'=>$journal,'image'=>JournalQr::image($url),'scanUrl'=>$url])
            ->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');
    }

    public function scan(Request $request, string $token)
    {
        $device=$this->device($request);
        $qr=DB::table('journal_scan_tokens')->where('token_hash',hash('sha256',$token))->first();
        abort_unless($qr,404);
        $journal=Journal::with('placement.student.user')->findOrFail($qr->journal_id);
        $this->assertAssignment($device,$journal);
        abort_unless($journal->revision===$qr->revision && $journal->status!=='draft',409,'Isi jurnal berubah. Minta siswa menampilkan QR versi terbaru.');
        if($qr->used_at) return redirect()->route('industry.home')->with('success','Versi jurnal ini sudah tervalidasi; tidak dicatat dua kali.');
        abort_unless(now()->lt($qr->expires_at),410,'QR kedaluwarsa. Siswa dapat membuat QR baru, termasuk untuk kegiatan hari lalu.');
        if($request->isMethod('get') || $request->isMethod('head')) return view('industry.scan',['title'=>'Memvalidasi jurnal','mentor'=>$device->mentor,'journal'=>$journal,'action'=>url()->current(),'automatic'=>true]);
        DB::transaction(function()use($device,$qr,$request){
            $locked=Journal::whereKey($qr->journal_id)->lockForUpdate()->firstOrFail();
            IndustryMentor::whereKey($device->industry_mentor_id)->lockForUpdate()->firstOrFail();
            $trusted=IndustryDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
            abort_unless(!$trusted->revoked_at && $trusted->expires_at->isFuture() && $trusted->mentor->is_active,403,'Perangkat tidak lagi dipercaya.');
            $this->assertAssignment($trusted,$locked);
            $scan=DB::table('journal_scan_tokens')->where('id',$qr->id)->lockForUpdate()->first();
            abort_unless($locked->revision===$scan->revision && $locked->status!=='draft' && $locked->industry_score!==null,409,'Jurnal berubah. Scan QR terbaru.');
            if($scan->used_at) return;
            abort_unless(now()->lt($scan->expires_at),410,'QR kedaluwarsa.');
            $validation=JournalIndustryValidation::firstOrCreate(['journal_id'=>$locked->id,'revision'=>$locked->revision],[
                'industry_mentor_id'=>$trusted->industry_mentor_id,'industry_device_id'=>$trusted->id,
                'snapshot'=>$locked->contentSnapshot()+['student_name'=>$locked->placement->student->user->name,
                    'nis'=>$locked->placement->student->nis,'mentor_name'=>$trusted->mentor->name,'company_name'=>$trusted->mentor->company->name],
                'scanned_at'=>now(),'ip_address'=>$request->ip(),
            ]);
            $locked->update(['validated_revision'=>$locked->revision]);
            $trusted->update(['last_used_at'=>now()]);
            DB::table('journal_scan_tokens')->where('id',$qr->id)->update(['used_at'=>now(),'updated_at'=>now()]);
            if($validation->wasRecentlyCreated) $this->kioskAudit('journal.industry_validated',$locked,['validation_id'=>$validation->id,
                'revision'=>$locked->revision,'industry_mentor_id'=>$trusted->industry_mentor_id,'industry_device_id'=>$trusted->id,'scanned_at'=>now()->toIso8601String()]);
        });
        return redirect()->route('industry.home')->with('success','Jurnal dan nilai tervalidasi. Waktu scan tercatat, terpisah dari tanggal kegiatan.');
    }

    private function assertAssignment(IndustryDevice $device, Journal $journal): void
    {
        abort_unless((int)$journal->placement->company_id===(int)$device->mentor->company_id &&
            $device->mentor->placements()->where('placements.id',$journal->placement_id)->exists(),403,'Jurnal bukan siswa yang ditugaskan kepada pembimbing ini.');
    }

    private function kioskAudit(string $action, object $subject, array $context): void
    {
        // A kiosk is a separate principal, even if another school account happens
        // to have an active session in the same browser.
        \App\Models\AuditLog::create(['user_id'=>null,'action'=>$action,'subject_type'=>class_basename($subject),
            'subject_id'=>$subject->id,'context'=>$context,'ip_address'=>request()->ip()]);
    }
}
