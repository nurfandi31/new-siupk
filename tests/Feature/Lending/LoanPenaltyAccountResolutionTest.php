<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Models\Loan;
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

    public function test_migrated_loan_uses_legacy_penalty_code_even_on_old_chart(): void
    {
        // Chart Next lama: 4.1.01.04 bernama denda, 4.1.02.01 bernama dividen.
        // Pinjaman migrasi kode 1 tetap wajib memakai kode pacuan 4.1.02.01.
        $this->rename('4.1.02.01', 'Pendapatan Dividen Unit Usaha 1');
        $this->rename('4.1.01.04', 'Pendapatan Denda Piutang SPP');

        $loan = $this->loanWithKode('01');

        self::assertSame('4.1.02.01', $this->resolveFor('resolvePenaltyAccount', 'spp', $loan));
        self::assertSame('4.1.01.01', $this->resolveFor('resolveRevenueAccount', 'spp', $loan));
        self::assertSame('1.1.03.01', $this->resolveFor('resolveReceivableAccount', 'spp', $loan));
    }

    public function test_migrated_member_kb_loan_uses_kode_7_accounts(): void
    {
        $loan = $this->loanWithKode('07');

        self::assertSame('4.1.01.07', $this->resolveFor('resolveRevenueAccount', 'pi', $loan));
        self::assertSame('4.1.02.07', $this->resolveFor('resolvePenaltyAccount', 'pi', $loan));
        self::assertSame('1.1.03.07', $this->resolveFor('resolveReceivableAccount', 'pi', $loan));
    }

    public function test_explicit_loan_account_codes_override_product_kode(): void
    {
        // Kode produk 01, tetapi akun eksplisit hasil migrasi menunjuk kode 07.
        // Kolom eksplisit harus menang agar kode produk tanpa akun legacy tidak
        // pernah jatuh ke akun Next yang kebetulan sama kodenya.
        $loan = $this->loanWithKode('01');
        $loan->legacy_account_codes = [
            'principal' => '1.1.03.07',
            'revenue' => '4.1.01.07',
            'penalty' => '4.1.02.07',
        ];

        self::assertSame('1.1.03.07', $this->resolveFor('resolveReceivableAccount', 'spp', $loan));
        self::assertSame('4.1.01.07', $this->resolveFor('resolveRevenueAccount', 'spp', $loan));
        self::assertSame('4.1.02.07', $this->resolveFor('resolvePenaltyAccount', 'spp', $loan));
    }

    public function test_partial_explicit_codes_do_not_fall_back_to_derived_principal(): void
    {
        // Chart legacy punya jasa/denda 07 tetapi tidak punya pokok 07. Array
        // eksplisit harus otoritatif: pokok jatuh ke akun produk, bukan pola
        // 1.1.03.07 yang bisa menabrak akun Next lain.
        $loan = $this->loanWithKode('01');
        $loan->legacy_account_codes = [
            'revenue' => '4.1.01.07',
            'penalty' => '4.1.02.07',
        ];

        self::assertSame('1.1.03.09', $this->resolveFor('resolveReceivableAccount', 'pi', $loan));
        self::assertSame('4.1.01.07', $this->resolveFor('resolveRevenueAccount', 'pi', $loan));
        self::assertSame('4.1.02.07', $this->resolveFor('resolvePenaltyAccount', 'pi', $loan));
    }

    public function test_kode_10_without_legacy_accounts_does_not_use_next_piutang_jasa(): void
    {
        // Chart Next punya 1.1.03.10 = Piutang Jasa Perorangan, bukan piutang
        // pokok legacy. Tanpa kode akun eksplisit, kode 10 harus fallback ke
        // akun produk, bukan memakai 1.1.03.10.
        $loan = $this->loanWithKode('10');

        self::assertSame('1.1.03.09', $this->resolveFor('resolveReceivableAccount', 'pi', $loan));
        self::assertSame('4.1.01.07', $this->resolveFor('resolveRevenueAccount', 'pi', $loan));
        self::assertSame('4.1.02.05', $this->resolveFor('resolvePenaltyAccount', 'pi', $loan));
    }

    public function test_kode_04_without_legacy_accounts_does_not_use_next_piutang_jasa(): void
    {
        // 1.1.03.04 di Next = Piutang Jasa SPP, bukan pokok. Pendapatan jasa
        // dan denda 4.1.01.04/4.1.02.04 tetap sah di chart legacy.
        $loan = $this->loanWithKode('04');

        self::assertSame('1.1.03.09', $this->resolveFor('resolveReceivableAccount', 'pi', $loan));
        self::assertSame('4.1.01.04', $this->resolveFor('resolveRevenueAccount', 'pi', $loan));
        // 4.1.02.04 tidak ada di chart default Next; fallback ke akun produk.
        // Yang penting pokok tidak memakai 1.1.03.04 (Piutang Jasa SPP).
        self::assertSame('4.1.02.05', $this->resolveFor('resolvePenaltyAccount', 'pi', $loan));
    }

    public function test_native_loan_without_legacy_kode_keeps_product_defaults(): void
    {
        $loan = new Loan;
        $loan->row_id = 987654;
        $loan->tenant_id = 1;

        self::assertSame('1.1.03.09', $this->resolveFor('resolveReceivableAccount', 'pi', $loan));
        self::assertSame('4.1.01.07', $this->resolveFor('resolveRevenueAccount', 'pi', $loan));
        self::assertSame('4.1.02.05', $this->resolveFor('resolvePenaltyAccount', 'pi', $loan));
    }

    private function loanWithKode(string $kode): Loan
    {
        $loan = new Loan;
        $loan->row_id = 900000 + (int) $kode;
        $loan->tenant_id = 1;
        $loan->legacy_product_kode = $kode;

        return $loan;
    }

    private function resolve(string $productCode): string
    {
        return $this->resolveFor('resolvePenaltyAccount', $productCode, null);
    }

    private function resolveFor(string $methodName, string $productCode, ?Loan $loan): string
    {
        $service = app(LoanService::class);
        $method = new ReflectionMethod($service, $methodName);
        $method->setAccessible(true);

        /** @var Account $account */
        $account = $method->invoke($service, $productCode, $loan);

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
