<?php
namespace App\Support;

use App\Models\{Certificate, CertificateDocument};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;

class CertificateArchive
{
    public function sourcesMatch(array $stored, array $current): bool
    {
        // Native MySQL JSON can reorder object keys. Ignore key order, never coerce identity strings (e.g. NIS 001 vs 1).
        $normalize=function (mixed $value) use (&$normalize): mixed {
            if (!is_array($value)) return $value;
            foreach ($value as $key=>$part) $value[$key]=$normalize($part);
            ksort($value);
            return $value;
        };
        return json_encode($normalize($stored),JSON_THROW_ON_ERROR)===json_encode($normalize($current),JSON_THROW_ON_ERROR);
    }

    /** Read a fresh, consistent source inside the caller's transaction. Never save these rendering models. */
    public function source(Certificate $certificate): array
    {
        $certificate->unsetRelation('placement');
        $certificate->load(['placement.roster','placement.student.user','placement.company','placement.period',
            'placement.assessment.scores.criterion','placement.assessment.assessor','placement.assessment.approver']);
        $p=$certificate->placement; $r=$p->roster; $a=$p->assessment;
        $fail=fn ($message) => throw ValidationException::withMessages(['certificate'=>$message]);
        if (!$r || !$r->internship_starts_at || !$r->internship_ends_at || $r->internship_status==='not_internship'
            || $r->internship_ends_at->lt($r->internship_starts_at)) $fail('Jadwal siswa di Kontrol PKL harus lengkap, berurutan, dan siswa mengikuti PKL.');
        if ((int)$r->student_id!==(int)$p->student_id || (int)$r->internship_period_id!==(int)$p->internship_period_id) $fail('Roster tidak sesuai dengan siswa/tag penempatan. Perbaiki hubungan data sebelum pengajuan.');
        if (!$a || $a->status!=='approved' || $a->final_score===null || !is_numeric($a->final_score)
            || (float)$a->final_score<0 || (float)$a->final_score>100) $fail('Penilaian dan nilai akhir resmi harus sudah disahkan.');
        if (!$p->student?->user?->name || !$p->student->nis || !$r->class_name || !$r->major || !$p->company?->name || !$p->period?->name) $fail('Identitas siswa, kelas/jurusan roster, perusahaan, dan tag tahun harus lengkap.');
        if ($a->template_snapshot) {
            $template=$a->template_snapshot;
            if (($template['major']??null)!==$r->major) $fail('Jurusan pada ketentuan nilai tidak cocok dengan roster tahun tersebut. Periksa sumber penilaian.');
            if (empty($template['certificate_heading']) || empty($template['certificate_statement']) || empty($template['fields'])
                || !$a->assessed_on || !$a->industry_assessor || $a->calculated_score===null) $fail('Ketentuan template dan identitas penilaian industri belum lengkap.');
            foreach ($template['fields'] as $field) {
                $value=$a->field_values[$field['key'] ?? ''] ?? null;
                if (!isset($field['key'],$field['label'],$field['type'],$field['description'],$field['weight']) || $value===null || $value===''
                    || ($field['type']==='score' && (!is_numeric($value) || $value<0 || $value>100))) $fail('Ada ketentuan atau hasil penilaian yang belum lengkap.');
            }
        } else {
            if (!$a->scores->count() || $a->scores->contains(fn ($score) => !$score->criterion || $score->score===null)) $fail('Rincian nilai skema lama belum lengkap.');
            // Preserve the legacy gate; the new template flow does not require school journal approval.
            if (!$p->journals()->where('status','approved')->exists() || $p->journals()->where('status','!=','approved')->exists()) $fail('Untuk skema nilai lama, jurnal harus sudah ditinjau seluruhnya.');
        }
        // Class and major belong to the selected year's roster, not the student's current profile.
        $p->student->class_name=$r->class_name; $p->student->major=$r->major;
        $snapshot=[
            'schema'=>1,'number'=>$certificate->number,'version'=>(int)$certificate->version,
            'student'=>['name'=>$p->student->user->name,'nis'=>$p->student->nis,'class_name'=>$r->class_name,'major'=>$r->major],
            'company'=>['name'=>$p->company->name],'period'=>['name'=>$p->period->name],
            'starts_at'=>$r->internship_starts_at->toDateString(),'ends_at'=>$r->internship_ends_at->toDateString(),
            'assessment'=>$a->only(['id','revision','template_snapshot','field_values','industry_assessor','assessed_on','calculated_score','final_score','final_score_reason','notes','approved_at']),
            'assessor'=>['id'=>$a->assessor_id,'name'=>$a->assessor?->name],
            'approver'=>['id'=>$a->approved_by,'name'=>$a->approver?->name],
            'scores'=>$a->scores->sortBy('id')->map(fn ($score)=>['name'=>$score->criterion->name,'weight'=>$score->criterion->weight,
                'display_order'=>$score->criterion->display_order,'score'=>$score->score])->values()->all(),
        ];
        if ($certificate->print_template_id) $snapshot['print']=app(CertificatePrint::class)->source($certificate);
        // Normalize dates/scalars to the same representation as JSON database casts.
        return json_decode(json_encode($snapshot,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
    }

    public function capture(Certificate $certificate, ?int $actor, string $source='submission', ?string $note=null): CertificateDocument
    {
        return $certificate->documents()->create(['version'=>$certificate->version,
            'source'=>$source,'note'=>$note,'captured_by'=>$actor]+$this->render($certificate));
    }

    /** Same render/validation for preview and capture; this method never writes a document. */
    public function render(Certificate $certificate): array
    {
        $snapshot=$this->source($certificate);
        $templated=(bool)$certificate->placement->assessment->template_snapshot;
        $html=view(isset($snapshot['print'])?'certificates.print-pdf':($templated?'certificates.template-pdf':'certificates.pdf'),[
            'certificate'=>$certificate,'print'=>$snapshot['print']??null,'startsAt'=>$certificate->placement->roster->internship_starts_at,
            'endsAt'=>$certificate->placement->roster->internship_ends_at,
        ])->render();
        $docx=[];
        if (isset($snapshot['print']['pdf_background'])) {
            $render=app(CertificatePdfBackground::class)->render($certificate,$snapshot['print']);
            $bytes=$render['pdf']; $html=$render['html'];
        } elseif (isset($snapshot['print']) && $snapshot['print']['front_hash']) {
            $render=app(CertificateDocx::class)->render(\App\Models\CertificatePrintTemplate::findOrFail($certificate->print_template_id),$snapshot['print']['values']);
            $bytes=$render['pdf']; unset($render['pdf']); $docx=$render;
        } else {
        $pdf=Pdf::loadHTML($html)->setPaper('a4',$templated?'portrait':'landscape');
        $pdf->render();
        if ($pdf->getDomPDF()->getCanvas()->get_page_count()!==2) throw ValidationException::withMessages([
            'certificate'=>'Isi dokumen tidak muat tepat dua halaman. Sesuaikan ketentuan/narasi sebelum mengajukan; dokumen belum diterbitkan.',
        ]);
        $bytes=$pdf->output();
        }
        return ['snapshot'=>$snapshot,'rendered_html'=>$html,
            'pdf_base64'=>base64_encode($bytes),'sha256'=>hash('sha256',$bytes)]+$docx;
    }

    public function bytes(CertificateDocument $document): string
    {
        $bytes=base64_decode($document->pdf_base64,true);
        abort_unless(is_string($bytes) && hash_equals($document->sha256,hash('sha256',$bytes)),409,'Arsip PDF gagal pemeriksaan integritas. Hubungi koordinator; jangan membangun ulang dari data hidup.');
        return $bytes;
    }
}
