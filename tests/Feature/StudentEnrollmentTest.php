<?php

namespace Tests\Feature;

use App\Models\{InternshipRoster, Student, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::create(['name'=>'Koordinator', 'email'=>'coordinator@test.local', 'password'=>'password123', 'role'=>'koordinator', 'is_active'=>true]);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['name'=>'Siswa Baru', 'nis'=>'001/22.33', 'birth_date'=>'2008-11-07',
            'class_name'=>'XI TKJ', 'major'=>'Teknik Komputer dan Jaringan',
            'academic_year'=>'2026/2027', 'internship_status'=>'pending'], $overrides);
    }

    public function test_student_creation_provisions_nis_login_and_own_dashboard_without_placement(): void
    {
        $this->actingAs($this->coordinator())->post(route('students.store'), $this->data())
            ->assertRedirect()->assertSessionHasNoErrors();
        $student = Student::with('user')->firstOrFail();
        $this->assertNull($student->user->email);
        $this->assertTrue(Hash::check('07112008', $student->user->password));
        $this->assertNotSame('07112008', $student->user->password);
        $this->assertSame('2008-11-07', $student->birth_date->toDateString());
        $this->post(route('logout'));
        $this->post(route('login.attempt'), ['login'=>'001/22.33', 'password'=>'07112008'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($student->user);
        $this->get(route('dashboard'))->assertOk()->assertSee('Data Diri Saya')->assertSee('001/22.33')
            ->assertSee('07/11/2008')->assertSee('Industri belum ditentukan');
        $this->put(route('profile.password'), ['current_password'=>'07112008', 'password'=>'KataSandiBaru123',
            'password_confirmation'=>'KataSandiBaru123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('KataSandiBaru123', $student->user->fresh()->password));
    }

    public function test_roster_registration_reuses_identity_across_years_without_resetting_password(): void
    {
        $this->actingAs($this->coordinator())->post(route('internships.store'), $this->data())
            ->assertSessionHasNoErrors()->assertRedirect();
        $student = Student::with('user')->firstOrFail();
        $student->user->update(['password'=>'ChangedPassword123']);
        $this->post(route('internships.store'), $this->data(['academic_year'=>'2027/2028', 'birth_date'=>null]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('students', 1);
        $this->assertSame(2, InternshipRoster::where('student_id', $student->id)->count());
        $this->assertTrue(Hash::check('ChangedPassword123', $student->user->fresh()->password));
        $this->post(route('internships.store'), $this->data(['academic_year'=>'2028/2029', 'birth_date'=>'2009-11-07']))
            ->assertSessionHasErrors('birth_date');
        $this->assertDatabaseCount('internship_rosters', 2);
    }

    public function test_import_rolls_back_all_accounts_and_rosters_if_any_new_student_lacks_birth_date(): void
    {
        $csv="nis,nama,kelas,jurusan,tanggal_lahir\n001,Satu,XI TKJ,TKJ,2008-11-07\n002,Dua,XI TKJ,TKJ,\n";
        $this->actingAs($this->coordinator())->post(route('internships.import'), [
            'academic_year'=>'2026/2027', 'roster_file'=>UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHasErrors('roster_file');
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('internship_rosters', 0);
        $this->assertSame(0, User::where('role', 'siswa')->count());
    }

    public function test_csv_import_and_reimport_preserve_nis_and_password(): void
    {
        $csv="nis,nama,kelas,jurusan,tanggal_lahir\n00001,Satu,XI TKJ,TKJ,2008-11-07\n";
        $this->actingAs($this->coordinator());
        $payload = fn () => ['academic_year'=>'2026/2027', 'roster_file'=>UploadedFile::fake()->createWithContent('students.csv', $csv)];
        $this->post(route('internships.import'), $payload())->assertSessionHasNoErrors();
        $student = Student::with('user')->firstOrFail();
        $student->user->update(['password'=>'ChangedPassword123']);
        $this->post(route('internships.import'), $payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('internship_rosters', 1);
        $this->assertSame('00001', $student->nis);
        $this->assertTrue(Hash::check('ChangedPassword123', $student->user->fresh()->password));
        $this->assertStringContainsString('2008-11-07', $this->get(route('internships.export', ['academic_year'=>'2026/2027']))->streamedContent());
    }

    public function test_legacy_roster_can_be_linked_and_student_cannot_see_another_roster(): void
    {
        $legacy = InternshipRoster::create(collect($this->data())->except('birth_date')->all());
        InternshipRoster::create(collect($this->data(['nis'=>'OTHER', 'name'=>'Rahasia Siswa Lain']))->except('birth_date')->all());
        $this->actingAs($this->coordinator())->put(route('internships.update', $legacy), [
            'internship_status'=>'pending', 'birth_date'=>'2008-11-07',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($legacy->fresh()->student_id);
        $this->actingAs($legacy->fresh()->student->user)->get(route('dashboard'))->assertOk()
            ->assertSee('Siswa Baru')->assertSee('2026/2027')->assertDontSee('Rahasia Siswa Lain');
        $this->post(route('students.store'), $this->data(['nis'=>'UNAUTHORIZED']))->assertForbidden();
    }

    public function test_invalid_birth_date_and_staff_email_collision_do_not_create_accounts(): void
    {
        $coordinator = $this->coordinator();
        $this->actingAs($coordinator)->post(route('students.store'), $this->data(['birth_date'=>'2099-11-07']))->assertSessionHasErrors('birth_date');
        $this->post(route('internships.store'), $this->data(['email'=>$coordinator->email]))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('internship_rosters', 0);
        $this->assertSame('koordinator', $coordinator->fresh()->role);
    }

    public function test_staff_email_login_and_inactive_student_rejection(): void
    {
        $coordinator = $this->coordinator();
        $this->post(route('login.attempt'), ['email'=>$coordinator->email, 'password'=>'password123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($coordinator);
        $this->post(route('students.store'), $this->data())->assertSessionHasNoErrors();
        Student::firstOrFail()->user->update(['is_active'=>false]);
        $this->post(route('logout'));
        $this->post(route('login.attempt'), ['login'=>'001/22.33', 'password'=>'07112008'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}
