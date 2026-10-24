<?php
namespace Database\Seeders;

use App\Models\{Assessment, AssessmentTemplate, Certificate, CertificatePrintTemplate, Company, Student, User};
use App\Support\{CertificateArchive, StudentEnrollment};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CertificatePreviewDemoSeeder extends Seeder
{
    public const NIS = 'DEMO-SERT-001';
    public const MARKER = 'DATA DEMO FIKTIF: sertifikat siap uji, bukan dokumen resmi.';

    public function run(): void
    {
        if (!app()->environment(['local','testing'])) throw new \RuntimeException('Contoh sertifikat hanya untuk local/testing.');
        DB::transaction(function () {
            $existing=Student::where('nis',self::NIS)->first();
            if ($existing) {
                if (!$existing->rosters()->where('notes',self::MARKER)->exists()) throw new \RuntimeException('NIS demo sudah dipakai data lain; tidak ada data yang ditimpa.');
                // Preserve the user's edits, approvals, versions, and password on subsequent runs.
                return;
            }
            if (User::where('email','sertifikat.demo@simmag.test')->exists()) throw new \RuntimeException('Email demo sudah dipakai; tidak ada akun yang diubah.');
            if (\App\Models\InternshipRoster::where('nis',self::NIS)->exists()) throw new \RuntimeException('NIS demo sudah ada pada roster; tidak ada roster yang diubah.');
            $actor=User::where('email','koordinator@simmag.test')->where('role','koordinator')->firstOrFail();
            $end=now('Asia/Jakarta')->startOfDay()->subDays(7);
            $student=app(StudentEnrollment::class)->register([
                'nis'=>self::NIS,'nisn'=>'DEMO-NISN-001','name'=>'Siswa Uji Sertifikat (DEMO)',
                'birth_date'=>'2008-11-07','email'=>'sertifikat.demo@simmag.test',
                'major'=>'Kuliner','class_name'=>'XII KUL DEMO',
            ],$actor->id);
            $roster=$student->rosters()->firstOrFail();
            $roster->update(['notes'=>self::MARKER,'internship_status'=>'internship','schedule_source'=>'individual_override',
                'internship_starts_at'=>$end->copy()->subDays(90),'internship_ends_at'=>$end]);
            $company=Company::create(['name'=>'Industri Uji Sertifikat (DEMO)','address'=>'Alamat fiktif untuk pengujian','contact'=>'demo@example.test']);
            $placement=$student->placements()->create(['internship_roster_id'=>$roster->id,'internship_period_id'=>$roster->internship_period_id,
                'company_id'=>$company->id,'teacher_id'=>null]);
            $fields=[]; $mapping=[];
            foreach (config('certificate_print.mapping') as $key=>$label) {
                $fieldKey='field_'.(count($fields)+1); $mapping[$key]=$fieldKey;
                $fields[]=['key'=>$fieldKey,'label'=>$label,'description'=>'Contoh ketentuan '.$label.' untuk praktik kuliner.','weight'=>20,'type'=>'score'];
            }
            $assessmentTemplate=AssessmentTemplate::create(['name'=>'DEMO siap uji — Kuliner 80/20','major'=>'Kuliner','created_by'=>$actor->id,
                'certificate_heading'=>'SERTIFIKAT CONTOH / DEMO',
                'certificate_statement'=>'DATA FIKTIF UNTUK PENGUJIAN SISTEM — BUKAN DOKUMEN RESMI.', 'fields'=>$fields,'is_active'=>true]);
            Assessment::create(['placement_id'=>$placement->id,'assessor_id'=>$actor->id,'approved_by'=>$actor->id,'approved_at'=>now(),
                'status'=>'approved','assessment_template_id'=>$assessmentTemplate->id,'template_snapshot'=>$assessmentTemplate->snapshot(),
                'field_values'=>['field_1'=>80,'field_2'=>82,'field_3'=>85,'field_4'=>89,'field_5'=>90],
                'calculated_score'=>85.2,'final_score'=>85,'final_score_reason'=>'DEMO: nilai resmi contoh 85,00; hasil hitung 85,20 tidak menggantinya.',
                'industry_assessor'=>'Penilai Industri (DEMO)','assessed_on'=>$end,'revision'=>1,'notes'=>self::MARKER]);
            $print=CertificatePrintTemplate::create(['name'=>'DEMO siap uji — PDF bawaan','assessment_template_id'=>$assessmentTemplate->id,
                'front_variant'=>'kepala_sekolah_dan_industri','back_variant'=>'pembimbing_industri','score_mapping'=>$mapping,'created_by'=>$actor->id,
                'settings'=>['nama_sekolah_judul'=>'SEKOLAH CONTOH (DEMO)','nama_sekolah_ttd'=>'SEKOLAH CONTOH (DEMO)',
                    'nama_kepala_sekolah'=>'Kepala Sekolah (DEMO)','nip_kepala_sekolah'=>'DEMO-NIP-001',
                    'bidang_keahlian'=>'Pariwisata (contoh)','program_keahlian'=>'Kuliner','kota_terbit'=>'Gresik']]);
            $certificate=Certificate::create(['placement_id'=>$placement->id,'number'=>'DEMO/SERTIFIKAT/'.$placement->id,
                'status'=>'awaiting_print','issued_at'=>now(),'print_template_id'=>$print->id,
                'print_options'=>['birth_place'=>'Gresik','bidang_pekerjaan'=>'Pengolahan makanan (DEMO)','nisn_nipd'=>'DEMO-NISN-001',
                    'tanggal_pengesahan'=>$end->toDateString(),'nama_pimpinan_industri'=>'Pimpinan Industri (DEMO)']]);
            $certificate->refresh();
            $document=app(CertificateArchive::class)->capture($certificate,$actor->id,'demo',self::MARKER);
            $document->update(['issued_at'=>$certificate->issued_at]);
            $certificate->approvals()->create(['party'=>'coordinator','status'=>'approved','approver_id'=>$actor->id,'decided_at'=>now(),'note'=>self::MARKER]);
            $certificate->approvalEvents()->create(['version'=>$certificate->version,'party'=>'coordinator','decision'=>'approved','approver_id'=>$actor->id,'note'=>self::MARKER]);
        });
    }
}
