<?php
namespace App\Http\Controllers;

use App\Models\{Assessment, AssessmentEvidence, Placement};
use App\Support\{Access, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssessmentEvidenceController
{
    public function index(Request $request,Assessment $assessment)
    {
        Access::placement($request->user(),$assessment->placement_id);
        $assessment->load(['placement.student.user','placement.roster','placement.period']);
        $items=$assessment->evidence()->select('id','assessment_id','uploaded_by','assessment_revision','assessment_status','original_name','mime','size','sha256','note','created_at')
            ->with('uploader')->latest('id')->paginate(10);
        return view('assessments.evidence',compact('assessment','items'));
    }
    public function store(Request $request,Assessment $assessment)
    {
        $data=$request->validate(['revision'=>'required|integer|min:0','note'=>'required|string|min:10|max:2000',
            'file'=>'required|file|mimes:pdf,jpg,jpeg,png|max:2048']);
        Access::placement($request->user(),$assessment->placement_id);
        $file=$request->file('file'); $bytes=file_get_contents($file->getRealPath());
        abort_unless($bytes!==false,422,'Berkas tidak dapat dibaca.');
        $mime=$file->getMimeType();
        abort_unless(in_array($mime,['application/pdf','image/jpeg','image/png'],true),422,'Format bukti tidak didukung.');
        $name=Str::limit(preg_replace('/[\x00-\x1F\x7F]/u','',basename(str_replace('\\','/',$file->getClientOriginalName()))),180,'');
        DB::transaction(function() use($assessment,$request,$data,$bytes,$mime,$name) {
            Placement::whereKey($assessment->placement_id)->lockForUpdate()->firstOrFail();
            $current=Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$current->revision===(int)$data['revision'],409,'Penilaian sudah berubah. Muat ulang dan periksa versi sebelum mengunggah bukti.');
            $evidence=$current->evidence()->create(['uploaded_by'=>$request->user()->id,'assessment_revision'=>$current->revision??0,
                'assessment_status'=>$current->status,'original_name'=>$name,'mime'=>$mime,'size'=>strlen($bytes),
                'sha256'=>hash('sha256',$bytes),'file_base64'=>base64_encode($bytes),'note'=>$data['note']]);
            Audit::record('assessment.evidence_added',$evidence,['assessment_id'=>$current->id,'revision'=>$current->revision,'sha256'=>$evidence->sha256]);
        });
        return redirect()->route('assessments.evidence.index',$assessment)->with('success','Bukti industri tersimpan. Nilai, status pengesahan, dan sertifikat tidak diubah.');
    }
    public function download(Request $request,AssessmentEvidence $evidence)
    {
        Access::placement($request->user(),$evidence->assessment->placement_id);
        $bytes=base64_decode($evidence->file_base64,true);
        abort_unless(is_string($bytes) && strlen($bytes)===$evidence->size && hash_equals($evidence->sha256,hash('sha256',$bytes)),409,'Integritas bukti gagal. Hubungi koordinator; dokumen tidak dikirim.');
        $extension=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$evidence->mime]??null;
        abort_unless($extension,409,'Format arsip tidak dikenali.');
        return response($bytes,200,['Content-Type'=>$evidence->mime,'Content-Disposition'=>'attachment; filename="bukti-nilai-'.$evidence->id.'.'.$extension.'"',
            'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"sandbox; default-src 'none'"]);
    }
}
