<?php
namespace Database\Seeders;

use App\Models\{Certificate, User};
use App\Support\CertificateWordArchive;
use Illuminate\Database\Seeder;

class CertificateDemoWordSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local','testing'])) throw new \RuntimeException('Hanya untuk contoh local/testing.');
        $certificate=Certificate::whereHas('placement.roster',fn($q)=>$q->where('nis',CertificatePreviewDemoSeeder::NIS)
            ->where('notes',CertificatePreviewDemoSeeder::MARKER))->firstOrFail();
        $document=$certificate->documents()->where('version',$certificate->version)->whereNotNull('issued_at')->firstOrFail();
        $actor=User::where('email','koordinator@simmag.test')->where('role','koordinator')->firstOrFail();
        app(CertificateWordArchive::class)->create($document,$actor->id);
    }
}
