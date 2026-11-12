<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Documents\Services\ReportSignatureService;
use App\Domain\Documents\Services\SignatureImageService;
use App\Domain\Documents\Services\SignatureTemplateService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\User;
use App\Support\ReportPdf;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Smalot\PdfParser\Parser;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class SignatureDigitalTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private string $samplePngBase64;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        Storage::fake('public');

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Admin Settings',
            'email' => 'admin_settings@example.test',
            'username' => 'admin_settings',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        // 1x1 transparent PNG base64
        $this->samplePngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }

    public function test_settings_page_renders_signature_images_prop(): void
    {
        $response = $this->actingAs($this->user)->get('/settings?tab=signatures');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Index')
            ->has('signatures')
            ->has('signatureImages')
        );
    }

    public function test_can_upload_and_delete_signature_image(): void
    {
        $postResponse = $this->actingAs($this->user)->post('/settings/signatures/image', [
            'report_key' => 'default',
            'image' => $this->samplePngBase64,
        ]);

        $postResponse->assertRedirect('/settings?tab=signatures');

        $service = app(SignatureImageService::class);
        $this->assertNotNull($service->path('default'));
        $this->assertNotNull($service->dataUri('default'));

        $deleteResponse = $this->actingAs($this->user)->delete('/settings/signatures/image', [
            'report_key' => 'default',
        ]);

        $deleteResponse->assertRedirect('/settings?tab=signatures');

        $service = app(SignatureImageService::class);
        $this->assertNull($service->path('default'));
    }

    public function test_report_signature_renders_officer_tokens_and_default_image(): void
    {
        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Koperasi Maju Bersama',
            'short_name' => 'KMB',
            'address' => 'Jl. Raya No. 1, Kota Test',
            'timezone' => 'Asia/Jakarta',
            'manager_name' => 'Drs. H. Sudirman',
            'manager_title' => 'Direktur Utama',
            'secretary_name' => 'Siti Aminah',
            'secretary_title' => 'Sekretaris',
        ]);

        app(SignatureTemplateService::class)->save([
            'laporan_keuangan' => '<p>{jabatan_kepala}: {kepala_lembaga}</p><p>{jabatan_sekretaris}: {sekretaris_lembaga}</p><p>{ttd_image}</p>',
        ]);
        app(SignatureImageService::class)->store('default', $this->samplePngBase64);

        $html = app(ReportSignatureService::class)->render('laporan_keuangan');

        self::assertStringContainsString('Direktur Utama: Drs. H. Sudirman', $html);
        self::assertStringContainsString('Sekretaris: Siti Aminah', $html);
        self::assertStringContainsString('data:image/png;base64,', $html);
        self::assertStringNotContainsString('{kepala_lembaga}', $html);
        self::assertStringNotContainsString('{ttd_image}', $html);
    }

    public function test_report_signature_table_uses_full_report_width(): void
    {
        app(SignatureTemplateService::class)->save([
            'laporan_keuangan' => '<table style="min-width: 75px;"><tbody><tr>'
                .'<td style="text-align:center;">Kepala</td>'
                .'<td style="text-align:center;">Sekretaris</td>'
                .'<td style="text-align:center;"><table style="min-width: 75px;"><tbody><tr>'
                .'<td style="text-align:center;">Bendahara</td>'
                .'</tr></tbody></table></td>'
                .'</tr></tbody></table>',
        ]);

        $html = app(ReportSignatureService::class)->render('laporan_keuangan');

        self::assertStringContainsString(
            'style="min-width: 75px;width:100%;table-layout:fixed;"',
            $html
        );
        self::assertSame(2, substr_count($html, 'width:100%;table-layout:fixed;'));
    }

    public function test_report_pdf_uses_signature_for_reports_but_not_loan_documents(): void
    {
        $pdf = app(ReportPdf::class);
        $keyMethod = new \ReflectionMethod($pdf, 'signatureKeyForView');
        $keyMethod->setAccessible(true);

        self::assertSame('laporan_keuangan', $keyMethod->invoke($pdf, 'reports.pdf.balance_sheet'));
        self::assertSame('laporan_keuangan', $keyMethod->invoke($pdf, 'reports.pdf.ojk.collectibility'));
        self::assertSame('rekap_pinjaman', $keyMethod->invoke($pdf, 'reports.pdf.lending.kolek_desa'));
        self::assertSame('rekap_pinjaman', $keyMethod->invoke($pdf, 'reports.pdf.loan_list'));
        self::assertNull($keyMethod->invoke($pdf, 'reports.pdf.loan_documents.spk'));
        self::assertNull($keyMethod->invoke($pdf, 'reports.pdf.cash_evidence.bkk'));
        self::assertNull($keyMethod->invoke($pdf, 'reports.pdf.annual.cover'));

        $renderMethod = new \ReflectionMethod($pdf, 'renderHtml');
        $renderMethod->setAccessible(true);

        $html = $renderMethod->invoke(
            $pdf,
            'reports.pdf.layout',
            ['identity' => [], 'title' => 'Uji'],
            '<table><tr><td>Mengetahui</td></tr></table>'
        );

        self::assertStringContainsString('data-report-signature', $html);
        self::assertStringContainsString('Mengetahui', $html);
        self::assertLessThan(strpos($html, '</body>'), strpos($html, 'data-report-signature'));
    }

    public function test_report_pdf_output_contains_configured_signature(): void
    {
        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Koperasi Maju Bersama',
            'timezone' => 'Asia/Jakarta',
            'manager_name' => 'Nama Kepala Uji',
        ]);

        app(SignatureTemplateService::class)->save([
            'laporan_keuangan' => '<p>Ditandatangani oleh {kepala_lembaga}</p>',
        ]);

        $response = app(ReportPdf::class)->download(
            'reports.pdf.layout',
            ['identity' => ['legal_name' => 'Koperasi Maju Bersama'], 'title' => 'Uji Tanda Tangan'],
            'uji-tanda-tangan.pdf'
        );

        $text = (new Parser)->parseContent((string) $response->getContent())->getText();

        self::assertStringContainsString('Ditandatangani oleh Nama Kepala Uji', $text);
    }
}
