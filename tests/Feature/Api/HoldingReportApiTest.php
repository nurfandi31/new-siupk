<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class HoldingReportApiTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_can_list_subsidiary_tenants(): void
    {
        $response = $this->getJson('/api/v1/holding/tenants');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'tenant-a')
            ->assertJsonPath('data.0.name', 'Tenant A');
    }

    public function test_can_get_single_tenant_detail(): void
    {
        $response = $this->getJson('/api/v1/holding/tenants/tenant-a');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'tenant-a');
    }

    public function test_can_fetch_balance_sheet(): void
    {
        $response = $this->getJson('/api/v1/holding/reports/balance-sheet?tenant=tenant-a&year=2026&month=8');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.report', 'balance_sheet')
            ->assertJsonPath('meta.scope', 'single_tenant')
            ->assertJsonPath('meta.tenant.code', 'tenant-a')
            ->assertJsonStructure([
                'status',
                'meta' => ['report', 'report_title', 'scope', 'tenant', 'period', 'generated_at'],
                'data' => ['period', 'identity', 'sections', 'totals', 'balanced'],
            ]);
    }

    public function test_can_fetch_income_statement(): void
    {
        $response = $this->getJson('/api/v1/holding/reports/income-statement?tenant=tenant-a&year=2026&month=8');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.report', 'income_statement')
            ->assertJsonPath('meta.scope', 'single_tenant')
            ->assertJsonStructure([
                'status',
                'meta' => ['report', 'report_title', 'scope', 'tenant', 'period', 'generated_at'],
                'data' => ['period', 'identity', 'groups', 'summary'],
            ]);
    }

    public function test_can_fetch_cash_flow(): void
    {
        $response = $this->getJson('/api/v1/holding/reports/cash-flow?tenant=tenant-a&year=2026&month=8');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.report', 'cash_flow')
            ->assertJsonPath('meta.scope', 'single_tenant')
            ->assertJsonStructure([
                'status',
                'meta' => ['report', 'report_title', 'scope', 'tenant', 'period', 'generated_at'],
                'data' => ['period', 'identity', 'cash_accounts', 'opening_cash', 'closing_cash', 'sections'],
            ]);
    }

    public function test_can_fetch_calk(): void
    {
        $response = $this->getJson('/api/v1/holding/reports/calk?tenant=tenant-a&year=2026&month=8');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.report', 'calk')
            ->assertJsonPath('meta.scope', 'single_tenant')
            ->assertJsonStructure([
                'status',
                'meta' => ['report', 'report_title', 'scope', 'tenant', 'period', 'generated_at'],
                'data' => ['period', 'identity', 'highlights', 'policies'],
            ]);
    }

    public function test_can_fetch_equity_changes(): void
    {
        $response = $this->getJson('/api/v1/holding/reports/equity-changes?tenant=tenant-a&year=2026&month=8');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.report', 'equity_changes')
            ->assertJsonPath('meta.scope', 'single_tenant')
            ->assertJsonStructure([
                'status',
                'meta' => ['report', 'report_title', 'scope', 'tenant', 'period', 'generated_at'],
                'data' => ['period', 'identity', 'rows', 'summary', 'bridge'],
            ]);
    }

    public function test_can_fetch_full_financial_pack(): void
    {
        $response = $this->getJson('/api/v1/holding/reports/pack?tenant=tenant-a&year=2026&month=8');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.report', 'financial_report_pack')
            ->assertJsonStructure([
                'status',
                'meta' => ['report', 'report_title', 'scope', 'tenant', 'period', 'generated_at'],
                'data' => ['balance_sheet', 'income_statement', 'cash_flow', 'equity_changes', 'calk'],
            ]);
    }

    public function test_can_fetch_tenant_scoped_reports(): void
    {
        $response = $this->getJson('/api/v1/holding/tenants/tenant-a/reports/balance-sheet?year=2026&month=8');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.tenant.code', 'tenant-a');
    }

    public function test_can_fetch_consolidated_reports(): void
    {
        $response = $this->getJson('/api/v1/holding/reports/consolidated/balance-sheet?year=2026&month=8');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.scope', 'consolidated');
    }

    public function test_returns_404_for_unknown_tenant(): void
    {
        $response = $this->getJson('/api/v1/holding/reports/balance-sheet?tenant=unknown-tenant-x');

        $response->assertNotFound()
            ->assertJsonPath('status', 'error');
    }

    public function test_enforces_api_key_authentication_when_configured(): void
    {
        Config::set('services.holding.api_key', 'secret-holding-key-12345');

        // Request without key -> 401
        $this->getJson('/api/v1/holding/tenants')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');

        // Request with invalid key -> 401
        $this->withHeaders(['Authorization' => 'Bearer wrong-key'])
            ->getJson('/api/v1/holding/tenants')
            ->assertUnauthorized();

        // Request with valid Bearer token -> 200
        $this->withHeaders(['Authorization' => 'Bearer secret-holding-key-12345'])
            ->getJson('/api/v1/holding/tenants')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Request with X-Holding-Key header -> 200
        $this->withHeaders(['X-Holding-Key' => 'secret-holding-key-12345'])
            ->getJson('/api/v1/holding/tenants')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Request with X-API-Key header -> 200
        $this->withHeaders(['X-API-Key' => 'secret-holding-key-12345'])
            ->getJson('/api/v1/holding/tenants')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }
}
