<?php

namespace Database\Seeders;

use App\Models\{Assessment, AssessmentTemplate, Certificate, Company, InternshipPeriod, InternshipRoster, InternshipScheduleBatch, Journal, Placement, User};
use App\Support\StudentEnrollment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local','testing'])) throw new RuntimeException('Seeder demo hanya untuk local/testing.');
        DB::transaction(function () {
            $staff = [];
            foreach ([
                ['admin@simmag.test','Admin SIMMAG','admin'],
                ['koordinator@simmag.test','Koordinator PKL 1','koordinator'],
                ['koordinator2@simmag.test','Koordinator PKL 2','koordinator'],
                ['guru@simmag.test','Rina Wulandari (Guru A)','guru'],
                ['guru2@simmag.test','Budi Hartono (Guru B)','guru'],
            ] as $index=>[$email,$name,$role]) {
                $staff[$email] = User::firstOrCreate(['email'=>$email],[
                    'name'=>$name,'role'=>$role,'is_active'=>true,'password'=>'password123','nip'=>'19800101201001000'.($index+1),
                ]);
            }
            $coordinator=$staff['koordinator@simmag.test'];
            $today=now('Asia/Jakarta')->startOfDay();
            $year=InternshipRoster::currentAcademicYear();
            $tag=InternshipPeriod::forAcademicYear($year);
            $companies=collect(['PT Mitra Teknologi (Demo)','CV Mitra Industri (Demo)','Hotel dan Kuliner Cerme (Demo)'])
                ->map(fn($name)=>Company::firstOrCreate(['name'=>$name],['address'=>'Gresik, Jawa Timur (data fiktif)','contact'=>'demo@example.test']));
            $majors=InternshipRoster::officialMajors();
            $templates=[]; $batches=[];
            foreach ($majors as $major) {
                $templates[$major]=AssessmentTemplate::firstOrCreate(['name'=>'Ketentuan Industri — '.$major],[
                    'major'=>$major,'certificate_heading'=>'SERTIFIKAT PRAKTIK KERJA LAPANGAN',
                    'certificate_statement'=>'Telah melaksanakan Praktik Kerja Lapangan dan penilaian kompetensi dari industri.',
                    'fields'=>[
                        ['key'=>'field_1','label'=>'Kompetensi bidang','description'=>'Keterampilan praktik pada bidang '.$major.'.','type'=>'score','weight'=>60],
                        ['key'=>'field_2','label'=>'Disiplin dan tanggung jawab','description'=>'Kehadiran, ketepatan waktu, dan penyelesaian tugas.','type'=>'score','weight'=>20],
                        ['key'=>'field_3','label'=>'Keselamatan kerja','description'=>'Kepatuhan terhadap prosedur keselamatan kerja.','type'=>'score','weight'=>20],
                        ['key'=>'field_4','label'=>'Lingkup pekerjaan','description'=>'Uraian pekerjaan yang dinilai oleh industri.','type'=>'text','weight'=>0],
                    ],'created_by'=>$coordinator->id,'is_active'=>true,
                ]);
                $batches[$major]=InternshipScheduleBatch::firstOrCreate(['academic_year'=>$year,'name'=>'Batch Demo '.$major],[
                    'scope'=>'major','selected_majors'=>[$major],'starts_at'=>$today->copy()->subDays(14),
                    'ends_at'=>$today->copy()->addDays(75),'student_count'=>0,'created_by'=>$coordinator->id,
                ]);
            }
            $demoPath='journals/demo-kegiatan-pkl.png';
            if (!Storage::disk('local')->exists($demoPath)) Storage::disk('local')->put($demoPath,file_get_contents(__DIR__.'/assets/demo-jurnal.png'));
            $names=['Andi Saputra','Sinta Rahma','Raka Pratama','Nabila Putri','Dimas Setiawan','Alya Maharani','Bima Prasetya','Citra Lestari'];
            for ($index=0; $index<30; $index++) {
                $major=$majors[$index%8];
                $nis='2026'.str_pad($index+1,3,'0',STR_PAD_LEFT);
                $existing=InternshipRoster::where('nis',$nis)->where('academic_year',$year)->exists();
                $student=app(StudentEnrollment::class)->register([
                    'academic_year'=>$year,'nis'=>$nis,'nisn'=>'2026'.str_pad($index+1,6,'0',STR_PAD_LEFT),
                    'name'=>$names[$index%8].' (Demo '.($index+1).')',
                    'birth_date'=>'2008-11-07','email'=>'siswa'.($index===0?'':($index+1)).'@simmag.test',
                    'class_name'=>'XII '.($index%8+1),'major'=>$major,
                ],$coordinator->id);
                $roster=$student->rosters()->where('academic_year',$year)->firstOrFail();
                $phase=$index%5;
                if (!$existing) {
                    [$from,$to]=match($phase){
                        0=>[$today->copy()->subDays(120),$today->copy()->subDays(7)],
                        1=>[$batches[$major]->starts_at,$batches[$major]->ends_at],
                        2=>[$today->copy()->addDays(15),$today->copy()->addDays(105)],
                        default=>[null,null],
                    };
                    $roster->update(['internship_status'=>$phase===4?'not_internship':($phase<3?'internship':'pending'),
                        'internship_starts_at'=>$from,'internship_ends_at'=>$to,
                        'schedule_source'=>$phase===1?'major_batch':($phase<3?'individual_override':null),
                        'schedule_batch_id'=>$phase===1?$batches[$major]->id:null,
                        'notes'=>'DATA DEMO FIKTIF: tag bersama, jadwal siswa berbeda.',
                    ]);
                }
                // Twelve students remain visible for testing bulk placement (six are explicitly not participating).
                if ($phase>=3) continue;
                $placement=Placement::firstOrCreate(['internship_roster_id'=>$roster->id],[
                    'student_id'=>$student->id,'internship_period_id'=>$tag->id,'company_id'=>$companies[$index%3]->id,
                    'teacher_id'=>$index%3===0?null:$staff[$index%2===0?'guru@simmag.test':'guru2@simmag.test']->id,
                ]);
                if ($phase===2) continue;
                $date=$phase===0?$roster->internship_ends_at:$today->copy()->subDay();
                $journal=Journal::where('placement_id',$placement->id)->whereDate('activity_date',$date->toDateString())->first()
                    ?? Journal::create(['placement_id'=>$placement->id,'activity_date'=>$date->toDateString(),
                    'activity'=>'Melaksanakan praktik kompetensi '.$major,'outcome'=>'Dokumentasi pekerjaan selesai.',
                    'status'=>$phase===0?'approved':'submitted','reviewed_by'=>$phase===0?$coordinator->id:null,
                    'reviewed_at'=>$phase===0?now():null,
                    'reviewed_revision'=>$phase===0?1:null,'revision'=>1,'industry_score'=>88.25,
                    'work_area'=>'Area praktik (Demo)','starts_time'=>'08:00','ends_time'=>'16:00',
                    'equipment_notes'=>'Peralatan praktik sesuai jurusan (data fiktif).',
                ]);
                $journal->attachments()->firstOrCreate(['path'=>$demoPath],[
                    'original_name'=>'foto-jurnal-demo.png','mime'=>'image/png','size'=>Storage::disk('local')->size($demoPath),
                ]);
                $journal->events()->firstOrCreate(['status'=>$journal->status],[
                    'actor_id'=>$phase===0?$coordinator->id:$student->user_id,'note'=>'Dokumentasi alur demo.',
                ]);
                if ($phase!==0) continue;
                $template=$templates[$major];
                Assessment::firstOrCreate(['placement_id'=>$placement->id],[
                    'assessor_id'=>$coordinator->id,'approved_by'=>$coordinator->id,'approved_at'=>now(),
                    'status'=>'approved','assessment_template_id'=>$template->id,'template_snapshot'=>$template->snapshot(),
                    'field_values'=>['field_1'=>90,'field_2'=>85,'field_3'=>90,'field_4'=>'Pekerjaan praktik sesuai jurusan.'],
                    'calculated_score'=>89,'final_score'=>89,'industry_assessor'=>'Penilai Mitra Industri (Demo)',
                    'assessed_on'=>$roster->internship_ends_at,'revision'=>1,'notes'=>'Nilai industri dicatat koordinator; contoh fiktif.',
                ]);
                if ($index>5) continue;
                $issued=$index===5;
                $certificate=Certificate::firstOrCreate(['placement_id'=>$placement->id],[
                    'number'=>'SIMMAG/'.$today->year.'/DEMO'.str_pad($index+1,3,'0',STR_PAD_LEFT),
                    'status'=>$issued?'awaiting_print':'awaiting_approval','issued_at'=>$issued?now():null,
                ]);
                // Only new demo certificates are captured. Never reconstruct existing issued documents implicitly.
                if ($certificate->wasRecentlyCreated) {
                    $certificate->refresh();
                    $archive=app(\App\Support\CertificateArchive::class)->capture($certificate,$coordinator->id,'demo');
                    if ($issued) $archive->update(['issued_at'=>$certificate->issued_at]);
                }
                $certificate->approvals()->firstOrCreate(['party'=>'coordinator'],[
                    'status'=>$issued?'approved':'pending','approver_id'=>$issued?$coordinator->id:null,'decided_at'=>$issued?now():null,
                ]);
                if ($issued) $certificate->approvalEvents()->firstOrCreate(['version'=>1,'party'=>'coordinator'],[
                    'decision'=>'approved','approver_id'=>$coordinator->id,'note'=>'Pengesahan contoh.',
                ]);
            }
            foreach ($batches as $batch) $batch->update(['student_count'=>InternshipRoster::where('schedule_batch_id',$batch->id)->count()]);
        });
    }
}
