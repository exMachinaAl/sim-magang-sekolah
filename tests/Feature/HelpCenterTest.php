<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AssessmentTemplate;
use Database\Seeders\CertificatePreviewDemoSeeder;
use App\Support\HelpCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_faq_is_authenticated_and_role_aware(): void
    {
        $this->get(route('guide'))->assertRedirect(route('login'));

        $expectations = [
            'siswa' => ['Panduan siswa', 'Bagaimana mengisi jurnal?'],
            'guru' => ['Panduan guru pembimbing', 'Apakah guru wajib memeriksa semua jurnal secara rutin?'],
            'koordinator' => ['Panduan koordinator PKL', 'Apa urutan menyiapkan siswa PKL?'],
            'admin' => ['Panduan admin sistem', 'Apa batas admin dibanding koordinator?'],
        ];

        foreach ($expectations as $role => [$section, $question]) {
            $response = $this->actingAs(User::where('role', $role)->firstOrFail())->get(route('guide'));
            $response->assertOk()->assertSee('FAQ &amp; Panduan Penggunaan', false)
                ->assertSee($section)->assertSee($question)
                ->assertSee('data-faq-entry', false)->assertSee('id="faq-query"', false)
                ->assertSee('data-context-help', false);
        }
    }

    public function test_context_button_is_accessible_and_changes_with_page_and_role(): void
    {
        $student = User::where('role', 'siswa')->firstOrFail();
        $this->actingAs($student)->get(route('journals.index'))->assertOk()
            ->assertSee('Petunjuk halaman ini')->assertSee('Jurnal &amp; foto', false)
            ->assertSee('Simpan kegiatan sendiri, tunjukkan ringkasan kepada pembimbing industri')
            ->assertSee('aria-expanded="false"', false)->assertSee('data-hover-delay="1000"', false)
            ->assertSee('js/context-help.js', false);

        $coordinator = User::where('role', 'koordinator')->firstOrFail();
        $this->actingAs($coordinator)->get(route('internships.index'))->assertOk()
            ->assertSee('Kontrol PKL')->assertSee('tanggal mulai dan selesai pada roster')
            ->assertSee('ID akun koordinator yang aktif');

        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertSee('Akun staf &amp; guru pendamping', false)->assertSee('Arsipkan/Sembunyikan');
    }

    public function test_help_center_resolves_specific_routes_before_general_routes_and_has_fallback(): void
    {
        $coordinator = User::where('role', 'koordinator')->firstOrFail();
        $service = app(HelpCenter::class);

        $this->assertSame('kiosk-industri', $service->page('journals.mentors', $coordinator)['key']);
        $this->assertSame('template-sertifikat', $service->page('certificate-print-templates.index', $coordinator)['key']);
        $this->assertSame('sertifikat-siapkan', $service->page('certificates.prepare', $coordinator)['key']);
        $this->assertSame('jurnal', $service->page('journals.index', $coordinator)['key']);
        $this->assertSame('umum', $service->page('route.baru.yang.belum.dipetakan', $coordinator)['key']);
    }

    public function test_kiosk_help_explains_portal_confirmation_and_batch_flow(): void
    {
        $coordinator = User::where('role', 'koordinator')->firstOrFail();
        $service = app(HelpCenter::class);
        $help = $service->page('journals.mentors', $coordinator);

        $this->assertStringContainsString('Portal Kiosk', $help['summary']);
        $this->assertStringContainsString('OK untuk mengesahkan atau Batal', implode(' ', $help['steps']));

        $this->actingAs($coordinator)->get(route('guide', ['q' => 'perangkat pembimbing industri']))
            ->assertOk()
            ->assertSee('Portal Kiosk')
            ->assertSee('memindai banyak QR siswa secara berurutan');
    }

    public function test_context_help_script_supports_delayed_hover_click_escape_and_outside_close(): void
    {
        $script = file_get_contents(public_path('js/context-help.js'));

        $this->assertStringContainsString("window.setTimeout(() => open(false), delay)", $script);
        $this->assertStringContainsString("button.addEventListener('click'", $script);
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString('!widget.contains(event.target)', $script);
        $this->assertStringContainsString("matchMedia('(hover: hover) and (pointer: fine)')", $script);
    }

    public function test_certificate_faq_and_inline_help_explain_the_full_workflow(): void
    {
        $coordinator = User::where('role', 'koordinator')->firstOrFail();

        $this->actingAs($coordinator)->get(route('guide', ['q' => 'template sertifikat']))
            ->assertOk()
            ->assertSee('Panduan sertifikat')
            ->assertSee('Bagaimana urutan pengujian template sertifikat yang aman?')
            ->assertSee('Buka contoh DEMO-SERT-001 dan unduh PDF.')
            ->assertSee('Bagaimana memakai template DOCX asli sekolah?')
            ->assertSee('UJI 03 - Template Asli Sekolah')
            ->assertSee('Actual size / 100%')
            ->assertSee("const initial=new URLSearchParams(window.location.search).get('q')", false);

        $assessment = AssessmentTemplate::where('major', 'Kuliner')->firstOrFail();
        $this->get(route('certificate-print-templates.index', ['assessment_template_id' => $assessment->id]))
            ->assertOk()
            ->assertSee('data-inline-help', false)
            ->assertSee('Petunjuk format cetak')
            ->assertSee('js/inline-help.js', false)
            ->assertSee('LibreOffice server aktif')
            ->assertDontSee('LibreOffice server belum dikonfigurasi');

        $this->seed(CertificatePreviewDemoSeeder::class);
        $placement = \App\Models\InternshipRoster::where('nis', CertificatePreviewDemoSeeder::NIS)->firstOrFail()->placement;
        $this->get(route('certificates.prepare', $placement))->assertOk()
            ->assertSee('Petunjuk desain cetak')
            ->assertSee('Petunjuk tanggal pengesahan');
    }

    public function test_inline_help_supports_one_second_hover_click_keyboard_and_escape(): void
    {
        $script = file_get_contents(public_path('js/inline-help.js'));

        $this->assertStringContainsString('const delay = 1000', $script);
        $this->assertStringContainsString("button.addEventListener('pointerenter'", $script);
        $this->assertStringContainsString("button.addEventListener('click'", $script);
        $this->assertStringContainsString("button.addEventListener('focus'", $script);
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString("matchMedia('(hover: hover) and (pointer: fine)')", $script);
    }
}
