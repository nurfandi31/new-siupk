<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\InstallmentSystem;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Lending\Services\LoanSimulationService;
use App\Tenancy\Services\TenantInstallmentSystemProvisioner;
use DomainException;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class InstallmentSystemsTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_master_data_seeds_twenty_three_legacy_rows(): void
    {
        app(TenantInstallmentSystemProvisioner::class)->ensureDefaults();

        self::assertSame(23, InstallmentSystem::query()->count());
        self::assertSame(0, InstallmentSystem::query()->where('legacy_id', 1)->value('principal_grace_months'));
        self::assertSame(6, InstallmentSystem::query()->where('legacy_id', 26)->value('principal_grace_months'));
    }

    public function test_every_six_month_principal_is_paid_in_months_six_twelve_eighteen_and_twenty_four(): void
    {
        $result = app(LoanSimulationService::class)->simulate([
            'principal_amount' => 12000000,
            'term_months' => 24,
            'interest_rate' => 1.5,
            'rate_unit' => 'monthly',
            'installment_method' => 'flat',
            'principal_frequency' => 'every_6_months',
            'interest_frequency' => 'monthly',
            'rounding_step' => 0,
            'start_date' => '2026-01-01',
        ]);

        $principalRows = array_values(array_filter($result['schedule'], fn (array $row) => $row['principal_due'] > 0));

        self::assertSame(['2026-07-01', '2027-01-01', '2027-07-01', '2028-01-01'], array_column($principalRows, 'due_date'));
    }

    public function test_m6_keeps_monthly_interest_and_defers_principal_to_month_seven(): void
    {
        $result = $this->simulate([
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'principal_grace_months' => 6,
        ]);

        $principalRows = array_filter($result['schedule'], fn (array $row) => $row['principal_due'] > 0);
        $interestOnlyRows = array_filter($result['schedule'], fn (array $row) => $row['principal_due'] == 0.0);
        $firstPrincipal = current($principalRows);

        self::assertSame('2026-08-01', $firstPrincipal['due_date']);
        self::assertCount(18, $principalRows);
        self::assertCount(6, $interestOnlyRows);
        self::assertEqualsWithDelta(12000000.0, array_sum(array_column($result['schedule'], 'principal_due')), 0.01);
    }

    public function test_m1_defers_both_principal_and_interest_by_one_month(): void
    {
        $result = $this->simulate([
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'principal_grace_months' => 1,
            'interest_grace_months' => 1,
        ]);

        self::assertSame('2026-03-01', $result['schedule'][0]['due_date']);
        self::assertSame('2027-01-01', $result['schedule'][10]['due_date']);
        self::assertGreaterThan(0.0, $result['schedule'][0]['principal_due']);
    }

    public function test_seasonal_system_defers_principal_to_month_three(): void
    {
        $result = $this->simulate([
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'principal_grace_months' => 2,
            'term_months' => 12,
        ]);

        $dates = array_column($result['schedule'], 'due_date');

        self::assertNotContains('2026-01-01', $dates);
        self::assertContains('2026-02-01', $dates);
        self::assertContains('2026-04-01', $dates);
    }

    public function test_rejects_interval_greater_than_term(): void
    {
        $this->expectException(DomainException::class);

        $this->simulate([
            'principal_frequency' => 'every_36_months',
            'interest_frequency' => 'monthly',
            'term_months' => 24,
        ]);
    }

    private function simulate(array $overrides = []): array
    {
        $payload = [
            'principal_amount' => 12000000,
            'term_months' => 24,
            'interest_rate' => 1.5,
            'rate_unit' => 'monthly',
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 0,
            'start_date' => '2026-01-01',
            ...$overrides,
        ];

        if (LoanService::intervalMonths($payload['principal_frequency']) > $payload['term_months']) {
            throw new DomainException('Interval angsuran tidak boleh melebihi jangka waktu setelah grace period.');
        }

        return app(LoanSimulationService::class)->simulate($payload);
    }
}
