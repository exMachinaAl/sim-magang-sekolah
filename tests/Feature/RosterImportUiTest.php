<?php

namespace Tests\Feature;

use App\Models\{InternshipRoster, Student, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RosterImportUiTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::create(['name'=>'Koordinator Impor', 'email'=>'import@test.local', 'role'=>'koordinator', 'is_active'=>true, 'password'=>'password123']);
    }

    private function upload(string $csv)
    {
        return $this->post(route('students.import'), [
            'academic_year'=>'2027/2028', 'roster_file'=>UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ]);
    }

    public function test_import_form_and_examples_are_in_students_not_pkl_control(): void
    {
        $response = $this->actingAs($this->coordinator())->get(route('students.index'));
        $response->assertOk()->assertSee('Impor Data Siswa Massal')->assertSee('Template Kosong (opsional)')
            ->assertSee('Template + Contoh 8 Siswa')->assertSee('Template Jadwal + Contoh')->assertSee('CONTOH001')->assertSee('CONTOH002')
            ->assertSee('name="roster_file"', false)->assertSee('tanggal_lahir')->assertSee('5.000');
        $this->get(route('internships.index'))->assertOk()->assertDontSee('name="roster_file"', false)->assertDontSee('Impor Data Siswa Massal');
    }

    public function test_templates_offer_blank_headers_or_importable_examples_for_all_majors(): void
    {
        $this->actingAs($this->coordinator());
        $blank = $this->get(route('students.template',['example'=>0]))->assertOk()->streamedContent();
        $this->assertSame(1, count(explode("\n", trim($blank))));
        $this->assertStringContainsString('tanggal_lahir', $blank);
        $this->assertStringNotContainsString('mulai_pkl', $blank);
        $csv = $this->get(route('students.template', ['academic_year'=>'2027/2028']))->assertOk()->streamedContent();
        $this->assertStringContainsString('2027/2028', $csv);
        foreach (InternshipRoster::officialMajors() as $major) $this->assertStringContainsString($major, $csv);
        $this->upload($csv)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount('students', 8);
        $this->assertDatabaseCount('internship_rosters', 8);
        $this->assertSame(8, InternshipRoster::whereNull('internship_starts_at')->whereNotNull('student_id')->count());
        $this->assertSame(8, InternshipRoster::where('academic_year','2027/2028')->count());
    }

    public function test_schedule_example_roundtrips_and_student_only_import_preserves_schedule(): void
    {
        $this->actingAs($this->coordinator());
        $csv = $this->get(route('internships.template', ['type'=>'schedule', 'example'=>1, 'academic_year'=>'2027/2028']))->assertOk()->streamedContent();
        $this->upload($csv)->assertSessionHasNoErrors();
        $this->assertSame(8, InternshipRoster::whereDate('internship_starts_at','2027-07-01')->where('schedule_source','individual_override')->count());
        $studentsOnly = $this->get(route('internships.template', ['example'=>1, 'academic_year'=>'2027/2028']))->streamedContent();
        $this->upload($studentsOnly)->assertSessionHasNoErrors();
        $this->assertSame(8, InternshipRoster::whereDate('internship_ends_at','2027-12-31')->count());
        $this->assertDatabaseCount('students', 8);
        $this->upload("nis,nama,kelas,jurusan,status_magang\nCONTOH001,Contoh Siswa 1,XII 1,TITL,tidak_magang\n")->assertSessionHasNoErrors();
        $roster = InternshipRoster::where('nis','CONTOH001')->firstOrFail();
        $this->assertNull($roster->internship_starts_at);
        $this->assertNull($roster->internship_ends_at);
        $this->assertNull($roster->schedule_source);
        $this->assertSame('not_internship', $roster->internship_status);
    }

    public function test_empty_duplicate_malformed_and_invalid_date_imports_are_rejected_without_writes(): void
    {
        $this->actingAs($this->coordinator());
        $header = "nis,nama,kelas,jurusan,tanggal_lahir\n";
        foreach ([
            $header,
            $header."001,Satu,XII 1,TKJ,2008-11-07\n001,Dua,XII 1,TKJ,2008-11-07\n",
            "nis,nama,kelas,jurusan,nis\n001,Satu,XII 1,TKJ,001\n",
            $header."001,Satu,XII 1,TKJ,2008-11-07,extra\n",
            $header."001,Satu,XII 1,TKJ,2008-02-31\n",
            "nis,nama,kelas,jurusan,tanggal_lahir,mulai_pkl,selesai_pkl\n001,Satu,XII 1,TKJ,2008-11-07,2027-02-31,2027-07-01\n",
        ] as $csv) {
            $this->upload($csv)->assertSessionHasErrors('roster_file');
            $this->assertDatabaseCount('students', 0);
            $this->assertDatabaseCount('internship_rosters', 0);
        }
    }

    public function test_semicolon_csv_preserves_zero_prefix_and_uses_default_year(): void
    {
        $this->actingAs($this->coordinator());
        $this->upload("nis;nama;kelas;jurusan;tanggal_lahir\n000123;Nama Siswa;XII 1;TKJ;2008-11-07\n")
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', ['nis'=>'000123']);
        $this->assertDatabaseHas('internship_rosters', ['nis'=>'000123','academic_year'=>'2027/2028']);
    }

    public function test_management_flags_explain_legacy_issues_without_blocking_pages(): void
    {
        $this->actingAs($this->coordinator());
        foreach (['reports.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('RED FLAG — modul belum sepenuhnya sinkron')->assertSee('Buka Kontrol PKL');
        }
        foreach (['placements.index','periods.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('RED FLAG — modul belum sepenuhnya sinkron');
        }
        $this->get(route('students.index'))->assertOk()->assertDontSee('RED FLAG — modul belum sepenuhnya sinkron');
        $this->get(route('internships.index'))->assertOk()->assertDontSee('RED FLAG — modul belum sepenuhnya sinkron');
        $this->get(route('companies.index'))->assertOk()->assertDontSee('RED FLAG — modul belum sepenuhnya sinkron');
        $student = User::create(['name'=>'Siswa', 'role'=>'siswa','is_active'=>true,'password'=>'password123']);
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertDontSee('RED FLAG — modul belum sepenuhnya sinkron');
        $this->get(route('internships.template'))->assertForbidden();
        $this->get(route('students.template'))->assertForbidden();
        $this->upload("nis,nama,kelas,jurusan\n1,Siswa,XII 1,TKJ\n")->assertForbidden();
    }
}
