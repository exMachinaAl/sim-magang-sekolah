<?php

namespace Tests\Feature;

use App\Models\{InternshipRoster, Student, User};
use App\Support\StudentEnrollment;
use Database\Seeders\StudentPklDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-23 12:00:00','Asia/Jakarta'));
    }

    private function actor(): User
    {
        return User::create(['name'=>'Koordinator','email'=>'dir@test.local','password'=>'password123','role'=>'koordinator','is_active'=>true]);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['nis'=>'000001','name'=>'Nama Unik Siswa','birth_date'=>'2008-11-07',
            'class_name'=>'XII TKJ 1','major'=>'Teknik Komputer dan Jaringan','academic_year'=>'2026/2027'], $overrides);
    }

    public function test_root_shows_cards_without_student_payload_and_search_opens_student_in_major(): void
    {
        $first = app(StudentEnrollment::class)->register($this->data());
        $other = app(StudentEnrollment::class)->register($this->data(['nis'=>'000002','name'=>'Siswa Kuliner Lain','major'=>'Kuliner']));
        $roster = $first->rosters()->firstOrFail();
        $this->actingAs($this->actor())->get(route('internships.index'))->assertOk()
            ->assertSee('Direktori jurusan')->assertSee('Kuliner')->assertSee('Teknik Komputer dan Jaringan')
            ->assertDontSee('Nama Unik Siswa')->assertDontSee('Siswa Kuliner Lain')->assertDontSee('name="roster_ids[]"', false)
            ->assertDontSee('Daftar Seluruh Siswa');
        foreach (['Nama Unik','000001'] as $query) {
            $this->get(route('internships.index',['q'=>$query]))->assertOk()->assertSee('Nama Unik Siswa')
                ->assertSee('Buka siswa di jurusan')->assertDontSee('Siswa Kuliner Lain');
        }
        $this->get(route('internships.index',['major'=>'Teknik Komputer dan Jaringan','roster_id'=>$roster->id]))
            ->assertOk()->assertSee('Nama Unik Siswa')->assertDontSee('Siswa Kuliner Lain')->assertSee('Atur individual');
        $this->get(route('internships.index',['q'=>'Tidak Ada']))->assertOk()->assertSee('Tidak ada siswa yang sesuai filter');
    }

    public function test_department_search_by_alias_and_year_scoping(): void
    {
        app(StudentEnrollment::class)->register($this->data());
        app(StudentEnrollment::class)->register($this->data(['nis'=>'OLD001','name'=>'Arsip Siswa Lama','academic_year'=>'2025/2026']));
        $this->actingAs($this->actor())->get(route('internships.index',['q'=>'TKJ','academic_year'=>'2026/2027']))
            ->assertOk()->assertSee('Nama Unik Siswa')->assertDontSee('Arsip Siswa Lama')->assertSee('Buka daftar siswa');
        $this->get(route('internships.index',['academic_year'=>'2025/2026','major'=>'Teknik Komputer dan Jaringan']))
            ->assertOk()->assertSee('Arsip Siswa Lama')->assertDontSee('Nama Unik Siswa');
        $this->get(route('internships.index',['batch'=>1,'major'=>'Teknik Komputer dan Jaringan']))
            ->assertOk()->assertSee('name="roster_ids[]"',false)->assertSee('Nama Unik Siswa')->assertDontSee('Arsip Siswa Lama');
    }

    public function test_master_student_registration_creates_roster_and_rejects_unknown_major(): void
    {
        $this->actingAs($this->actor())->post(route('students.store'),$this->data())->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $this->assertDatabaseHas('internship_rosters',['student_id'=>$student->id,'nis'=>'000001','academic_year'=>'2026/2027','major'=>$student->major]);
        $this->post(route('students.store'),$this->data(['nis'=>'000002','major'=>'Salah Ejaan']))->assertSessionHasErrors('major');
        $this->assertDatabaseCount('students',1);
    }

    public function test_edit_synchronizes_latest_roster_but_preserves_historical_class_and_schedule(): void
    {
        $service = app(StudentEnrollment::class);
        $student = $service->register($this->data(['academic_year'=>'2025/2026','class_name'=>'XI TKJ 1']));
        $service->register($this->data());
        $latest = $student->rosters()->where('academic_year','2026/2027')->firstOrFail();
        $latest->update(['internship_status'=>'internship','internship_starts_at'=>'2026-08-01','internship_ends_at'=>'2026-12-31']);
        $password = $student->user->password;
        $this->actingAs($this->actor())->put(route('students.update',$student),$this->data([
            'nis'=>'NEW001','nisn'=>'009988','name'=>'Nama Dikoreksi','class_name'=>'XII DKV 2','major'=>'Desain Komunikasi Visual',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students',['id'=>$student->id,'nis'=>'NEW001','class_name'=>'XII DKV 2','major'=>'Desain Komunikasi Visual']);
        $this->assertDatabaseHas('internship_rosters',['student_id'=>$student->id,'academic_year'=>'2025/2026','class_name'=>'XI TKJ 1','major'=>'Teknik Komputer dan Jaringan','nis'=>'NEW001','nisn'=>'009988']);
        $this->assertSame('XII DKV 2',$latest->fresh()->class_name);
        $this->assertSame('2026-12-31',$latest->fresh()->internship_ends_at->toDateString());
        $this->assertSame($password,$student->user->fresh()->password);
        $this->get(route('internships.index',['q'=>'NEW001']))->assertSee('Nama Dikoreksi');
    }

    public function test_out_of_order_import_uses_latest_enrollment_without_rewriting_history(): void
    {
        $this->actingAs($this->actor());
        $csv="tahun_ajaran,nis,nama,tanggal_lahir,kelas,jurusan\n2027/2028,000001,Nama Unik,2008-11-07,XII DKV 2,DKV\n2026/2027,000001,Nama Unik,2008-11-07,XI TKJ 1,TKJ\n";
        $this->post(route('students.import'),['academic_year'=>'2026/2027','roster_file'=>UploadedFile::fake()->createWithContent('siswa.csv',$csv)])
            ->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $this->assertSame('Desain Komunikasi Visual',$student->major);
        $this->assertSame('XII DKV 2',$student->class_name);
        $this->assertDatabaseHas('internship_rosters',['academic_year'=>'2026/2027','class_name'=>'XI TKJ 1','major'=>'Teknik Komputer dan Jaringan']);
        $this->assertDatabaseCount('students',1);
    }

    public function test_demo_seeder_creates_32_linked_students_and_never_resets_existing_data(): void
    {
        $this->seed(StudentPklDemoSeeder::class);
        $this->assertDatabaseCount('students',32);
        $this->assertSame(32,InternshipRoster::whereNotNull('student_id')->count());
        $this->assertSame(8,InternshipRoster::distinct()->count('major'));
        $states = InternshipRoster::all()->countBy(fn ($row) => $row->lifecycleState());
        foreach (['pkl','scheduled','school','unscheduled'] as $state) $this->assertSame(8,$states[$state]);
        $student = Student::where('nis','DEMO-PKL-001')->firstOrFail();
        $this->assertTrue(Hash::check('07112008',$student->user->password));
        $student->user->update(['password'=>'AlreadyChanged123']);
        $student->rosters()->first()->update(['notes'=>'Catatan pengguna']);
        $this->seed(StudentPklDemoSeeder::class);
        $this->assertDatabaseCount('students',32);
        $this->assertTrue(Hash::check('AlreadyChanged123',$student->user->fresh()->password));
        $this->assertSame('Catatan pengguna',$student->rosters()->first()->notes);
    }

    public function test_demo_seeder_refuses_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(\RuntimeException::class);
        (new StudentPklDemoSeeder())->run();
    }
}
