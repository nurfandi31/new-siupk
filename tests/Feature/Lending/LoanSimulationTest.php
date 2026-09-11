<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\LoanProduct;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LoanSimulationTest extends TestCase
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
            'name' => 'Petugas Simulasi',
            'email' => 'simulasi@example.test',
            'username' => 'petugas_simulasi',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_simulation_index_page_renders_with_products_and_defaults(): void
    {
        LoanProduct::query()->create([
            'code' => 'SPP',
            'name' => 'Simpan Pinjam Perempuan',
            'interest_method' => 'flat',
            'default_interest_rate' => 1.5,
            'default_term_months' => 12,
            'minimum_amount' => 1000000,
            'maximum_amount' => 50000000,
            'borrower_scope' => 'group_only',
            'is_active' => true,
            'rounding_method' => '500',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/lending/simulation');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Lending/Simulation/Index')
                ->has('products', 1)
                ->has('defaultSimulation')
                ->has('frequencyOptions')
                ->has('methodOptions')
                ->has('roundingOptions')
                ->where('products.0.code', 'SPP')
                ->where('products.0.rounding_step', 500)
                ->where('defaultSimulation.summary.rounding_step', 500)
            );
    }

    public function test_calculate_flat_simulation_returns_accurate_schedule(): void
    {
        $payload = [
            'principal_amount' => 12000000,
            'term_months' => 12,
            'interest_rate' => 12.0,
            'rate_unit' => 'annual',
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 500,
            'start_date' => '2026-08-27',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/lending/simulation/calculate', $payload);

        $response->assertOk()
            ->assertJsonPath('summary.principal_amount', 12000000)
            ->assertJsonPath('summary.total_interest', 1440000)
            ->assertJsonPath('summary.total_payment', 13440000)
            ->assertJsonPath('summary.term_months', 12)
            ->assertJsonPath('summary.installment_method', 'flat')
            ->assertJsonPath('summary.rounding_step', 500);

        $data = $response->json();
        $this->assertCount(12, $data['schedule']);
        $this->assertEqualsWithDelta(1000000.0, $data['schedule'][0]['principal_due'], 0.01);
        $this->assertEqualsWithDelta(120000.0, $data['schedule'][0]['interest_due'], 0.01);
        $this->assertEqualsWithDelta(1120000.0, $data['schedule'][0]['total_due'], 0.01);
        $this->assertEqualsWithDelta(0.0, $data['schedule'][11]['remaining_principal'], 0.01);
    }

    public function test_calculate_annuity_simulation_returns_accurate_schedule(): void
    {
        $payload = [
            'principal_amount' => 10000000,
            'term_months' => 12,
            'interest_rate' => 12.0,
            'rate_unit' => 'annual',
            'installment_method' => 'annuity',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 500,
            'start_date' => '2026-08-27',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/lending/simulation/calculate', $payload);

        $response->assertOk()
            ->assertJsonPath('summary.principal_amount', 10000000)
            ->assertJsonPath('summary.installment_method', 'annuity')
            ->assertJsonPath('summary.rounding_step', 500);

        $data = $response->json();
        $this->assertCount(12, $data['schedule']);
        $this->assertGreaterThan(10000000, $data['summary']['total_payment']);
        $this->assertEqualsWithDelta(0.0, $data['schedule'][11]['remaining_principal'], 0.05);
    }

    public function test_calculate_declining_simulation_returns_accurate_schedule(): void
    {
        $payload = [
            'principal_amount' => 10000000,
            'term_months' => 10,
            'interest_rate' => 12.0,
            'rate_unit' => 'annual',
            'installment_method' => 'declining',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 500,
            'start_date' => '2026-08-27',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/lending/simulation/calculate', $payload);

        $response->assertOk()
            ->assertJsonPath('summary.principal_amount', 10000000)
            ->assertJsonPath('summary.installment_method', 'declining')
            ->assertJsonPath('summary.rounding_step', 500);

        $data = $response->json();
        $this->assertCount(10, $data['schedule']);
        $this->assertGreaterThan($data['schedule'][1]['interest_due'], $data['schedule'][0]['interest_due']);
        $this->assertEqualsWithDelta(0.0, $data['schedule'][9]['remaining_principal'], 0.01);
    }

    public function test_calculate_with_custom_rounding_step(): void
    {
        $payload = [
            'principal_amount' => 10000000,
            'term_months' => 12,
            'interest_rate' => 12.0,
            'rate_unit' => 'annual',
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 1000,
            'start_date' => '2026-08-27',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/lending/simulation/calculate', $payload);

        $response->assertOk()
            ->assertJsonPath('summary.rounding_step', 1000);

        $data = $response->json();
        foreach ($data['schedule'] as $row) {
            $this->assertEquals(0, fmod($row['principal_due'], 1000), 'Principal should be rounded to 1000');
            $this->assertEquals(0, fmod($row['interest_due'], 1000), 'Interest should be rounded to 1000');
        }
    }

    public function test_calculate_validation_fails_for_invalid_input_and_below_minimum_rounding(): void
    {
        $payload = [
            'principal_amount' => 5000, // min is 100000
            'term_months' => 0, // min is 1
            'interest_rate' => 150, // max is 100
            'installment_method' => 'invalid_method',
            'principal_frequency' => 'unknown',
            'interest_frequency' => 'unknown',
            'rounding_step' => -100, // min is 0
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/lending/simulation/calculate', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'principal_amount',
                'term_months',
                'interest_rate',
                'installment_method',
                'principal_frequency',
                'interest_frequency',
                'rounding_step',
            ]);
    }

    public function test_pdf_simulation_export_streams_pdf_with_minimum_rounding(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/lending/simulation/pdf?principal_amount=10000000&term_months=12&interest_rate=12&installment_method=flat&borrower_name=Kelompok%20Mawar');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_unauthenticated_user_cannot_access_simulation(): void
    {
        $this->get('/lending/simulation')
            ->assertRedirect('/login');

        $this->postJson('/lending/simulation/calculate', [
            'principal_amount' => 10000000,
            'term_months' => 12,
            'interest_rate' => 12.0,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 500,
        ])->assertStatus(401);
    }
}
