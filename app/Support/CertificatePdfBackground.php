<?php

namespace App\Support;

use App\Models\{Certificate, CertificatePrintTemplate};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\{Fpdi, PdfParser\StreamReader};

/** Pure PHP renderer; independent of the optional DOCX/LibreOffice conversion. */
class CertificatePdfBackground
{
    public static function defaultLayout(): array
    {
        return array_fill_keys(['front','back'],['x'=>20,'y'=>25,'width'=>170,'height'=>247]);
    }

    public function validateLayout(array $layout): array
    {
        $result=[];
        foreach (['front','back'] as $side) {
            foreach (['x','y','width','height'] as $key) {
                $v=$layout[$side][$key]??null;
                if (!is_numeric($v) || !is_finite((float)$v)) CertificatePrint::fail('Area isi PDF harus berupa angka milimeter lengkap.');
                $result[$side][$key]=round((float)$v,2);
            }
            $box=$result[$side];
            if ($box['x']<5 || $box['y']<5 || $box['width']<120 || $box['height']<140
                || $box['x']+$box['width']>205 || $box['y']+$box['height']>292) {
                CertificatePrint::fail('Area isi '.$side.' harus berada di dalam A4 dengan tepi minimal 5 mm, lebar minimal 120 mm dan tinggi minimal 140 mm.');
            }
        }
        return $result;
    }

    private function page(Fpdi $pdf, string $bytes): int|string
    {
        try {
            $count=$pdf->setSourceFile(StreamReader::createByString($bytes));
            if ($count!==1) CertificatePrint::fail('Setiap desain PDF harus tepat satu halaman. Unggah depan dan belakang secara terpisah.');
            // Import only page artwork, not annotations, actions or external links.
            $page=$pdf->importPage(1);
            $size=$pdf->getTemplateSize($page);
            if (abs($size['width']-210)>0.5 || abs($size['height']-297)>0.5) CertificatePrint::fail('Desain PDF harus A4 portrait (210 × 297 mm).');
            return $page;
        } catch (ValidationException $e) { throw $e; }
        catch (\Throwable $e) {
            CertificatePrint::fail('PDF tidak dapat dibaca. Gunakan PDF standar tanpa sandi; bila perlu ekspor ulang sebagai PDF 1.4.');
        }
    }

    public function normalize(string $bytes): string
    {
        $pdf=new Fpdi;
        $page=$this->page($pdf,$bytes);
        $pdf->AddPage('P',[210,297]);
        $pdf->useTemplate($page,0,0,210,297);
        return $pdf->Output('S');
    }

    public function render(Certificate $certificate, array $print): array
    {
        // Bound archived/legacy text too, before an unbreakable oversized table row reaches Dompdf.
        foreach ($certificate->placement->assessment->template_snapshot['fields'] as $field) {
            if (mb_strlen($field['label'])>100 || mb_strlen($field['description'])>600) {
                CertificatePrint::fail('Aspek PDF melebihi batas: judul 100 karakter dan ketentuan 600 karakter per aspek.');
            }
        }
        foreach ($print['values'] as $value) if (mb_strlen((string)$value)>2000) CertificatePrint::fail('Teks PDF terlalu panjang; ringkas isian sebelum mencetak.');
        $template=CertificatePrintTemplate::findOrFail($print['template_id']);
        $layout=$this->validateLayout($print['pdf_background']['layout']);
        $pdf=new Fpdi; $html=[];
        foreach (['front','back'] as $side) {
            $bytes=base64_decode($template->{$side.'_pdf'}??'',true);
            $expected=$print['pdf_background'][$side.'_hash'];
            if (!is_string($bytes) || !hash_equals($expected,hash('sha256',$bytes))) CertificatePrint::fail('Integritas desain PDF '.$side.' gagal; tidak ada dokumen diterbitkan.');
            $background=$this->page($pdf,$bytes);
            $html[$side]=view('certificates.print-pdf',[
                'certificate'=>$certificate,'print'=>$print,'pdfSide'=>$side,'pdfBox'=>$layout[$side],
            ])->render();
            $content=Pdf::loadHTML($html[$side])->setPaper('a4','portrait')->setOption('isFontSubsettingEnabled',true);
            $content->render();
            if ($content->getDomPDF()->getCanvas()->get_page_count()!==1) {
                CertificatePrint::fail('Isi '.$side.' melebihi area PDF. Perbesar area isi atau ringkas ketentuan; nilai tidak dipotong atau diperkecil otomatis.');
            }
            $overlay=$this->page($pdf,$content->output());
            unset($content);
            gc_collect_cycles();
            $pdf->AddPage('P',[210,297]);
            $pdf->useTemplate($background,0,0,210,297);
            $pdf->useTemplate($overlay,0,0,210,297);
        }
        return ['pdf'=>$pdf->Output('S'),'html'=>implode("\n<!-- Halaman PDF berikutnya -->\n",$html)];
    }

    public function preview(string $bytes): string
    {
        $pdf=new Fpdi;
        $count=$pdf->setSourceFile(StreamReader::createByString($bytes));
        for ($i=1;$i<=$count;$i++) {
            $page=$pdf->importPage($i); $size=$pdf->getTemplateSize($page);
            $pdf->AddPage($size['orientation'],[$size['width'],$size['height']]); $pdf->useTemplate($page);
            $pdf->SetFont('Helvetica','B',10); $pdf->SetTextColor(180,35,35);
            $pdf->SetXY(5,5); $pdf->Cell($size['width']-10,6,'PRATINJAU - BELUM DITERBITKAN',0,0,'C');
        }
        return $pdf->Output('S');
    }

    public function sample(string $side): string
    {
        $pdf=new Fpdi; $pdf->SetAutoPageBreak(false); $pdf->AddPage('P',[210,297]);
        $pdf->SetFillColor(20,51,79); $pdf->Rect(0,0,210,12,'F'); $pdf->Rect(0,285,210,12,'F');
        $pdf->SetDrawColor(177,139,58); $pdf->SetLineWidth(0.7); $pdf->Rect(12,17,186,263);
        $pdf->SetFont('Helvetica','',7); $pdf->SetTextColor(100,100,100);
        $pdf->SetXY(15,277); $pdf->Cell(180,5,'CONTOH DEKORASI - '.strtoupper($side==='front'?'DEPAN':'BELAKANG'),0,0,'C');
        return $pdf->Output('S');
    }
}
