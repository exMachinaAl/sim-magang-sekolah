<?php

namespace Tests\Feature;

use App\Models\{Assessment, AssessmentSubmission, Placement, User};
use App\Support\AssessmentSubmissionSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class StudentAssessmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
    }

    private function candidate(): Placement
    {
        return Placement::whereDoesntHave('assessment')->whereNotNull('teacher_id')->whereHas('roster')->with(['student.user','roster','teacher'])->firstOrFail();
    }

    private function payload(Placement $placement): array
    {
        $source = app(AssessmentSubmissionSource::class)->for($placement);
        $values = [];
        $score = 80;
        foreach ($source['snapshot']['fields'] as $field) {
            $values[$field['key']] = $field['type'] === 'score' ? $score : 'Hasil pekerjaan sesuai lembar industri.';
        }
        return [
            'placement_id'=>$placement->id, 'industry_assessor'=>'Dodi Pembimbing Industri',
            'assessed_on'=>now('Asia/Jakarta')->toDateString(), 'values'=>$values,
            'final_score'=>$score, 'notes'=>'Disalin siswa dari lembar penilaian akhir industri.',
            'photo'=>UploadedFile::fake()->image('lembar-nilai.jpg', 80, 80),
        ];
    }

    public function test_student_submission_is_private_and_coordinator_can_apply_compatible_criteria(): void
    {
        $placement = $this->candidate();
        $student = $placement->student->user;
        $payload = $this->payload($placement);

        $this->actingAs($student)->get(route('assessments.index'))->assertOk()
            ->assertSee('Setor nilai akhir dari industri')->assertSee('Kirim ke Koordinator');
        $this->post(route('assessments.submissions.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();

        $submission = AssessmentSubmission::firstOrFail();
        $this->assertSame($student->id, $submission->submitted_by);
        $this->assertSame('submitted', $submission->status);
        $this->assertSame($payload['values'], $submission->field_values);
        $this->assertSame('80.00', $submission->calculated_score);
        $this->assertTrue($submission->hasPhoto());
        $photoResponse=$this->get(route('assessments.submissions.photo',$submission))->assertOk()->assertHeader('content-type','image/jpeg');
        $this->assertStringContainsString('inline;',$photoResponse->headers->get('Content-Disposition'));
        $photoDownload=$this->get(route('assessments.submissions.photo',[$submission,'download'=>1]))->assertOk();
        $this->assertStringContainsString('attachment;',$photoDownload->headers->get('Content-Disposition'));

        $other = User::where('role','siswa')->where('id','!=',$student->id)->firstOrFail();
        $this->actingAs($other)->get(route('assessments.submissions.photo',$submission))->assertNotFound();
        $this->post(route('assessments.submissions.store'), $payload)->assertNotFound();

        $teacher=$placement->teacher;
        $this->actingAs($teacher)->get(route('assessments.index'))->assertOk()
            ->assertSee('Setoran nilai dari siswa')->assertSee('Lihat Penilaian &amp; Bukti',false)
            ->assertViewHas('submissions',fn($items)=>$items->contains('id',$submission->id));
        $this->get(route('assessments.student',[$placement,'submission_id'=>$submission->id]))->assertOk()
            ->assertSee('Mode lihat saja')->assertSee('Dodi Pembimbing Industri')->assertSee('Lihat foto bukti lebih besar')
            ->assertDontSee('name="finalize"',false)->assertDontSee('Gunakan setoran ini');
        $this->get(route('assessments.submissions.photo',$submission))->assertOk()->assertHeader('content-type','image/jpeg');
        $otherTeacher=User::where('role','guru')->where('id','!=',$teacher->id)->firstOrFail();
        $this->actingAs($otherTeacher)->get(route('assessments.student',$placement))->assertNotFound();
        $this->get(route('assessments.submissions.photo',$submission))->assertNotFound();

        $coordinator = User::where('role','koordinator')->firstOrFail();
        DB::table('internship_rosters')->where('id',$placement->internship_roster_id)
            ->update(['internship_ends_at'=>now('Asia/Jakarta')->subDay()->toDateString()]);
        $this->actingAs($coordinator)->get(route('assessments.index'))->assertOk()
            ->assertSee('Setoran nilai dari siswa')->assertSee('Periksa Penilaian Siswa')->assertSee('Segera dinilai');
        $this->get(route('assessments.student',[$placement,'submission_id'=>$submission->id]))->assertOk()
            ->assertSee('Dodi Pembimbing Industri')->assertSee('Lihat foto bukti')
            ->assertSee('class="evidence-thumbnail"',false)->assertSee('id="assessment-evidence-lightbox"',false)
            ->assertSee('Unduh gambar')->assertSee('Buka di tab lain')
            ->assertSee('Perlu segera dinilai')->assertSee('1. Setoran dan bukti dari siswa')
            ->assertSee('2. Nilai resmi koordinator')->assertSee('3. Bukti tambahan koordinator')
            ->assertSee('4. Lanjutkan ke sertifikat')->assertSee('Form diprefill dari setoran')->assertSee('value="80"',false);

        $this->post(route('assessments.store'), [
            'placement_id'=>$placement->id, 'template_id'=>$submission->assessment_template_id,
            'submission_id'=>$submission->id, 'revision'=>0, 'intent'=>'submitted',
            'industry_assessor'=>$submission->industry_assessor, 'assessed_on'=>$submission->assessed_on->toDateString(),
            'values'=>$submission->field_values, 'final_score'=>$submission->final_score, 'notes'=>$submission->notes,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $assessment = Assessment::where('placement_id',$placement->id)->firstOrFail();
        $this->assertSame($submission->template_snapshot, $assessment->template_snapshot);
        $this->assertSame($submission->field_values, $assessment->field_values);
        $this->assertSame($coordinator->id, $assessment->assessor_id);
        $this->assertSame('applied', $submission->fresh()->status);
        $this->assertSame($coordinator->id, $submission->fresh()->applied_by);
        $this->assertDatabaseHas('audit_logs',['action'=>'assessment_submission.applied','subject_id'=>$submission->id]);
        $this->post(route('assessments.evidence.store',$assessment),[
            'revision'=>$assessment->revision,'note'=>'Salinan tambahan lembar nilai untuk pemeriksaan koordinator.',
            'file'=>UploadedFile::fake()->image('bukti-koordinator.png',40,40),
        ])->assertSessionHasNoErrors()->assertRedirect(route('assessments.student',$placement));
        $this->get(route('assessments.student',$placement))->assertOk()
            ->assertSee('bukti-koordinator.png')->assertSee('Lihat bukti tambahan lebih besar')
            ->assertSee('Sahkan Nilai')->assertSee('Perlu segera dinilai');
        $this->get(route('assessments.evidence.index',$assessment))->assertOk()
            ->assertSee('Setoran siswa yang pernah diterapkan')->assertSee('Lihat foto sumber siswa')
            ->assertSee('Lihat bukti industri lebih besar')->assertSee('data-evidence-preview',false);

        $evidence=$assessment->evidence()->firstOrFail();
        $this->actingAs($teacher)->get(route('assessments.student',$placement))->assertOk()
            ->assertSee('Mode lihat saja')->assertSee('bukti-koordinator.png')->assertSee('Nilai resmi:')
            ->assertDontSee('Sahkan Nilai')->assertDontSee('Unggah Bukti Tambahan');
        $this->get(route('assessments.evidence.index',$assessment))->assertOk()
            ->assertSee('Informasi penilaian yang dicatat dari industri')->assertSee('Lihat bukti industri lebih besar')
            ->assertDontSee('Unggah Bukti Industri')->assertDontSee('Sahkan setelah bukti diperiksa');
        $this->get(route('assessments.evidence.download',$evidence))->assertOk();
        $this->post(route('assessments.evidence.store',$assessment),[])->assertForbidden();
        $this->put(route('assessments.approve',$assessment),[])->assertForbidden();

        $this->actingAs($student)->get(route('assessments.student',$placement))->assertForbidden();
    }

    public function test_submission_requires_server_side_template_values_and_reason_for_score_difference(): void
    {
        $placement = $this->candidate();
        $payload = $this->payload($placement);
        $this->actingAs($placement->student->user);

        $bad = $payload;
        $firstKey = array_key_first($bad['values']);
        $bad['values'][$firstKey] = 101;
        $this->post(route('assessments.submissions.store'),$bad)->assertSessionHasErrors('values.'.$firstKey);

        $bad = $payload;
        $bad['final_score'] = 90;
        $this->post(route('assessments.submissions.store'),$bad)->assertSessionHasErrors('final_score_reason');
        $this->assertDatabaseCount('assessment_submissions',0);
    }
}
