<?php

namespace Tests\Feature;

use App\Models\User;
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
            ->assertSee('Pengguna &amp; role', false)->assertSee('prinsip akses minimum');
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

    public function test_context_help_script_supports_delayed_hover_click_escape_and_outside_close(): void
    {
        $script = file_get_contents(public_path('js/context-help.js'));

        $this->assertStringContainsString("window.setTimeout(() => open(false), delay)", $script);
        $this->assertStringContainsString("button.addEventListener('click'", $script);
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString('!widget.contains(event.target)', $script);
        $this->assertStringContainsString("matchMedia('(hover: hover) and (pointer: fine)')", $script);
    }
}
