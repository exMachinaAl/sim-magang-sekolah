<?php

namespace App\Http\Controllers;

use App\Models\{Certificate, CertificatePrintTemplate, Placement};
use App\Support\{Access, CertificateArchive, CertificatePrint, CertificatePdfBackground};
use Illuminate\Http\Request;

class CertificatePreviewController
{
    public function __invoke(Request $request, Placement $placement)
    {
        $placement=Access::placement($request->user(),$placement->id);
        $data=$request->validate(['print_template_id'=>'required|integer|exists:certificate_print_templates,id',
            'number'=>'nullable|string|max:100']);
        $template=CertificatePrintTemplate::findOrFail($data['print_template_id']);
        $existing=$placement->certificate;
        // Unsaved model: no sequence reservation, document, approval, audit or version is written.
        $certificate=new Certificate(['placement_id'=>$placement->id,'print_template_id'=>$template->id,
            'number'=>$existing?->number??($data['number']??'PRATINJAU/NOMOR-BELUM-DITETAPKAN'),
            'version'=>$existing ? $existing->version+1 : 1,
            'print_options'=>app(CertificatePrint::class)->options($request,$template)]);
        $render=app(CertificateArchive::class)->render($certificate);
        $bytes=app(CertificatePdfBackground::class)->preview(base64_decode($render['pdf_base64'],true));
        return response($bytes,200,['Content-Type'=>'application/pdf',
            'Content-Disposition'=>'inline; filename="pratinjau-sertifikat.pdf"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
