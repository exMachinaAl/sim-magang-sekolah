<?php
namespace App\Support;

use App\Models\{CertificateDocument, CertificatePrintTemplate, CertificateWordExport};
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\{IOFactory, PhpWord, Settings};

class CertificateWordArchive
{
    public function create(CertificateDocument $document, int $actor): CertificateWordExport
    {
        return DB::transaction(function () use ($document,$actor) {
            $document=CertificateDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($document->issued_at,403,'Versi belum disahkan.');
            if ($existing=$document->wordExport) return $existing;
            abort_if($document->front_docx || $document->back_docx,409,'Versi ini sudah memiliki DOCX asli di arsip. Gunakan unduhan yang tersedia.');
            app(CertificateArchive::class)->bytes($document); // Refuse deriving from an archive whose integrity failed.
            $snapshot=$document->snapshot; $print=$snapshot['print']??null;
            if (!$print) CertificatePrint::fail('Arsip lama belum memiliki identitas cetak lengkap. Lengkapi melalui revisi versi baru; tidak menebak data dari profil terkini.');
            $fronts=['kepala_sekolah'=>'sertifikat_kepala_sekolah.docx','kepala_sekolah_dan_industri'=>'sertifikat_kepala_sekolah_dan_industri.docx'];
            $backs=['pembimbing_industri'=>'daftar_nilai_pembimbing_industri.docx','pembimbing_sekolah'=>'daftar_nilai_pembimbing_sekolah.docx'];
            if (!isset($fronts[$print['front_variant']],$backs[$print['back_variant']])) CertificatePrint::fail('Pilihan penandatangan pada arsip tidak dikenali.');
            $engine=app(CertificateDocx::class);
            $front=$engine->sanitize(resource_path('templates/certificates/'.$fronts[$print['front_variant']]));
            $back=$print['score_mapping']
                ? $engine->sanitize(resource_path('templates/certificates/'.$backs[$print['back_variant']]))
                : $this->customBack($snapshot);
            $template=new CertificatePrintTemplate(['front_docx'=>base64_encode($front),'back_docx'=>base64_encode($back),
                'front_hash'=>hash('sha256',$front),'back_hash'=>hash('sha256',$back)]);
            $files=$engine->render($template,$print['values'],false);
            $export=CertificateWordExport::create($files+[
                'certificate_document_id'=>$document->id,'created_by'=>$actor,'source_pdf_hash'=>$document->sha256,
                'layout'=>$print['score_mapping']?'paket_asli_80_20':'depan_asli_belakang_dinamis',
                'template_hashes'=>['front'=>$template->front_hash,'back'=>$template->back_hash],
            ]);
            Audit::record('certificate.word_exported',$export,['certificate_id'=>$document->certificate_id,'version'=>$document->version,'source_pdf_hash'=>$document->sha256]);
            return $export;
        });
    }

    /** Free-form assessments must never be truncated to the package's four fixed aspects. */
    private function customBack(array $snapshot): string
    {
        return app(CertificateDocx::class)->temporary(function ($dir) use ($snapshot) {
            Settings::setOutputEscapingEnabled(true);
            $word=new PhpWord; $word->setDefaultFontName('Arial'); $word->setDefaultFontSize(10);
            $section=$word->addSection(['paperSize'=>'A4','marginTop'=>850,'marginBottom'=>850,'marginLeft'=>850,'marginRight'=>850]);
            $section->addText('DAFTAR NILAI PRAKTIK KERJA LAPANGAN',['bold'=>true,'size'=>14],['alignment'=>'center']);
            foreach (['nama_siswa'=>'Nama','nisn_nipd'=>'NISN / NIPD','kelas'=>'Kelas','konsentrasi_keahlian'=>'Konsentrasi','nama_industri'=>'Industri','nomor_sertifikat'=>'Nomor sertifikat'] as $key=>$label) {
                $section->addText($label.': ${'.$key.'}');
            }
            $table=$section->addTable(['borderSize'=>6,'borderColor'=>'555555','cellMargin'=>70]);
            $table->addRow();
            foreach (['No.','Aspek / ketentuan','Hasil industri','Bobot'] as $title) $table->addCell()->addText($title,['bold'=>true]);
            foreach ($snapshot['assessment']['template_snapshot']['fields'] as $i=>$field) {
                $n=$i+1; $table->addRow(null,['cantSplit'=>true]);
                $table->addCell(500)->addText((string)$n);
                $cell=$table->addCell(6000); $cell->addText('${aspek_'.$n.'}',['bold'=>true]); $cell->addText('${deskripsi_'.$n.'}');
                $table->addCell(1600)->addText('${hasil_'.$n.'}'); $table->addCell(900)->addText('${bobot_'.$n.'}');
            }
            $section->addText('Hasil hitung: ${nilai_hitung}');
            $section->addText('Nilai akhir resmi: ${nilai_akhir_pkl}',['bold'=>true]);
            if ($reason=$snapshot['assessment']['final_score_reason']??null) $section->addText('Keterangan: '.$reason);
            $section->addText('${kota_terbit}, ${tanggal_penilaian}');
            $school=$snapshot['print']['back_variant']==='pembimbing_sekolah';
            $section->addText($school?'Pembimbing sekolah':'Pembimbing industri'); $section->addTextBreak(3);
            $section->addText('${nama_pembimbing}',['bold'=>true]);
            if ($school) $section->addText('NIP ${nip_pembimbing}');
            $path=$dir.'/back.docx'; IOFactory::createWriter($word,'Word2007')->save($path);
            return file_get_contents($path);
        });
    }
}
