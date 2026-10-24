<?php

namespace Database\Seeders;

use App\Models\{Certificate, CertificatePrintTemplate};
use App\Support\CertificatePdfBackground;
use Illuminate\Database\Seeder;

class CertificatePdfTemplateDemoSeeder extends Seeder
{
    public const NAME='DEMO — PDF berdekorasi tanpa LibreOffice';

    public function run(): void
    {
        if (!app()->environment(['local','testing'])) throw new \RuntimeException('Contoh hanya untuk local/testing.');
        $certificate=Certificate::whereHas('placement.roster',fn($q)=>$q->where('nis',CertificatePreviewDemoSeeder::NIS)
            ->where('notes',CertificatePreviewDemoSeeder::MARKER))->firstOrFail();
        $original=$certificate->printTemplate;
        if (!$original) throw new \RuntimeException('Template contoh sertifikat belum tersedia.');
        if (CertificatePrintTemplate::where('name',self::NAME)->where('assessment_template_id',$original->assessment_template_id)->exists()) return;
        $values=$original->only(['assessment_template_id','front_variant','back_variant','settings','score_mapping','created_by']);
        $values['name']=self::NAME;
        $values['pdf_layout']=CertificatePdfBackground::defaultLayout();
        foreach (['front','back'] as $side) {
            $bytes=app(CertificatePdfBackground::class)->sample($side);
            $values[$side.'_pdf']=base64_encode($bytes); $values[$side.'_pdf_hash']=hash('sha256',$bytes);
        }
        // Only a new template; never revise, approve, or replace the user's existing certificate.
        CertificatePrintTemplate::create($values);
    }
}
