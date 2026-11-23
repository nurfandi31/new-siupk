<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Services\LoanService;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

/**
 * Akun denda legacy memakai pola `4.1.02.{kode produk}`, sedangkan chart
 * bawaan Next lama menaruh denda di `4.1.01.04-06`. Resolver harus memilih
 * akun yang benar pada kedua tata letak (dan tidak pernah memilih akun dividen).
 */
final class LoanPenaltyAccountResolutionTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_default_chart_resolves_legacy_penalty_codes(): void
    {
        self::assertSame('4.1.02.01', $this->resolve('spp'));
        self::assertSame('4.1.02.02', $this->resolve('uep'));
        self::assertSame('4.1.02.03', $this->resolve('pl'));
        self::assertSame('4.1.02.05', $this->resolve('pi'));
    }

    public function test_old_next_chart_still_resolves_denda_accounts(): void
    {
        // Tiru chart Next lama: denda ada di 4.1.01.04-06, 4.1.02.01-03 = dividen.
        $this->rename('4.1.02.01', 'Pendapatan Dividen Unit Usaha 1');
        $this->rename('4.1.01.04', 'Pendapatan Denda Piutang SPP');

        self::assertSame('4.1.01.04', $this->resolve('spp'));
    }

    public function test_old_chart_uep_penalty_falls_back_to_next_code(): void
    {
        $this->rename('4.1.02.02', 'Pendapatan Dividen Unit Usaha 2');
        $this->rename('4.1.01.05', 'Pendapatan Denda Piutang UEP');

        self::assertSame('4.1.01.05', $this->resolve('uep'));
    }

    private function resolve(string $productCode): string
    {
        $service = app(LoanService::class);
        $method = new ReflectionMethod($service, 'resolvePenaltyAccount');
        $method->setAccessible(true);

        /** @var Account $account */
        $account = $method->invoke($service, $productCode);

        return (string) $account->code;
    }

    private function rename(string $code, string $name): void
    {
        DB::connection('tenant')
            ->table('accounts')
            ->where('code', $code)
            ->update(['name' => $name, 'updated_at' => now()]);
    }
}
