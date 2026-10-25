<?php

namespace Tests\Feature;

use App\Models\{AssessmentEvidence, CertificateDocument, CertificatePrintTemplate, CertificateWordExport};
use App\Support\{ArchiveBackupProbe, ArchiveIntegrity};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class ArchiveRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
        $this->seed(\Database\Seeders\CertificatePreviewDemoSeeder::class);
        $this->seed(\Database\Seeders\CertificateDemoWordSeeder::class);
        $this->seed(\Database\Seeders\CertificatePdfTemplateDemoSeeder::class);
        $this->seed(\Database\Seeders\AssessmentEvidenceDemoSeeder::class);
    }

    public function test_integrity_checks_database_artifacts_read_only_and_reports_legacy_gap(): void
    {
        $queries=[];
        DB::listen(function($query) use (&$queries) { $queries[]=$query->sql; });
        $report=app(ArchiveIntegrity::class)->inspect(DB::connection());
        $this->assertSame([],$report['failures']);
        $this->assertGreaterThanOrEqual(6,$report['checked_files']);
        foreach ($queries as $query) $this->assertMatchesRegularExpression('/^\s*select\b/i',$query);
        $doc=CertificateDocument::whereNotNull('issued_at')->firstOrFail();
        DB::table('certificate_documents')->where('id',$doc->id)->delete();
        $report=app(ArchiveIntegrity::class)->inspect(DB::connection());
        $this->assertSame(1,$report['legacy_issued_without_archive']);
    }

    public function test_corrupt_pdf_and_missing_background_pair_are_not_treated_as_valid_backups(): void
    {
        $doc=CertificateDocument::firstOrFail();
        DB::table('certificate_documents')->where('id',$doc->id)->update(['pdf_base64'=>base64_encode('different bytes')]);
        $template=CertificatePrintTemplate::whereNotNull('front_pdf')->firstOrFail();
        DB::table('certificate_print_templates')->where('id',$template->id)->update(['back_pdf'=>null,'back_pdf_hash'=>null]);
        $report=app(ArchiveIntegrity::class)->inspect(DB::connection());
        $this->assertTrue(collect($report['failures'])->contains(fn($f)=>$f['table']==='certificate_documents' && $f['id']===$doc->id && $f['field']==='pdf_base64'));
        $this->assertTrue(collect($report['failures'])->contains(fn($f)=>$f['reason']==='incomplete_front_back_pair'));
    }

    public function test_word_source_mismatch_and_wrong_evidence_size_are_reported_without_exposing_contents(): void
    {
        $word=CertificateWordExport::firstOrFail();
        DB::table('certificate_word_exports')->where('id',$word->id)->update(['source_pdf_hash'=>str_repeat('a',64),'front_docx'=>'not-base64!']);
        $evidence=AssessmentEvidence::firstOrFail();
        DB::table('assessment_evidence')->where('id',$evidence->id)->update(['size'=>1]);
        $report=app(ArchiveIntegrity::class)->inspect(DB::connection());
        $this->assertCount(3,$report['failures']);
        $this->assertContains('source_archive_mismatch',array_column($report['failures'],'reason'));
        $text=json_encode($report);
        $this->assertStringNotContainsString('not-base64!',$text);
        $this->assertStringNotContainsString('DEMO-SERT-001',$text);
    }

    public function test_probe_rejects_non_local_environment_and_cannot_be_pointed_at_working_database(): void
    {
        $this->artisan('simmag:verify-archive-backup')->expectsOutputToContain('hanya untuk lingkungan local')->assertFailed();
        $valid='simmag_restore_probe_0123456789abcdef';
        ArchiveBackupProbe::assertTarget('simmag',$valid);
        foreach ([['simmag','simmag'],[$valid,$valid],['simmag','simmag_restore_probe_shared'],['simmag','test;DROP DATABASE simmag'],['bad-name',$valid]] as [$source,$target]) {
            try { ArchiveBackupProbe::assertTarget($source,$target); $this->fail('Unsafe target accepted'); }
            catch (\RuntimeException $e) { $this->assertStringContainsString('tidak aman',$e->getMessage()); }
        }
    }
}
