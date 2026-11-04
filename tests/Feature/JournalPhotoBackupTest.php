<?php
namespace Tests\Feature;

use App\Models\JournalAttachment;
use App\Support\JournalPhotoBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class JournalPhotoBackupTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local'); $this->seed();
        Storage::disk('local')->makeDirectory('backups');
    }

    private function backup(): array
    {
        $service=app(JournalPhotoBackup::class); $disk=Storage::disk('local');
        $manifest=$service->snapshot(DB::connection(),$disk->path(''));
        $zip=$disk->path('backups/photos.zip');
        $service->write($manifest,$disk->path(''),$zip);
        return [$service,$manifest,$zip,$disk->path('backups')];
    }

    public function test_photos_restore_into_separate_directory_with_hashes_and_deduplication(): void
    {
        [$service,$manifest,$zip,$parent]=$this->backup();
        $result=$service->verifyRestore(DB::connection(),$zip,$parent,$manifest);
        $this->assertSame(JournalAttachment::count(),$result['attachments']);
        $this->assertSame(count(array_unique(array_column($manifest,'path'))),$result['unique_files']);
        $this->assertTrue($result['verified']);
        $this->assertSame($manifest,$service->snapshot(DB::connection(),Storage::disk('local')->path('')));
        $this->assertSame([],glob($parent.'/photo-restore-*'));
    }

    public function test_missing_photo_cannot_be_declared_a_successful_backup(): void
    {
        $attachment=JournalAttachment::firstOrFail();
        Storage::disk('local')->delete($attachment->path);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('hilang');
        app(JournalPhotoBackup::class)->snapshot(DB::connection(),Storage::disk('local')->path(''));
    }

    public function test_same_length_corruption_in_zip_is_detected_and_temporary_files_removed(): void
    {
        [$service,$manifest,$path,$parent]=$this->backup();
        $zip=new ZipArchive; $zip->open($path);
        $zip->addFromString('files/'.$manifest[0]['path'],str_repeat('x',$manifest[0]['size'])); $zip->close();
        try { $service->verifyRestore(DB::connection(),$path,$parent,$manifest); $this->fail('Corruption accepted'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('hash',$e->getMessage()); }
        $this->assertSame([],glob($parent.'/photo-restore-*'));
        $this->assertSame($manifest,$service->snapshot(DB::connection(),Storage::disk('local')->path('')));
    }

    public function test_path_traversal_and_undeclared_zip_entries_are_rejected(): void
    {
        [$service,$manifest,$path,$parent]=$this->backup();
        $zip=new ZipArchive; $zip->open($path); $zip->addFromString('../outside.txt','do not extract'); $zip->close();
        try { $service->verifyRestore(DB::connection(),$path,$parent,$manifest); $this->fail('Extra entry accepted'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('di luar manifest',$e->getMessage()); }
        $this->assertFileDoesNotExist(dirname($parent).'/outside.txt');
        JournalAttachment::firstOrFail()->update(['path'=>'journals/../../.env']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak aman');
        $service->snapshot(DB::connection(),Storage::disk('local')->path(''));
    }

    public function test_restored_database_must_reference_the_same_photos(): void
    {
        [$service,$manifest,$path,$parent]=$this->backup();
        JournalAttachment::firstOrFail()->update(['size'=>1]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Referensi foto');
        $service->verifyRestore(DB::connection(),$path,$parent,$manifest);
    }
}
