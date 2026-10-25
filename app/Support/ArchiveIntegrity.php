<?php

namespace App\Support;

use Illuminate\Database\Connection;

/** Read-only checks; never repairs or reconstructs a missing/corrupt artifact. */
class ArchiveIntegrity
{
    public function inspect(Connection $db): array
    {
        $report=['checked_files'=>0,'failures'=>[],'legacy_issued_without_archive'=>0];
        $specs=[
            'certificate_documents'=>['pdf_base64'=>'sha256','front_docx'=>'front_hash','back_docx'=>'back_hash'],
            'certificate_print_templates'=>['front_docx'=>'front_hash','back_docx'=>'back_hash','front_pdf'=>'front_pdf_hash','back_pdf'=>'back_pdf_hash'],
            'certificate_word_exports'=>['front_docx'=>'front_hash','back_docx'=>'back_hash'],
            'assessment_evidence'=>['file_base64'=>'sha256'],
        ];
        foreach ($specs as $table=>$fields) {
            foreach ($db->table($table)->lazyById(50) as $row) {
                foreach ($fields as $field=>$hash) {
                    $required=($table==='certificate_documents' && $field==='pdf_base64')
                        || in_array($table,['certificate_word_exports','assessment_evidence'],true);
                    if (!$required && $row->$field===null && $row->$hash===null) continue;
                    $bytes=is_string($row->$field)?base64_decode($row->$field,true):false;
                    $valid=is_string($bytes) && $bytes!=='' && is_string($row->$hash)
                        && preg_match('/^[a-f0-9]{64}$/',$row->$hash) && hash_equals($row->$hash,hash('sha256',$bytes));
                    if ($valid && $table==='assessment_evidence') $valid=strlen($bytes)===(int)$row->size;
                    $report['checked_files']++;
                    if (!$valid) $report['failures'][]=['table'=>$table,'id'=>$row->id,'field'=>$field,'reason'=>'missing_or_invalid_bytes_hash_size'];
                }
                foreach (['docx','pdf'] as $format) {
                    if (!array_key_exists('front_'.$format,$fields)) continue;
                    if (($row->{'front_'.$format}===null)!==($row->{'back_'.$format}===null)) {
                        $report['failures'][]=['table'=>$table,'id'=>$row->id,'field'=>$format,'reason'=>'incomplete_front_back_pair'];
                    }
                }
                if ($table==='certificate_word_exports') {
                    $source=$db->table('certificate_documents')->where('id',$row->certificate_document_id)->value('sha256');
                    if (!$source || !hash_equals($source,$row->source_pdf_hash??'')) {
                        $report['failures'][]=['table'=>$table,'id'=>$row->id,'field'=>'source_pdf_hash','reason'=>'source_archive_mismatch'];
                    }
                }
            }
        }
        $report['legacy_issued_without_archive']=$db->table('certificates as c')->whereNotNull('c.issued_at')
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('certificate_documents as d')->whereColumn('d.certificate_id','c.id')
                ->whereColumn('d.version','c.version')->whereNotNull('d.issued_at'))->count();
        return $report;
    }
}
