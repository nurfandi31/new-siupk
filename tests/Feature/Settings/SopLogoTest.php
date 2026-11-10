<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\User;
use App\Support\ReportPdf;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class SopLogoTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);
        Storage::fake('public');

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas SOP',
            'email' => 'sop-logo@example.test',
            'username' => 'sop_logo_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Koperasi Maju Bersama',
            'short_name' => 'KMB',
            'address' => 'Jl. Raya No. 1, Kota Test',
            'phone' => '08123456789',
            'email' => 'info@kmb.test',
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_sop_logo_upload_is_shared_with_document_and_report_identity(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/settings/sop/logo', [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ]);

        $response->assertRedirect(route('settings.sop.index', ['tab' => 'logo']));

        $profile = OrganizationProfile::query()->firstOrFail();
        self::assertNotNull($profile->logo_path);
        Storage::disk('public')->assertExists($profile->logo_path);

        $logoUrl = asset('storage/'.$profile->logo_path);

        $this->actingAs($this->user)
            ->get('/settings/sop')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Sop/Index')
                ->where('logoUrl', $logoUrl)
            );
    }

    public function test_sop_logo_can_be_removed(): void
    {
        $this->actingAs($this->user)
            ->post('/settings/sop/logo', [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ]);

        $profile = OrganizationProfile::query()->firstOrFail();
        $path = (string) $profile->logo_path;
        self::assertNotSame('', $path);

        $this->actingAs($this->user)
            ->delete('/settings/sop/logo')
            ->assertRedirect(route('settings.sop.index', ['tab' => 'logo']));

        self::assertNull(OrganizationProfile::query()->firstOrFail()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_report_pdf_uses_sop_logo_when_report_payload_omits_it(): void
    {
        $this->actingAs($this->user)
            ->post('/settings/sop/logo', [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ]);

        OrganizationProfile::query()->firstOrFail();

        $method = new \ReflectionMethod(ReportPdf::class, 'withLogo');
        $method->setAccessible(true);

        $data = $method->invoke(app(ReportPdf::class), [
            'identity' => ['legal_name' => 'Koperasi Maju Bersama'],
        ]);

        self::assertStringStartsWith('data:image/png;base64,', (string) $data['identity']['logo_url']);
        self::assertSame($data['identity']['logo_url'], $data['logo_url']);

        // Teks kop juga harus ikut identitas lembaga, bukan hanya logo.
        self::assertSame('Koperasi Maju Bersama', $data['identity']['legal_name']);
        self::assertSame('KMB', $data['identity']['short_name']);
        self::assertSame('Jl. Raya No. 1, Kota Test', $data['identity']['address']);
        self::assertSame('08123456789', $data['identity']['phone']);
        self::assertSame('info@kmb.test', $data['identity']['email']);
    }

    public function test_report_layout_shows_legacy_letterhead_identity(): void
    {
        $html = view('reports.pdf.layout', [
            'identity' => [
                'legal_name' => 'Koperasi Maju Bersama',
                'district_name' => 'Kecamatan Test',
                'regency_name' => 'Kabupaten Test',
                'registration_number' => 'AHU-123',
                'address' => 'Jl. Raya No. 1, Kota Test',
                'phone' => '08123456789',
            ],
        ])->render();

        self::assertStringContainsString('KOPERASI MAJU BERSAMA', $html);
        self::assertStringContainsString('KEC. KECAMATAN TEST KAB. KABUPATEN TEST', $html);
        self::assertStringContainsString('SK Kemenkumham RI No. AHU-123', $html);
        self::assertStringContainsString('Jl. Raya No. 1, Kota Test', $html);
        self::assertStringContainsString('Telp. 08123456789', $html);
    }

    public function test_ojk_report_pdfs_render_with_sop_logo(): void
    {
        $this->actingAs($this->user)
            ->post('/settings/sop/logo', [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ]);

        foreach (['profile', 'balance-sheet', 'income-statement', 'loans-received'] as $report) {
            $this->actingAs($this->user)
                ->get("/regulatory/ojk/{$report}/pdf?year=2026&month=7")
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }
}
