<?php

namespace Tests\Feature;

use App\Models\{Assessment,AssessmentCriterion,AssessmentScore,Certificate,Company,InternshipPeriod,InternshipRoster,Journal,JournalAttachment,Placement,Student,TimelineEvent,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimmagWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-08-20 12:00:00','Asia/Jakarta'));
    }

    private function user(string $role, string $name): User
    {
        return User::create(['role'=>$role, 'name'=>$name,
            'email'=>strtolower(str_replace(' ', '.', $name)).'@test.local', 'password'=>'RahasiaDemo123!', 'is_active'=>true]);
    }

    private function placement(User $student, User $teacher, string $nis): Placement
    {
        $s=Student::create(['user_id'=>$student->id, 'nis'=>$nis, 'class_name'=>'XII RPL 1', 'major'=>'RPL']);
        $company=Company::create(['name'=>'Perusahaan '.$nis]);
        $period=InternshipPeriod::forAcademicYear('2026/2027');
        $roster=InternshipRoster::create(['academic_year'=>'2026/2027','student_id'=>$s->id,'nis'=>$nis,'name'=>$student->name,
            'class_name'=>$s->class_name,'major'=>$s->major,'internship_status'=>'internship','internship_starts_at'=>'2026-07-01','internship_ends_at'=>'2026-09-30']);
        return Placement::create(['student_id'=>$s->id, 'company_id'=>$company->id,
            'internship_period_id'=>$period->id, 'internship_roster_id'=>$roster->id, 'teacher_id'=>$teacher->id, 'status'=>'active']);
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_teacher_cannot_review_other_teachers_journal(): void
    {
        $teacherA=$this->user('guru','Guru A');
        $teacherB=$this->user('guru','Guru B');
        $student=$this->user('siswa','Siswa A');
        $placement=$this->placement($student,$teacherB,'10001');
        $journal=Journal::create(['placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Mengembangkan modul sistem','outcome'=>'Fitur utama selesai','status'=>'submitted']);
        $this->actingAs($teacherA)->put(route('journals.review',$journal),[
            'status'=>'approved'])->assertNotFound();
        $this->assertDatabaseHas('journals',['id'=>$journal->id,'status'=>'submitted']);
    }

    public function test_teacher_cannot_access_unassigned_journal_photo(): void
    {
        $teacherA=$this->user('guru','Guru A');
        $teacherB=$this->user('guru','Guru B');
        $student=$this->user('siswa','Siswa A');
        $placement=$this->placement($student,$teacherB,'10001');
        $journal=Journal::create(['placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Mengembangkan modul sistem','outcome'=>'Fitur utama selesai','status'=>'submitted']);
        $photo=JournalAttachment::create(['journal_id'=>$journal->id,'path'=>'journals/example.png',
            'original_name'=>'example.png','mime'=>'image/png','size'=>1234]);
        $this->actingAs($teacherA)->get(route('journals.photo',$photo))->assertNotFound();
    }

    public function test_teacher_cannot_open_another_teachers_journal_detail(): void
    {
        $teacherA=$this->user('guru','Guru A');
        $teacherB=$this->user('guru','Guru B');
        $student=$this->user('siswa','Siswa A');
        $placement=$this->placement($student,$teacherB,'10007');

        $this->actingAs($teacherA)->get(route('journals.show',$placement))->assertNotFound();
    }

    public function test_student_cannot_submit_other_students_journal(): void
    {
        $teacher=$this->user('guru','Guru A');
        $studentA=$this->user('siswa','Siswa A');
        $studentB=$this->user('siswa','Siswa B');
        $placement=$this->placement($studentB,$teacher,'10002');
        $this->actingAs($studentA)->post(route('journals.store'),[
            'placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Melakukan dokumentasi kegiatan', 'outcome'=>'Selesai', 'intent'=>'draft'
        ])->assertNotFound();
        $this->assertDatabaseCount('journals',0);
    }

    public function test_journal_index_groups_daily_entries_by_placement(): void
    {
        $teacher=$this->user('guru','Guru A');
        $student=$this->user('siswa','Siswa Rekap');
        $placement=$this->placement($student,$teacher,'10003');
        foreach (['2026-08-15','2026-08-16'] as $date) {
            Journal::create(['placement_id'=>$placement->id,'activity_date'=>$date,
                'activity'=>'Mengembangkan modul sistem','outcome'=>'Fitur selesai','status'=>'approved']);
        }

        $response=$this->actingAs($teacher)->get(route('journals.index'));
        $response->assertOk()->assertSee('2 jurnal')->assertSee(route('journals.show',$placement));
        $this->assertSame(1,substr_count($response->getContent(),'Siswa Rekap'));

        $dashboard=$this->actingAs($teacher)->get(route('dashboard'));
        $dashboard->assertOk()->assertSee('Progres Jurnal Siswa')->assertSee('2 jurnal');
        $this->assertSame(1,substr_count($dashboard->getContent(),'Siswa Rekap'));
    }

    public function test_journal_detail_records_revision_and_resubmission_timeline(): void
    {
        $teacher=$this->user('guru','Guru A');
        $student=$this->user('siswa','Siswa Timeline');
        $placement=$this->placement($student,$teacher,'10004');
        $journal=Journal::create(['placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Menyusun dokumentasi sistem','outcome'=>'Dokumen awal selesai','status'=>'submitted']);
        JournalAttachment::create(['journal_id'=>$journal->id,'path'=>'journals/example.png',
            'original_name'=>'example.png','mime'=>'image/png','size'=>1234]);

        $this->actingAs($teacher)->put(route('journals.review',$journal),[
            'revision'=>$journal->fresh()->revision,'review_note'=>'Lengkapi hasil pengujian.',
        ])->assertRedirect();
        $this->actingAs($student)->post(route('journals.store'),[
            'placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Menyusun dokumentasi sistem dan pengujian','outcome'=>'Dokumen lengkap',
            'intent'=>'submitted','revision'=>$journal->fresh()->revision,
        ])->assertRedirect(route('journals.show',$placement));

        $this->assertDatabaseHas('journal_events',['journal_id'=>$journal->id,'status'=>'approved']);
        $this->assertDatabaseHas('journal_events',['journal_id'=>$journal->id,'status'=>'submitted']);
        $this->actingAs($student)->get(route('journals.show',$placement))
            ->assertOk()->assertSee('Timeline Status')->assertSee('Lengkapi hasil pengujian.')->assertSee('Jurnal diperbaiki dan dikirim ulang.');
    }

    public function test_total_journal_photos_cannot_exceed_five_across_edits(): void
    {
        $teacher=$this->user('guru','Guru A');
        $student=$this->user('siswa','Siswa Foto');
        $placement=$this->placement($student,$teacher,'10005');
        $journal=Journal::create(['placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Mendokumentasikan kegiatan harian','outcome'=>'Dokumentasi tersedia','status'=>'draft']);
        for ($i=1;$i<=5;$i++) {
            JournalAttachment::create(['journal_id'=>$journal->id,'path'=>'journals/'.$i.'.png',
                'original_name'=>$i.'.png','mime'=>'image/png','size'=>100]);
        }

        $this->actingAs($student)->post(route('journals.store'),[
            'placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Mendokumentasikan kegiatan harian','outcome'=>'Dokumentasi tersedia',
            'intent'=>'draft','photos'=>[new UploadedFile(
                database_path('seeders/assets/demo-jurnal.png'),'tambahan.png','image/png',null,true
            )],
        ])->assertSessionHasErrors('photos');
        $this->assertDatabaseCount('journal_attachments',5);
    }

    public function test_finished_placement_can_receive_late_journal_for_dates_inside_its_schedule(): void
    {
        $teacher=$this->user('guru','Guru A');
        $student=$this->user('siswa','Siswa Selesai');
        $placement=$this->placement($student,$teacher,'10006');
        $placement->roster->update(['internship_ends_at'=>'2026-08-19']);

        $this->actingAs($student)->post(route('journals.store'),[
            'placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Mencatat kegiatan terdahulu','outcome'=>'Boleh tersimpan untuk tanggal PKL','intent'=>'draft',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('journals',1);
    }

    public function test_retired_role_cannot_open_journal_module(): void
    {
        $legacy=$this->user('legacy_kepala','Kepala Sekolah Lama');
        $this->actingAs($legacy)->get(route('journals.index'))->assertForbidden();
    }

    public function test_deactivated_account_cannot_continue_accessing_pages(): void
    {
        $student=$this->user('siswa','Siswa A');
        $student->update(['is_active'=>false]);
        $this->actingAs($student)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_certificate_requires_assessment_and_approved_journals(): void
    {
        $coordinator=$this->user('koordinator','Koordinator');
        $teacher=$this->user('guru','Guru A');
        $student=$this->user('siswa','Siswa A');
        $placement=$this->placement($student,$teacher,'10001');
        $this->actingAs($coordinator)->post(route('certificates.create'),[
            'placement_id'=>$placement->id])->assertSessionHasErrors('placement_id');
        $this->assertDatabaseCount('certificates',0);
    }

    public function test_teacher_cannot_score_another_teachers_placement(): void
    {
        $teacherA=$this->user('guru','Guru A');
        $teacherB=$this->user('guru','Guru B');
        $student=$this->user('siswa','Siswa A');
        $placement=$this->placement($student,$teacherB,'10001');
        $criterion=AssessmentCriterion::create(['name'=>'Disiplin','weight'=>100,'display_order'=>1]);
        $this->actingAs($teacherA)->post(route('assessments.store'),[
            'placement_id'=>$placement->id,'intent'=>'submitted','scores'=>[$criterion->id=>90]
        ])->assertForbidden();
        $this->assertDatabaseCount('assessments',0);
    }

    public function test_teacher_cannot_input_scores_even_for_assigned_student(): void
    {
        $teacher=$this->user('guru','Guru A');
        $coordinator=$this->user('koordinator','Koordinator');
        $student=$this->user('siswa','Siswa A');
        $placement=$this->placement($student,$teacher,'10001');
        $criterion=AssessmentCriterion::create(['name'=>'Disiplin','weight'=>100,'display_order'=>1]);

        $this->actingAs($teacher)->post(route('assessments.store'),[
            'placement_id'=>$placement->id,'intent'=>'submitted','scores'=>[$criterion->id=>91]
        ])->assertForbidden();
        $this->assertDatabaseCount('assessments',0);
    }

    public function test_certificate_requires_coordinator_approval_before_printing(): void
    {
        $coordinator=$this->user('koordinator','Koordinator');
        $teacher=$this->user('guru','Guru A');
        $student=$this->user('siswa','Siswa A');
        $placement=$this->placement($student,$teacher,'10001');
        Journal::create(['placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Mengembangkan modul sistem','outcome'=>'Fitur utama selesai','status'=>'approved']);
        $criterion=AssessmentCriterion::create(['name'=>'Kedisiplinan','weight'=>100,'display_order'=>1]);
        $assessment=Assessment::create(['placement_id'=>$placement->id,'assessor_id'=>$teacher->id,
            'status'=>'approved','approved_by'=>$coordinator->id,'approved_at'=>now(),'final_score'=>90]);
        AssessmentScore::create(['assessment_id'=>$assessment->id,
            'assessment_criterion_id'=>$criterion->id,'score'=>90]);
        $this->actingAs($coordinator)->post(route('certificates.create'),['placement_id'=>$placement->id])->assertRedirect();
        $certificate=Certificate::firstOrFail();
        $this->assertSame('awaiting_approval',$certificate->status);
        $this->actingAs($coordinator)->put(route('certificates.approve',$certificate),['decision'=>'approved'])->assertRedirect();
        $this->assertSame('awaiting_print',$certificate->fresh()->status);
        $this->assertDatabaseCount('certificate_approval_events',1);

        $pdf=$this->actingAs($coordinator)->get(route('certificates.pdf',$certificate));
        $pdf->assertOk()->assertHeader('content-type','application/pdf');
        $this->assertSame(2,preg_match_all('/\/Type\s*\/Page\b/',$pdf->getContent()));
    }

    public function test_coordinator_handles_certificate_distribution(): void
    {
        $coordinator=$this->user('koordinator','Koordinator');
        $teacher=$this->user('guru','Guru A');
        $student=$this->user('siswa','Siswa A');
        $placement=$this->placement($student,$teacher,'10001');
        $certificate=Certificate::create([
            'placement_id'=>$placement->id,'number'=>'SIMMAG/2026/TEST01','status'=>'awaiting_print'
        ]);

        $this->actingAs($coordinator)->put(route('certificates.movement',$certificate),[
            'expected_status'=>'awaiting_print','recipient'=>'Bagian Administrasi'
        ])->assertRedirect();

        $this->assertSame('printed',$certificate->fresh()->status);
        $this->assertDatabaseHas('certificate_movements',[
            'certificate_id'=>$certificate->id,'actor_id'=>$coordinator->id,
            'from_status'=>'awaiting_print','to_status'=>'printed',
        ]);
    }

    public function test_retired_certificate_officer_cannot_process_certificates(): void
    {
        $legacy=$this->user('legacy_petugas','Petugas Sertifikat Lama');
        $this->actingAs($legacy)->post(route('certificates.create'),['placement_id'=>1])->assertForbidden();
    }

    public function test_admin_cannot_create_a_new_certificate_officer_role(): void
    {
        $admin=$this->user('admin','Admin');
        $this->actingAs($admin)->post(route('users.store'),[
            'name'=>'Petugas Baru','email'=>'petugas.baru@test.local',
            'password'=>'RahasiaDemo123!','role'=>'petugas',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users',['email'=>'petugas.baru@test.local']);
    }

    public function test_admin_is_viewer_and_cannot_manage_coordinator_master_data(): void
    {
        $admin=$this->user('admin','Admin');
        $this->actingAs($admin)->get(route('placements.index'))->assertForbidden();
    }

    public function test_legacy_accounts_cannot_be_reactivated(): void
    {
        $admin=$this->user('admin','Admin');
        $legacy=$this->user('legacy_industri','Pembimbing Lama');
        $legacy->update(['is_active'=>false]);

        $this->actingAs($admin)->put(route('users.toggle',$legacy))->assertStatus(422);
        $this->assertFalse($legacy->fresh()->is_active);
    }

    public function test_demo_seeder_uses_the_new_role_and_approval_model(): void
    {
        $this->seed();

        $this->assertDatabaseMissing('users',['role'=>'industri']);
        $this->assertDatabaseMissing('users',['role'=>'kepala']);
        $this->assertDatabaseMissing('users',['role'=>'petugas']);
        $this->assertDatabaseMissing('certificate_approvals',['party'=>'industry']);
        $this->assertDatabaseMissing('certificate_approvals',['party'=>'school']);
        $this->assertDatabaseHas('certificate_approvals',['party'=>'coordinator']);
        $this->assertSame(Journal::count(),DB::table('journal_events')->distinct()->count('journal_id'));
    }

    public function test_legacy_manual_timeline_endpoints_are_retired(): void
    {
        $coordinator=$this->user('koordinator','Koordinator Timeline Lama');
        $teacher=$this->user('guru','Guru Timeline Lama');
        $student=$this->user('siswa','Siswa Timeline Lama');
        $placement=$this->placement($student,$teacher,'20001');
        $event=TimelineEvent::create([
            'internship_period_id'=>$placement->internship_period_id,'title'=>'Tahap Lama',
            'controller_role'=>'koordinator','starts_at'=>'2026-07-01','ends_at'=>'2026-07-03',
        ]);

        $this->actingAs($coordinator)->put(route('timeline.complete',$event),[
            'action'=>'complete',
        ])->assertStatus(410);
        $this->actingAs($coordinator)->put(route('timeline.update',$event),[
            'title'=>'Tahap Lama','controller_role'=>'koordinator','starts_at'=>'2026-07-01','ends_at'=>'2026-07-03',
        ])->assertStatus(410);
        $this->actingAs($coordinator)->post(route('timeline.store'),[
            'internship_period_id'=>$placement->internship_period_id,'title'=>'Tahap Baru',
            'controller_role'=>'koordinator','starts_at'=>'2026-07-01','ends_at'=>'2026-07-03',
        ])->assertStatus(410);
        $this->assertDatabaseCount('timeline_event_completions',0);
    }

    public function test_date_based_timeline_has_no_manual_completion_control(): void
    {
        $coordinator=$this->user('koordinator','Koordinator Progres Otomatis');
        $teacher=$this->user('guru','Guru Progres Otomatis');
        $student=$this->user('siswa','Siswa Progres Otomatis');
        $placement=$this->placement($student,$teacher,'20007');
        $event=TimelineEvent::create([
            'internship_period_id'=>$placement->internship_period_id,'title'=>'Kelengkapan Jurnal',
            'controller_role'=>'siswa','scope'=>'placement','progress_source'=>'journals',
            'starts_at'=>now()->subDay()->toDateString(),'ends_at'=>now()->addDay()->toDateString(),
        ]);
        Journal::create(['placement_id'=>$placement->id,'activity_date'=>'2026-08-15',
            'activity'=>'Mengembangkan modul sistem','outcome'=>'Fitur utama selesai','status'=>'submitted']);

        $this->actingAs($coordinator)->get(route('timeline.index',[
            'period_id'=>$placement->internship_period_id,'teacher_id'=>$teacher->id,'placement_id'=>$placement->id,
        ]))->assertOk()->assertSee('Rincian Timeline Berbasis Tanggal')
            ->assertSee('Masuk Sekolah')->assertDontSee('Selesaikan');

        $this->actingAs($student)->put(route('timeline.complete',$event),[
            'action'=>'complete','placement_id'=>$placement->id,
        ])->assertStatus(410);
        $this->assertDatabaseCount('timeline_event_completions',0);
    }

    public function test_timeline_navigation_opens_teacher_then_student_instead_of_showing_every_student(): void
    {
        $coordinator=$this->user('koordinator','Koordinator Navigasi');
        $teacher=$this->user('guru','Guru Navigasi');
        $teacher->update(['nip'=>'198701012010011111']);
        $student=$this->user('siswa','Siswa Navigasi');
        $placement=$this->placement($student,$teacher,'20010');
        TimelineEvent::create([
            'internship_period_id'=>$placement->internship_period_id,'title'=>'Timeline Khusus Navigasi',
            'controller_role'=>'siswa','scope'=>'placement','progress_source'=>'manual',
            'starts_at'=>'2026-07-01','ends_at'=>'2026-09-30',
        ]);

        $directory=$this->actingAs($coordinator)->get(route('timeline.index',[
            'period_id'=>$placement->internship_period_id,
        ]));
        $directory->assertOk()->assertSee('Daftar Guru Pembimbing')->assertSee('Guru Navigasi')->assertDontSee('Siswa Navigasi');

        $students=$this->actingAs($coordinator)->get(route('timeline.index',[
            'period_id'=>$placement->internship_period_id,'teacher_id'=>$teacher->id,
        ]));
        $students->assertOk()->assertSee('Daftar Siswa Bimbingan')->assertSee('Siswa Navigasi')->assertSee('Fase Hari Ini');

        $detail=$this->actingAs($coordinator)->get(route('timeline.index',[
            'period_id'=>$placement->internship_period_id,'teacher_id'=>$teacher->id,'placement_id'=>$placement->id,
        ]));
        $detail->assertOk()->assertSee('Rincian Timeline Berbasis Tanggal')->assertSee('Siswa Navigasi')->assertSee('Masuk Sekolah');
    }

    public function test_timeline_search_finds_teacher_nip_and_student_nis(): void
    {
        $coordinator=$this->user('koordinator','Koordinator Pencarian');
        $teacher=$this->user('guru','Guru Bisa Dicari');
        $teacher->update(['nip'=>'197912242005011234']);
        $student=$this->user('siswa','Siswa Bisa Dicari');
        $placement=$this->placement($student,$teacher,'NIS-CARI-01');

        $this->actingAs($coordinator)->get(route('timeline.index',[
            'period_id'=>$placement->internship_period_id,'q'=>'197912242005011234',
        ]))->assertOk()->assertSee('Guru Bisa Dicari')->assertSee('NIP 197912242005011234');

        $this->actingAs($coordinator)->get(route('timeline.index',[
            'period_id'=>$placement->internship_period_id,'q'=>'NIS-CARI-01',
        ]))->assertOk()->assertSee('Siswa Bisa Dicari')->assertSee('NIS NIS-CARI-01');
    }

    public function test_demo_seeder_creates_thirty_school_roster_examples(): void
    {
        $this->seed();

        $this->assertSame(30,InternshipRoster::where('academic_year','2026/2027')->count());
        $this->assertSame(8,InternshipRoster::where('academic_year','2026/2027')->distinct()->count('major'));
        $coordinator=User::where('email','koordinator@simmag.test')->firstOrFail();
        $this->actingAs($coordinator)->get(route('internships.index',['academic_year'=>'2026/2027']))
            ->assertOk()->assertSee('Seluruh siswa')->assertSee('Rekap Per Jurusan')->assertSee('30');
    }

    public function test_coordinator_imports_roster_with_student_accounts(): void
    {
        $coordinator=$this->user('koordinator','Koordinator Impor Roster');
        $csv="tahun_ajaran,nis,nisn,nama,email,kelas,jurusan,status_magang,periode,catatan,tanggal_lahir\n"
            ."2026/2027,IMP001,900000001,Siswa Impor Satu,,XII DKV 1,design komunikasi visual,magang,,Data awal,2008-11-07\n"
            ."2026/2027,IMP002,900000002,Siswa Impor Dua,,XII TKJ 1,teknik komputer jaringan,belum_ditentukan,,,2008-03-12\n";
        $file=UploadedFile::fake()->createWithContent('roster.csv',$csv);

        $this->actingAs($coordinator)->post(route('internships.import'),[
            'academic_year'=>'2026/2027','roster_file'=>$file,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('internship_rosters',['nis'=>'IMP001','internship_status'=>'internship']);
        $this->assertDatabaseHas('internship_rosters',['nis'=>'IMP002','internship_status'=>'pending']);
        $this->assertDatabaseHas('internship_rosters',['nis'=>'IMP001','major'=>'Desain Komunikasi Visual']);
        $this->assertDatabaseHas('internship_rosters',['nis'=>'IMP002','major'=>'Teknik Komputer dan Jaringan']);
        $this->assertDatabaseHas('users',['name'=>'Siswa Impor Satu','role'=>'siswa']);
        $this->assertNotNull(InternshipRoster::where('nis','IMP001')->firstOrFail()->student_id);
    }

    public function test_coordinator_can_apply_internship_status_in_batch_and_export_csv(): void
    {
        $coordinator=$this->user('koordinator','Koordinator Batch Roster');
        $first=InternshipRoster::create(['academic_year'=>'2026/2027','nis'=>'BATCH01','name'=>'Batch Satu',
            'class_name'=>'XII RPL 1','major'=>'RPL','internship_status'=>'pending']);
        $second=InternshipRoster::create(['academic_year'=>'2026/2027','nis'=>'BATCH02','name'=>'Batch Dua',
            'class_name'=>'XII RPL 1','major'=>'RPL','internship_status'=>'pending']);

        $this->actingAs($coordinator)->put(route('internships.bulk'),[
            'roster_ids'=>[$first->id,$second->id],'internship_status'=>'not_internship',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(2,InternshipRoster::whereIn('id',[$first->id,$second->id])
            ->where('internship_status','not_internship')->count());

        $export=$this->actingAs($coordinator)->get(route('internships.export',['academic_year'=>'2026/2027']));
        $export->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8');
        $this->assertStringContainsString('Batch Satu',$export->streamedContent());
    }

    public function test_lifecycle_is_calculated_from_dates_without_manual_confirmation(): void
    {
        $roster=new InternshipRoster(['internship_status'=>'internship']);
        $this->assertSame('unscheduled',$roster->lifecycleState(\Carbon\Carbon::parse('2026-10-22')));

        $roster->internship_starts_at=\Carbon\Carbon::parse('2026-10-25');
        $roster->internship_ends_at=\Carbon\Carbon::parse('2026-12-20');
        $this->assertSame('scheduled',$roster->lifecycleState(\Carbon\Carbon::parse('2026-10-22')));
        $this->assertSame('pkl',$roster->lifecycleState(\Carbon\Carbon::parse('2026-12-20')));
        $this->assertSame('school',$roster->lifecycleState(\Carbon\Carbon::parse('2026-12-21')));
        $this->assertSame('2026-12-21',$roster->returnToSchoolDate()->toDateString());
    }

    public function test_coordinator_schedules_selected_students_and_preserves_individual_exception(): void
    {
        $coordinator=$this->user('koordinator','Koordinator Jadwal Tanggal');
        $major='Teknik Komputer dan Jaringan';
        $individual=InternshipRoster::create([
            'academic_year'=>'2026/2027','nis'=>'DATE01','name'=>'Pengecualian Individual',
            'class_name'=>'XII TKJ 1','major'=>$major,'internship_status'=>'internship',
            'internship_starts_at'=>'2026-08-01','internship_ends_at'=>'2026-11-01',
            'schedule_source'=>'individual_override',
        ]);
        $batchTarget=InternshipRoster::create([
            'academic_year'=>'2026/2027','nis'=>'DATE02','name'=>'Target Batch',
            'class_name'=>'XII TKJ 1','major'=>$major,'internship_status'=>'pending',
        ]);
        $excluded=InternshipRoster::create([
            'academic_year'=>'2026/2027','nis'=>'DATE03','name'=>'Dikecualikan Checkbox',
            'class_name'=>'XII TKJ 1','major'=>$major,'internship_status'=>'pending',
        ]);

        $this->actingAs($coordinator)->post(route('internships.schedule-batch'),[
            'academic_year'=>'2026/2027','name'=>'Batch TKJ Oktober',
            'selected_majors'=>[$major],
            'roster_ids'=>[$individual->id,$batchTarget->id],
            'internship_starts_at'=>'2026-10-01','internship_ends_at'=>'2026-12-31',
            'overwrite_batch_schedules'=>1,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('2026-08-01',$individual->fresh()->internship_starts_at->toDateString());
        $this->assertSame('individual_override',$individual->fresh()->schedule_source);
        $scheduled=$batchTarget->fresh();
        $this->assertSame('internship',$scheduled->internship_status);
        $this->assertSame('2026-10-01',$scheduled->internship_starts_at->toDateString());
        $this->assertSame('2026-12-31',$scheduled->internship_ends_at->toDateString());
        $this->assertSame('major_batch',$scheduled->schedule_source);
        $this->assertNull($excluded->fresh()->internship_starts_at);
        $this->assertDatabaseHas('internship_schedule_batches',['name'=>'Batch TKJ Oktober','student_count'=>1]);

        $this->actingAs($coordinator)->put(route('internships.update',$individual),[
            'internship_status'=>'not_internship',
            'internship_starts_at'=>'2026-08-01','internship_ends_at'=>'2026-11-01',
        ])->assertRedirect();
        $individual->refresh();
        $this->assertNull($individual->internship_starts_at);
        $this->assertNull($individual->internship_ends_at);
        $this->assertNull($individual->schedule_source);
    }

    public function test_non_coordinator_cannot_manage_school_internship_roster(): void
    {
        $teacher=$this->user('guru','Guru Bukan Koordinator Roster');
        $this->actingAs($teacher)->get(route('internships.index'))->assertForbidden();
    }

    public function test_active_roles_can_render_their_core_pages(): void
    {
        $this->seed();
        $pages = [
            'admin@simmag.test' => ['dashboard','journals.index','monitoring.index','assessments.index','certificates.index','timeline.index','users.index'],
            'koordinator@simmag.test' => ['dashboard','journals.index','monitoring.index','assessments.index','certificates.index','timeline.index','placements.index','students.index','internships.index','reports.index'],
            'guru@simmag.test' => ['dashboard','journals.index','monitoring.index','assessments.index','certificates.index','timeline.index'],
            'siswa@simmag.test' => ['dashboard','journals.index','assessments.index','certificates.index','timeline.index'],
        ];

        foreach ($pages as $email => $routes) {
            $user=User::where('email',$email)->firstOrFail();
            foreach ($routes as $routeName) {
                $this->actingAs($user)->get(route($routeName))->assertOk();
            }
        }
    }
}
