<?php
namespace Tests\Feature;

use App\Models\{Certificate, Student, User};
use Database\Seeders\CertificatePreviewDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class CertificatePreviewDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local'); $this->seed();
    }

    public function test_example_is_ready_to_download_without_modifying_existing_data_and_reseed_preserves_user_edits(): void
    {
        $before=[];
        foreach (['users','students','internship_rosters','placements','assessments','certificates','certificate_documents'] as $table) {
            $before[$table]=DB::table($table)->orderBy('id')->get();
        }
        $this->seed(CertificatePreviewDemoSeeder::class);
        foreach ($before as $table=>$rows) {
            $this->assertEquals($rows,DB::table($table)->whereIn('id',$rows->pluck('id'))->orderBy('id')->get(),$table.' changed');
        }
        $this->assertDatabaseCount('students',31);
        $student=Student::where('nis',CertificatePreviewDemoSeeder::NIS)->firstOrFail();
        $c=$student->placements()->firstOrFail()->certificate;
        $this->actingAs(User::where('role','koordinator')->firstOrFail());
        $this->get(route('certificates.index'))->assertOk()->assertSee('data-certificate-demo',false)
            ->assertSee('PDF bawaan sudah bisa digunakan.')->assertSee('belum siap, menunggu LibreOffice');
        $this->get(route('certificates.show',$c))->assertOk()->assertSee('Unduh Sertifikat + Nilai PDF');
        $pdf=$this->get(route('certificates.pdf',$c))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF',$pdf);
        $this->get(route('certificates.prepare',[$c->placement_id,'print_template_id'=>$c->print_template_id]))->assertOk()->assertSee('Gresik')->assertSee('Ajukan Versi Baru');
        $print=$c->documents()->firstOrFail()->snapshot['print']['values'];
        $this->assertSame('84,00',$print['nilai_rata_rata_aspek']);
        $this->assertSame('85,20',$print['nilai_hitung']); $this->assertSame('85,00',$print['nilai_akhir_pkl']);
        $student->user->update(['password'=>'ChangedByTester123!']);
        $hash=$student->user->fresh()->password;
        $this->seed(CertificatePreviewDemoSeeder::class);
        $this->assertDatabaseCount('students',31);
        $this->assertSame($hash,$student->user->fresh()->password);
        $this->assertSame($pdf,$this->get(route('certificates.pdf',$c))->assertOk()->getContent());
        $this->actingAs(User::where('role','guru')->firstOrFail())->get(route('certificates.index'))->assertOk()->assertDontSee('data-certificate-demo',false);
        $this->actingAs(User::where('role','siswa')->where('id','!=',$student->user_id)->firstOrFail())->get(route('certificates.pdf',$c))->assertNotFound();
    }

    public function test_demo_refuses_an_identifier_collision_instead_of_overwriting_a_student(): void
    {
        $student=Student::firstOrFail(); $student->update(['nis'=>CertificatePreviewDemoSeeder::NIS]);
        $before=$student->fresh()->toArray();
        try { $this->seed(CertificatePreviewDemoSeeder::class); $this->fail('Expected collision refusal'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('tidak ada data yang ditimpa',$e->getMessage()); }
        $this->assertSame($before,$student->fresh()->toArray());
        $this->assertDatabaseCount('students',30);
    }
}
