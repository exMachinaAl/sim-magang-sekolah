<?php
namespace Database\Seeders;

use App\Models\{MonitoringRecord, Placement, User};
use Illuminate\Database\Seeder;

class MonitoringCaseDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local','testing'])) throw new \RuntimeException('Demo hanya untuk local/testing.');
        $p=Placement::whereHas('roster',fn($q)=>$q->where('nis',CertificatePreviewDemoSeeder::NIS)->where('notes',CertificatePreviewDemoSeeder::MARKER))->firstOrFail();
        $actor=User::where('email','koordinator@simmag.test')->where('role','koordinator')->firstOrFail();
        MonitoringRecord::firstOrCreate(['placement_id'=>$p->id,'notes'=>'DEMO MONITORING: kendala teknis untuk mencoba alur tindak lanjut.'],[
            'created_by'=>$actor->id,'teacher_id'=>null,'visit_date'=>now('Asia/Jakarta')->toDateString(),
            'follow_up'=>'Data fiktif. Silakan tetapkan penanggung jawab, tindak lanjuti, lalu tandai selesai.',
            'category'=>'technical','case_status'=>'open','coordinator_id'=>$actor->id,'revision'=>1,
        ]);
    }
}
