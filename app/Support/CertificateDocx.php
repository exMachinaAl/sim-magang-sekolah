<?php
namespace App\Support;

use App\Models\CertificatePrintTemplate;
use Illuminate\Filesystem\Filesystem;
use PhpOffice\PhpWord\{Settings, TemplateProcessor};
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

class CertificateDocx
{
    public function temporary(callable $callback): mixed
    {
        $root=storage_path('app/private/certificate-render');
        if (!is_dir($root)) mkdir($root,0700,true);
        $dir=$root.'/'.bin2hex(random_bytes(16)); mkdir($dir,0700);
        try { return $callback($dir); } finally { (new Filesystem)->deleteDirectory($dir); }
    }

    public function allowedTokens(): array
    {
        $tokens=array_merge(array_keys(config('certificate_print.settings')),['nomor_sertifikat','nama_siswa','nis','nisn_nipd','kelas','konsentrasi_keahlian',
            'tempat_tanggal_lahir','bidang_pekerjaan','nama_industri','tanggal_mulai_pkl','tanggal_selesai_pkl','tanggal_pengesahan',
            'nama_pimpinan_industri','nama_pembimbing','nip_pembimbing','tanggal_penilaian','nilai_akhir_pkl','nilai_hitung','nilai_rata_rata_aspek']);
        foreach (array_keys(config('certificate_print.mapping')) as $key) { $tokens[]='nilai_'.$key; $tokens[]='deskripsi_'.$key; }
        for ($i=1;$i<=12;$i++) foreach (['aspek','deskripsi','hasil','bobot'] as $prefix) $tokens[]=$prefix.'_'.$i;
        return $tokens;
    }

