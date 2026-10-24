<?php

namespace Tests\Feature;

use App\Models\{Assessment, AssessmentTemplate, Certificate, Company, InternshipPeriod, Placement, Student, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        return User::create(['name'=>$role,'email'=>uniqid().'@test.local','role'=>$role,'is_active'=>true,'password'=>'password123']);
    }

    private function templateData(): array
    {
        return ['name'=>'TKJ Kompetensi Industri','major'=>'Teknik Komputer dan Jaringan',
            'certificate_heading'=>'SERTIFIKAT PKL','certificate_statement'=>'Telah melaksanakan kompetensi jaringan komputer.',
            'fields'=>[
                ['label'=>'Instalasi jaringan','description'=>'Melakukan crimping dan pengujian kabel UTP.','type'=>'score','weight'=>80],
                ['label'=>'Laporan','description'=>'Laporan kegiatan yang dinilai.','type'=>'score','weight'=>20],
                ['label'=>'Perangkat','description'=>'Jenis perangkat yang dikerjakan.','type'=>'text','weight'=>0],
            ]];
    }

    private function placement(): Placement
    {
        $student=Student::create(['user_id'=>$this->actor('siswa')->id,'nis'=>'001/22.33','class_name'=>'XI TKJ','major'=>'Teknik Komputer dan Jaringan']);
        $company=Company::create(['name'=>'Industri Jaringan']);
        $period=InternshipPeriod::forAcademicYear('2026/2027');
        $roster=\App\Models\InternshipRoster::create(['academic_year'=>'2026/2027','student_id'=>$student->id,'nis'=>$student->nis,'name'=>$student->user->name,
            'class_name'=>$student->class_name,'major'=>$student->major,'internship_status'=>'internship','internship_starts_at'=>'2026-07-01','internship_ends_at'=>'2026-09-30']);
        return Placement::create(['student_id'=>$student->id,'company_id'=>$company->id,'internship_period_id'=>$period->id,'internship_roster_id'=>$roster->id,'teacher_id'=>$this->actor('guru')->id]);
    }

    private function input(Placement $placement, AssessmentTemplate $template): array
    {
        return ['placement_id'=>$placement->id,'template_id'=>$template->id,'revision'=>0,'intent'=>'submitted',
            'industry_assessor'=>'Bapak Industri','assessed_on'=>'2026-09-30',
            'values'=>['field_1'=>90,'field_2'=>80,'field_3'=>'Switch dan router'], 'final_score'=>88];
    }

    public function test_assessment_uses_name_or_nis_search_instead_of_student_dropdown(): void
    {
        $placement = $this->placement();
        $placement->student->user->update(['name'=>'Nama Siswa Dicari']);
        $this->actingAs($this->actor('koordinator'))->get(route('assessments.index'))
            ->assertOk()->assertDontSee('name="placement_id"', false)->assertDontSee('Nama Siswa Dicari');
        foreach (['001/22.33', 'Nama Siswa'] as $search) {
            $this->get(route('assessments.index', ['q'=>$search]))->assertOk()->assertSee('Nama Siswa Dicari')
                ->assertSee('001/22.33')->assertSee('Industri Jaringan')->assertSee('Buka Penilaian')
                ->assertDontSee('<select name="placement_id"', false);
        }
        $this->get(route('assessments.index', ['q'=>'tidak-ditemukan']))->assertOk()->assertSee('Tidak ditemukan penempatan');
        $this->actingAs($this->actor('siswa'))->get(route('assessments.index', ['q'=>'001/22.33']))
            ->assertOk()->assertDontSee('Nama Siswa Dicari');
    }

    public function test_coordinator_template_flows_into_student_assessment_and_two_page_certificate(): void
    {
        $coordinator=$this->actor('koordinator');
        $this->actingAs($coordinator)->post(route('assessment-templates.store'),$this->templateData())->assertSessionHasNoErrors()->assertRedirect();
        $template=AssessmentTemplate::firstOrFail();
        $placement=$this->placement();
        $this->get(route('assessment-templates.index'))->assertOk()->assertSee('Melakukan crimping');
        $this->get(route('assessments.index',['placement_id'=>$placement->id,'template_id'=>$template->id]))->assertOk()->assertSee('Jenis perangkat');
        $this->post(route('assessments.store'),$this->input($placement,$template))->assertSessionHasNoErrors()->assertRedirect();
        $assessment=Assessment::firstOrFail();
        $this->assertSame($coordinator->id,$assessment->assessor_id);
        $this->assertSame('88.00',$assessment->calculated_score);
        $this->assertSame('Switch dan router',$assessment->field_values['field_3']);
        $this->put(route('assessments.approve',$assessment),['decision'=>'approved','revision'=>$assessment->revision])->assertRedirect();
        $this->post(route('assessments.store'),array_replace($this->input($placement,$template),['revision'=>2]))->assertForbidden();

        $copy=$this->templateData(); $copy['name']='Ketentuan baru'; $copy['based_on_id']=$template->id;
        $copy['fields'][0]['description']='Ketentuan berbeda untuk angkatan berikutnya.';
        $this->post(route('assessment-templates.store'),$copy)->assertSessionHasNoErrors();
        $this->assertSame('Melakukan crimping dan pengujian kabel UTP.',$assessment->fresh()->template_snapshot['fields'][0]['description']);
        $this->post(route('certificates.create'),['placement_id'=>$placement->id])->assertSessionHasNoErrors();
        $certificate=Certificate::firstOrFail();
        $this->put(route('certificates.approve',$certificate),['decision'=>'approved','version'=>$certificate->version])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('certificates.pdf',$certificate))->assertOk()->assertHeader('content-type','application/pdf');
        $html=view('certificates.template-pdf',['certificate'=>$certificate->fresh(),'startsAt'=>$placement->roster->internship_starts_at,'endsAt'=>$placement->roster->internship_ends_at])->render();
        $this->assertStringContainsString('Melakukan crimping',$html);
        $this->assertStringContainsString('Switch dan router',$html);
        $this->assertStringContainsString('Telah melaksanakan kompetensi jaringan komputer.',$html);
        $this->assertStringContainsString('001/22.33',$html);
    }

    public function test_wrong_major_bad_values_and_stale_revision_cannot_overwrite_assessment(): void
    {
        $this->actingAs($this->actor('koordinator'))->post(route('assessment-templates.store'),$this->templateData());
        $template=AssessmentTemplate::firstOrFail(); $placement=$this->placement();
        $data=$this->input($placement,$template);
        $placement->roster->update(['major'=>'Kuliner']);
        $this->post(route('assessments.store'),$data)->assertStatus(422);
        $placement->roster->update(['major'=>'Teknik Komputer dan Jaringan']);
        $bad=$data; $bad['values']['field_1']=101;
        $this->post(route('assessments.store'),$bad)->assertSessionHasErrors('values.field_1');
        $bad=$data; $bad['final_score']=90;
        $this->post(route('assessments.store'),$bad)->assertSessionHasErrors('final_score_reason');
        $bad['final_score_reason']='Nilai resmi mengikuti dokumen industri.';
        $this->post(route('assessments.store'),$bad)->assertSessionHasNoErrors();
        $this->post(route('assessments.store'),$data)->assertSessionHasErrors('revision');
        $this->assertSame('90.00',Assessment::first()->final_score);
    }

    public function test_template_permissions_and_weights_are_enforced(): void
    {
        $teacher=$this->actor('guru');
        $this->actingAs($teacher)->get(route('assessment-templates.index'))->assertForbidden();
        $this->post(route('assessment-templates.store'),$this->templateData())->assertForbidden();
        $data=$this->templateData(); $data['fields'][0]['weight']=50;
        $this->actingAs($this->actor('koordinator'))->post(route('assessment-templates.store'),$data)->assertSessionHasErrors('fields');
        $this->assertDatabaseCount('assessment_templates',0);
    }
}
