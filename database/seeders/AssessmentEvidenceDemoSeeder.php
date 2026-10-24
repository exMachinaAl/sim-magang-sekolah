<?php
namespace Database\Seeders;

use App\Models\{Assessment, User};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;

class AssessmentEvidenceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local','testing'])) throw new \RuntimeException('Contoh bukti hanya untuk local/testing.');
        $a=Assessment::whereHas('placement.roster',fn($q)=>$q->where('nis',CertificatePreviewDemoSeeder::NIS)->where('notes',CertificatePreviewDemoSeeder::MARKER))->firstOrFail();
        $note='DEMO BUKTI INDUSTRI: dokumen sintetis untuk pengujian, bukan bukti penilaian asli.';
        if ($a->evidence()->where('note',$note)->exists()) return;
        $actor=User::where('email','koordinator@simmag.test')->where('role','koordinator')->firstOrFail();
        $rows='';
        foreach ($a->template_snapshot['fields'] as $field) $rows.='<p>'.e($field['label']).': '.e($a->field_values[$field['key']]??'').'</p>';
        $bytes=Pdf::loadHTML('<h1>CONTOH BUKTI NILAI - DEMO</h1><p>DATA FIKTIF, BUKAN DOKUMEN RESMI.</p><p>NIS: '.CertificatePreviewDemoSeeder::NIS.'</p>'.$rows.'<p>Nilai resmi contoh: '.e($a->final_score).'</p>')->setPaper('a4')->output();
        $a->evidence()->create(['uploaded_by'=>$actor->id,'assessment_revision'=>$a->revision,'assessment_status'=>$a->status,
            'original_name'=>'contoh-bukti-nilai-DEMO.pdf','mime'=>'application/pdf','size'=>strlen($bytes),
            'sha256'=>hash('sha256',$bytes),'file_base64'=>base64_encode($bytes),'note'=>$note]);
    }
}