    public function inspect(string $path, bool $allowLegacyMailMerge = false): array
    {
        $zip=new \ZipArchive;
        if ($zip->open($path)!==true) CertificatePrint::fail('Unggahan harus DOCX yang valid.');
        try {
            if ($zip->numFiles>600 || $zip->locateName('word/document.xml')===false) CertificatePrint::fail('Struktur DOCX tidak didukung.');
            $total=0;
            for ($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i); $total+=$stat['size']; $name=$stat['name'];
                if ($total>40*1024*1024 || str_contains($name,'..') || str_starts_with($name,'/') || str_contains($name,'\\')
                    || preg_match('/vba|embeddings|activeX|\.bin$/i',$name)) CertificatePrint::fail('DOCX terlalu besar atau mengandung konten aktif/tertanam yang tidak diizinkan.');
                if (preg_match('/\.(xml|rels)$/i',$name)) {
                    $xml=$zip->getFromIndex($i);
                    if (preg_match('/<!DOCTYPE|<!ENTITY|INCLUDETEXT|INCLUDEPICTURE|DDEAUTO|altChunk/i',$xml)) CertificatePrint::fail('DOCX dengan field dinamis atau konten aktif ditolak.');
                    $doc=new \DOMDocument;
                    if (!@$doc->loadXML($xml,LIBXML_NONET)) CertificatePrint::fail('XML DOCX tidak valid.');
                    foreach ($doc->getElementsByTagName('Relationship') as $relation) {
                        if (strtolower($relation->getAttribute('TargetMode'))!=='external') continue;
                        if (!$allowLegacyMailMerge || $name!=='word/_rels/settings.xml.rels'
                            || !preg_match('~/(mailMergeSource|recipientData)$~',$relation->getAttribute('Type'))) CertificatePrint::fail('DOCX dengan sumber eksternal ditolak.');
                    }
                }
            }
        } finally { $zip->close(); }
        $processor=new TemplateProcessor($path); $tokens=$processor->getVariables();
        $unknown=array_diff($tokens,$this->allowedTokens());
        if ($unknown) CertificatePrint::fail('Token DOCX tidak dikenali: '.implode(', ',$unknown));
        return $tokens;
    }

    /** Remove ONLY old Word mail-merge bindings; all other external relationships are rejected. */
    public function sanitize(string $path): string
    {
        $this->inspect($path,true);
        return $this->temporary(function($dir) use($path) {
            $copy=$dir.'/clean.docx'; copy($path,$copy); $zip=new \ZipArchive; $zip->open($copy);
            foreach (['word/settings.xml','word/_rels/settings.xml.rels'] as $part) {
                $xml=$zip->getFromName($part); if ($xml===false) continue;
                $doc=new \DOMDocument; $doc->loadXML($xml,LIBXML_NONET); $xp=new \DOMXPath($doc);
                $query=$part==='word/settings.xml'?'//*[local-name()="mailMerge"]':'//*[local-name()="Relationship" and (contains(@Type,"/mailMergeSource") or contains(@Type,"/recipientData"))]';
                foreach (iterator_to_array($xp->query($query)) as $node) $node->parentNode->removeChild($node);
                $zip->addFromString($part,$doc->saveXML());
            }
            $zip->close(); $this->inspect($copy);
            return file_get_contents($copy);
        });
    }

    public function render(CertificatePrintTemplate $template, array $values, bool $convert = true): array
    {
        return $this->temporary(function ($dir) use ($template,$values,$convert) {
            $result=[]; $files=[];
            foreach (['front','back'] as $side) {
                $bytes=base64_decode($template->{$side.'_docx'},true);
                if ($bytes===false || !hash_equals($template->{$side.'_hash'},hash('sha256',$bytes))) CertificatePrint::fail('Integritas template DOCX gagal.');
                $input=$dir.'/'.$side.'-template.docx'; file_put_contents($input,$bytes);
                $tokens=$this->inspect($input);
                foreach ($tokens as $token) if (!array_key_exists($token,$values) || $values[$token]==='') CertificatePrint::fail('Data untuk token '.$token.' belum tersedia.');
                Settings::setOutputEscapingEnabled(true);
                $processor=new TemplateProcessor($input); $processor->setValues($values);
                if ($processor->getVariables()) CertificatePrint::fail('Masih ada token yang belum terisi di DOCX.');
                $output=$dir.'/'.$side.'.docx'; $processor->saveAs($output);
                $filled=file_get_contents($output); $result[$side.'_docx']=base64_encode($filled); $result[$side.'_hash']=hash('sha256',$filled);
                $files[]=$output;
            }
            if ($convert) $result['pdf']=$this->convert($files,$dir);
            return $result;
        });
    }

    public function convert(array $files, string $dir): string
    {
        $binary=config('certificate_print.libreoffice');
        if (!$binary || !is_file($binary)) CertificatePrint::fail('Konversi DOCX belum siap. Administrator perlu memasang LibreOffice dan mengisi CERTIFICATE_LIBREOFFICE; atau gunakan template PDF bawaan. Tidak ada dokumen yang diterbitkan.');
        // Separate profile per render avoids collisions with another request or the user's open office session.
        $uri='file:///'.str_replace('%3A',':',implode('/',array_map('rawurlencode',explode('/',str_replace('\\','/',$dir.'/profile')))));
        if (DIRECTORY_SEPARATOR==='/') $uri='file://'.str_replace('%2F','/',rawurlencode($dir.'/profile'));
        $process=new Process([$binary,'-env:UserInstallation='.$uri,'--headless','--nologo','--nodefault','--norestore',
            '--convert-to','pdf:writer_pdf_Export','--outdir',$dir,...$files]);
        $process->setTimeout(60);
        if (PHP_OS_FAMILY==='Windows') $process->setOptions(['create_new_console'=>false]);
        try { $process->run(); } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) { CertificatePrint::fail('Konversi DOCX melewati batas waktu. Coba lagi atau periksa template.'); }
        if (!$process->isSuccessful()) CertificatePrint::fail('Konversi DOCX gagal. Periksa instalasi LibreOffice dan template; dokumen tidak diterbitkan.');
        return $this->merge(array_map(fn($path)=>substr($path,0,-5).'.pdf',$files));
    }

    public function merge(array $files): string
    {
        $pdf=new Fpdi;
        foreach ($files as $file) {
            if (!is_file($file)) CertificatePrint::fail('Konversi belum menghasilkan PDF lengkap.');
            try { $count=$pdf->setSourceFile($file); } catch (\Throwable $e) { CertificatePrint::fail('PDF hasil konversi tidak dapat dibaca.'); }
            if ($count!==1) CertificatePrint::fail('Masing-masing DOCX harus tepat satu halaman. Perpendek isi atau sesuaikan layout; tidak ada halaman yang dipotong.');
            $page=$pdf->importPage(1); $size=$pdf->getTemplateSize($page);
            if (abs($size['width']-210)>2 || abs($size['height']-297)>2) CertificatePrint::fail('Template DOCX harus A4 portrait untuk cetak depan–belakang.');
            $pdf->AddPage('P',[$size['width'],$size['height']]); $pdf->useTemplate($page);
        }
        return $pdf->Output('S');
    }
}
