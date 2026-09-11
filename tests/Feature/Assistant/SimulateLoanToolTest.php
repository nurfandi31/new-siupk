<?php

declare(strict_types=1);

namespace Tests\Feature\Assistant;

use App\Assistant\Handlers\SimulateLoanHandler;
use App\Domain\Assistant\Services\AssistantToolService;
use App\Domain\Lending\Models\LoanProduct;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Enpii\Assistant\Services\Tools\ToolRegistry;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class SimulateLoanToolTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas AI',
            'email' => 'ai_user@example.test',
            'username' => 'ai_officer',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_simulate_loan_handler_metadata(): void
    {
        /** @var SimulateLoanHandler $handler */
        $handler = app(SimulateLoanHandler::class);

        self::assertSame('simulate_loan', $handler->name());
        self::assertFalse($handler->requiresConfirmation());
        self::assertStringContainsString('simulasi', $handler->description());

        $schema = $handler->jsonSchema();
        self::assertSame('object', $schema['type']);
        self::assertArrayHasKey('principal_amount', $schema['properties']);
        self::assertArrayHasKey('installment_method', $schema['properties']);
        self::assertArrayHasKey('rounding_step', $schema['properties']);
    }

    public function test_simulate_loan_registered_in_tool_registry(): void
    {
        /** @var ToolRegistry $registry */
        $registry = app(ToolRegistry::class);

        $tool = $registry->resolve('simulate_loan');
        self::assertNotNull($tool);
        self::assertSame('simulate_loan', $tool->name());
    }

    public function test_simulate_loan_executes_and_returns_summary_and_download_button(): void
    {
        /** @var AssistantToolService $tools */
        $tools = app(AssistantToolService::class);

        $res = $tools->simulateLoan([
            'principal_amount' => 12000000,
            'term_months' => 12,
            'interest_rate' => 12.0,
            'installment_method' => 'flat',
            'borrower_name' => 'Kelompok Dahlia',
            'rounding_step' => 500,
        ]);

        self::assertTrue($res['success']);
        self::assertSame(12000000.0, (float) $res['summary']['principal_amount']);
        self::assertSame(1440000.0, (float) $res['summary']['total_interest']);
        self::assertSame(13440000.0, (float) $res['summary']['total_payment']);
        self::assertSame(12, (int) $res['summary']['term_months']);
        self::assertSame('flat', $res['summary']['installment_method']);
        self::assertSame(500, (int) $res['summary']['rounding_step']);
        self::assertStringContainsString('/lending/simulation/pdf?', $res['pdf_url']);
        self::assertStringContainsString('::button{', $res['download_button']);
        self::assertStringContainsString('download=1', $res['pdf_url']);
        self::assertCount(3, $res['schedule_preview']);
        self::assertSame(12, $res['total_installments']);
    }

    public function test_simulate_loan_uses_preset_product_defaults(): void
    {
        LoanProduct::query()->create([
            'code' => 'uep',
            'name' => 'Usaha Ekonomi Produktif',
            'interest_method' => 'effective',
            'default_interest_rate' => 14.4,
            'default_term_months' => 6,
            'minimum_amount' => 1000000,
            'maximum_amount' => 20000000,
            'borrower_scope' => 'group_only',
            'is_active' => true,
            'rounding_method' => '1000',
        ]);

        /** @var AssistantToolService $tools */
        $tools = app(AssistantToolService::class);

        $res = $tools->simulateLoan([
            'product_code' => 'uep',
            'principal_amount' => 5000000,
        ]);

        self::assertTrue($res['success']);
        self::assertSame(6, (int) $res['summary']['term_months']);
        self::assertSame(14.4, (float) $res['summary']['interest_rate_annual']);
        self::assertSame(1000, (int) $res['summary']['rounding_step']);
    }
}
