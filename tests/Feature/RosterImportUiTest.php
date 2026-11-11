<?php

namespace Tests\Feature;

use App\Models\{InternshipRoster, Student, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RosterImportUiTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::create(['name'=>'Koordinator Impor', 'email'=>'import@test.local', 'role'=>'koordinator', 'is_active'=>true, 'password'=>'password123']);
    }

    private function upload(string $contents,string $name='siswa.csv')
    {
        $path=tempnam(sys_get_temp_dir(),'simmag-import-');
        file_put_contents($path,$contents);
        $mime=str_ends_with($name,'.xlsx')?'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':'text/csv';
        try {
            return $this->post(route('students.import'), [
                'academic_year'=>'2027/2028', 'roster_file'=>new UploadedFile($path,$name,$mime,null,true),
            ]);
        } finally {
            @unlink($path);
        }
    }

    private function workbook(string $contents)
    {
        $path=tempnam(sys_get_temp_dir(),'simmag-template-');
        file_put_contents($path,$contents);
        try {
            return IOFactory::load($path);
        } finally {
            @unlink($path);
        }
    }

    private function xlsx(Spreadsheet $book): string
    {
        $path=tempnam(sys_get_temp_dir(),'simmag-xlsx-');
        try {
            (new Xlsx($book))->save($path);
            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_import_form_and_examples_are_in_students_not_pkl_control(): void
    {
        $response = $this->actingAs($this->coordinator())->get(route('students.index'));
        $response->assertOk()->assertSee('Impor Data Siswa Massal')->assertSee('Template XLSX Kosong (opsional)')
            ->assertSee('Template XLSX + Contoh 8 Siswa')->assertSee('Template XLSX Jadwal + Contoh')->assertSee('CONTOH001')->assertSee('CONTOH002')
            ->assertSee('Excel tidak membalik format')
            ->assertSee('name="roster_file"', false)->assertSee('tanggal_lahir')->assertSee('5.000');
        $this->get(route('internships.index'))->assertOk()->assertDontSee('name="roster_file"', false)->assertDontSee('Impor Data Siswa Massal');
    }

    public function test_templates_offer_blank_headers_or_importable_examples_for_all_majors(): void
    {
        $this->actingAs($this->coordinator());
        $blank = $this->get(route('students.template',['example'=>0]))->assertOk()->streamedContent();
        $blankBook=$this->workbook($blank); $blankSheet=$blankBook->getSheet(0);
        $this->assertSame('tanggal_lahir',$blankSheet->getCell('E1')->getValue());
        $this->assertNotContains('mulai_pkl',$blankSheet->rangeToArray('A1:I1')[0]);
        $xlsxResponse=$this->get(route('students.template', ['academic_year'=>'2027/2028']))->assertOk()
            ->assertHeader('content-type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $xlsx=$xlsxResponse->streamedContent();
        $book=$this->workbook($xlsx); $sheet=$book->getSheet(0);
        $this->assertSame('2027/2028',$sheet->getCell('A2')->getValue());
        $this->assertSame('2008-11-07',$sheet->getCell('E2')->getValue());
        $this->assertSame(DataType::TYPE_STRING,$sheet->getCell('E2')->getDataType());
        $this->assertSame('@',$sheet->getStyle('E2')->getNumberFormat()->getFormatCode());
        foreach (InternshipRoster::officialMajors() as $index=>$major) $this->assertSame($major,$sheet->getCell('G'.($index+2))->getValue());
        $this->upload($xlsx,'siswa.xlsx')->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount('students', 8);
        $this->assertDatabaseCount('internship_rosters', 8);
        $this->assertSame(8, InternshipRoster::whereNull('internship_starts_at')->whereNotNull('student_id')->count());
        $this->assertSame(8, InternshipRoster::where('academic_year','2027/2028')->count());
    }

    public function test_schedule_example_roundtrips_and_student_only_import_preserves_schedule(): void
    {
        $this->actingAs($this->coordinator());
        $xlsx = $this->get(route('internships.template', ['type'=>'schedule', 'example'=>1, 'academic_year'=>'2027/2028']))->assertOk()->streamedContent();
        $book=$this->workbook($xlsx); $sheet=$book->getSheet(0);
        $this->assertSame('2027-07-01',$sheet->getCell('K2')->getValue());
        $this->assertSame('2027-12-31',$sheet->getCell('L2')->getValue());
        $this->assertSame(DataType::TYPE_STRING,$sheet->getCell('K2')->getDataType());
        $this->assertSame('@',$sheet->getStyle('K2')->getNumberFormat()->getFormatCode());
        $this->upload($xlsx,'siswa-jadwal.xlsx')->assertSessionHasNoErrors();
        $this->assertSame(8, InternshipRoster::whereDate('internship_starts_at','2027-07-01')->where('schedule_source','individual_override')->count());
        $studentsOnly = $this->get(route('internships.template', ['example'=>1, 'academic_year'=>'2027/2028']))->streamedContent();
        $this->upload($studentsOnly,'siswa.xlsx')->assertSessionHasNoErrors();
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

    public function test_real_excel_date_cells_are_normalized_to_iso_dates(): void
    {
        $this->actingAs($this->coordinator());
        $book=new Spreadsheet(); $sheet=$book->getActiveSheet();
        $sheet->fromArray([
            ['nis','nama','kelas','jurusan','tanggal_lahir','status_magang','mulai_pkl','selesai_pkl'],
            ['000124','Tanggal Excel','XII 1','TKJ',ExcelDate::PHPToExcel(new \DateTimeImmutable('2008-11-07')),
                'magang',ExcelDate::PHPToExcel(new \DateTimeImmutable('2027-07-01')),ExcelDate::PHPToExcel(new \DateTimeImmutable('2027-12-31'))],
        ]);
        $sheet->getStyle('E2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('G2:H2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');

        $this->upload($this->xlsx($book),'tanggal-excel.xlsx')->assertSessionHasNoErrors();
        $roster=InternshipRoster::where('nis','000124')->firstOrFail();
        $this->assertSame('2008-11-07',$roster->student->birth_date->toDateString());
        $this->assertSame('2027-07-01',$roster->internship_starts_at->toDateString());
        $this->assertSame('2027-12-31',$roster->internship_ends_at->toDateString());
    }

    public function test_resolved_management_flags_are_removed_without_granting_student_access(): void
    {
        $this->actingAs($this->coordinator());
        foreach (['reports.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('RED FLAG — modul belum sepenuhnya sinkron')->assertSee('Seluruh Siswa / Roster');
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
