<?php
// Internal runner: boot only through certificate-mysql-races.php in its isolated child.
use App\Http\Controllers\CertificateController;
use App\Models\{Certificate, Placement, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Symfony\Component\Process\Process;

if (($mode??null)!=='scenarios' || !isset($database,$directory) || !app()->environment('testing')) throw new RuntimeException('Use the isolated harness.');
check((new Certificate)->getConnection()->getDatabaseName()===$database,'Unexpected model destination.');
$children=[]; $checks=[];
config(['session.driver'=>'array','cache.default'=>'array','filesystems.disks.local.root'=>$directory.'/storage/app/private']);
try {
    $actors=User::where('role','koordinator')->orderBy('id')->pluck('id')->all();
    check(count($actors)>=2,'Two coordinator fixtures required.');
    $request=function(array $params,int $actor) {
        $user=User::findOrFail($actor); Auth::setUser($user);
        $r=Request::create('/certificate-probe','POST',$params);
        $r->setUserResolver(fn()=>$user); $r->setLaravelSession(app('session')->driver());
        app()->instance('request',$r); return $r;
    };
    $run=function(array $fixtures) use (&$children,$directory): array {
        $processes=[];
        foreach ($fixtures as $slot=>$fixture) {
            file_put_contents($directory.'/'.$slot.'.json',json_encode($fixture,JSON_THROW_ON_ERROR));
            $p=new Process([PHP_BINARY,__DIR__.'/certificate-mysql-races.php','worker',$slot],base_path(),null,null,40);
            $p->start(); $children[]=$p; $processes[$slot]=$p;
        }
        awaitFiles(array_map(fn($slot)=>$directory.'/'.$slot.'.ready',array_keys($fixtures)));
        foreach (array_keys($fixtures) as $slot) touch($directory.'/'.$slot.'.go');
        awaitFiles(array_map(fn($slot)=>$directory.'/'.$slot.'.attempt',array_keys($fixtures)));
        DB::commit();
        $statuses=[];
        foreach ($processes as $slot=>$p) {
            $p->wait(); check($p->isSuccessful(),'Worker failed: '.$slot);
            $response=json_decode($p->getOutput(),true,512,JSON_THROW_ON_ERROR);
            file_put_contents($directory.'/'.$slot.'.result.json',json_encode($response,JSON_THROW_ON_ERROR));
            $statuses[]=$response['status'];
        }
        sort($statuses); return $statuses;
    };
    $fixture=fn(string $action,?Certificate $c,array $params,string $barrier='internship_rosters')=>[
        'action'=>$action,'certificate'=>$c?->id,'params'=>$params,'barrier_table'=>$barrier,'actor'=>$actors[0],
    ];
    $pair=fn(string $label,array $f)=>[$label.'_a'=>$f,$label.'_b'=>array_replace($f,['actor'=>$actors[1]])];
    $lock=function(Placement $p) { DB::beginTransaction(); $p->roster()->lockForUpdate()->firstOrFail(); };
    $record=function(string $name) use (&$checks) { $checks[]=$name; echo 'PASS: '.$name.PHP_EOL; };
    $candidate=fn()=>Placement::whereHas('assessment',fn($q)=>$q->where('status','approved'))->doesntHave('certificate')->firstOrFail();
    $audit=fn(string $action,Certificate $c)=>DB::table('audit_logs')->where('action',$action)->where('subject_id',$c->id)->count();
    $controller=app(CertificateController::class);

    $p=$candidate(); $lock($p);
    check($run($pair('create',$fixture('create',null,['placement_id'=>$p->id])))===[302,422],'Duplicate submission failed.');
    $c=$p->certificate()->sole();
    check($c->documents()->count()===1 && $audit('certificate.created',$c)===1,'Duplicate submission archive/audit.');
    $record('Two coordinators submit: one certificate, archive and audit');

    $lock($p);
    check($run($pair('approve',$fixture('approve',$c,['version'=>1,'decision'=>'approved'])))===[302,409],'Duplicate approval failed.');
    check($c->fresh()->status==='awaiting_print' && $c->approvalEvents()->count()===1 && $audit('certificate.approval',$c)===1,'Duplicate approval event/audit.');
    check(in_array($c->approvalEvents()->sole()->approver_id,$actors,true),'Decision actor missing.');
    $original=$c->documents()->sole()->toArray();
    $record('Two coordinators approve: one issuance and recorded decision actor');

    DB::beginTransaction(); Certificate::whereKey($c->id)->lockForUpdate()->firstOrFail();
    check($run($pair('move',$fixture('movement',$c,['version'=>1,'expected_status'=>'awaiting_print','recipient'=>'TU Demo'],'certificates')))=== [302,422],'Duplicate distribution failed.');
    check($c->fresh()->status==='printed' && $c->movements()->count()===1 && $audit('certificate.moved',$c)===1,'Distribution skipped a step.');
    $record('Two coordinators distribute: one transition and movement');

    $lock($p);
    check($run($pair('revise',$fixture('resubmit',$c,['version'=>1,'note'=>'Koreksi data fiktif untuk uji konkurensi.'])))===[302,409],'Duplicate revision failed.');
    check($c->fresh()->version===2 && $c->documents()->count()===2 && $audit('certificate.resubmitted',$c)===1,'Revision duplicated.');
    check($c->documents()->where('version',1)->sole()->toArray()===$original,'Issued archive changed.');
    $record('Two coordinators revise: version increments once, issued archive preserved');

    $lock($p);
    $controller->resubmit($request(['version'=>2,'note'=>'Versi baru saat pengesahan menunggu.'],$actors[0]),$c->fresh());
    check($run(['approve_old'=>$fixture('approve',$c,['version'=>2,'decision'=>'approved'])])===[409],'Old version approved during revision.');
    check($c->fresh()->version===3 && $c->fresh()->status==='awaiting_approval' && $c->approvalEvents()->count()===1,'Stale approval mutated state.');
    $record('Approval waiting for revision: old version rejected');

    $legacy=Certificate::where('status','awaiting_print')->firstOrFail();
    $legacy->documents()->delete(); // Synthetic legacy fixture, isolated DB only.
    $lock($legacy->placement);
    check($run($pair('legacy',$fixture('archiveLegacy',$legacy,['version'=>1,'note'=>'Pemeriksaan dokumen lama fiktif, rekonstruksi eksplisit.'])))===[302,409],'Duplicate legacy reconstruction failed.');
    check($legacy->documents()->count()===1 && $audit('certificate.legacy_archived',$legacy)===1,'Legacy archive overwritten/duplicated.');
    $record('Two legacy reconstructions: one archive, second request rejected');

    $pending=Certificate::where('status','awaiting_approval')->where('id','!=',$c->id)->firstOrFail();
    $lock($pending->placement);
    $pending->placement->roster->update(['internship_ends_at'=>$pending->placement->roster->internship_ends_at->subDay()]);
    check($run(['approve_changed_source'=>$fixture('approve',$pending,['version'=>$pending->version,'decision'=>'approved'])])===[422],'Approval ignored source correction committed while waiting.');
    check($pending->fresh()->status==='awaiting_approval' && $pending->approvalEvents()->count()===0,'Changed source was issued.');
    $record('Approval waiting for schedule correction: changed source rejected');

    $p1=$candidate(); $p2=Placement::whereHas('assessment',fn($q)=>$q->where('status','approved'))->doesntHave('certificate')->where('id','!=',$p1->id)->firstOrFail();
    $lock($p1); $p2->roster()->lockForUpdate()->firstOrFail();
    $f1=$fixture('create',null,['placement_id'=>$p1->id,'number'=>'RACE-NUMBER-SAME']);
    $f2=$fixture('create',null,['placement_id'=>$p2->id,'number'=>'RACE-NUMBER-SAME']); $f2['actor']=$actors[1];
    check($run(['number_a'=>$f1,'number_b'=>$f2])===[302,422],'Duplicate number did not produce a validation error.');
    check(Certificate::whereIn('placement_id',[$p1->id,$p2->id])->count()===1,'Losing submission left a partial certificate.');
    $record('Same number on different students: one submission, loser fully rolled back');

    $legacyDocument=$legacy->documents()->sole()->toArray();
    DB::beginTransaction(); Certificate::whereKey($legacy->id)->lockForUpdate()->firstOrFail();
    $controller->resubmit($request(['version'=>1,'note'=>'Revisi saat distribusi versi lama menunggu.'],$actors[0]),$legacy->fresh());
    check($run(['move_old'=>$fixture('movement',$legacy,['version'=>1,'expected_status'=>'awaiting_print','recipient'=>'TU Demo'],'certificates')])===[422],'Distribution accepted a superseded version.');
    check($legacy->fresh()->version===2 && $legacy->movements()->count()===0,'Stale distribution changed the new version.');
    check($legacy->documents()->where('version',1)->sole()->toArray()===$legacyDocument,'Legacy issued archive changed during revision.');
    $record('Distribution waiting for revision: superseded version rejected');

    \Illuminate\Support\Facades\Artisan::call('db:seed',['--class'=>\Database\Seeders\CertificatePreviewDemoSeeder::class,'--force'=>true]);
    $wordCertificate=Certificate::whereHas('placement.student',fn($q)=>$q->where('nis',\Database\Seeders\CertificatePreviewDemoSeeder::NIS))->firstOrFail();
    $document=$wordCertificate->documents()->sole(); $pdfBefore=$document->toArray();
    DB::beginTransaction(); $wordCertificate->documents()->whereKey($document->id)->lockForUpdate()->firstOrFail();
    check($run($pair('word',$fixture('prepareWord',$wordCertificate,['version'=>$document->version],'certificate_documents')))=== [302,302],'Concurrent DOCX preparation failed.');
    check(DB::table('certificate_word_exports')->where('certificate_document_id',$document->id)->count()===1,'Duplicate Word archive.');
    $export=$document->fresh()->wordExport;
    check($document->fresh()->toArray()===$pdfBefore && $export->source_pdf_hash===$document->sha256,'PDF changed or DOCX uses another source.');
    foreach (['front','back'] as $side) check(hash('sha256',base64_decode($export->{$side.'_docx'},true))===$export->{$side.'_hash'},'Word integrity failure.');
    $record('Two DOCX preparations: one export, valid hashes, original PDF preserved');
    file_put_contents($directory.'/checks.json',json_encode($checks,JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    $failed=true;
    echo 'FAIL: '.(get_class($e)===RuntimeException::class?$e->getMessage():get_class($e)).PHP_EOL;
} finally {
    foreach ($children as $p) if ($p->isRunning()) $p->stop(1);
    while (DB::transactionLevel()>0) DB::rollBack();
}
exit(isset($failed)?1:0);
