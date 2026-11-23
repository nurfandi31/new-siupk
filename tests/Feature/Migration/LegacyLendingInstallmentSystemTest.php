<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Migration\Lending\DTO\NormalizedGroupLoan;
use App\Domain\Migration\Lending\LegacyLendingNormalizer;
use App\Domain\Migration\Support\LegacyAmountParser;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LegacyLendingInstallmentSystemTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private LegacyLendingNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        DB::connection('tenant')->table('loan_products')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => 1,
            'public_id' => 'product-test',
            'code' => 'spp',
            'name' => 'SPP',
            'interest_method' => 'flat',
            'default_interest_rate' => 1.5,
            'default_term_months' => 12,
            'minimum_amount' => 0,
            'maximum_amount' => 0,
            'borrower_scope' => 'group',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->normalizer = new LegacyLendingNormalizer(
            app(TenantContext::class),
            app(LegacyAmountParser::class),
        );
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_legacy_m6_maps_principal_grace_to_six_months(): void
    {
        $loan = $this->normalize(['sistem_angsuran' => '26']);

        self::assertSame('monthly', $loan->principalFrequency);
        self::assertSame('monthly', $loan->interestFrequency);
        self::assertSame(6, $loan->principalGraceMonths);
        self::assertSame(0, $loan->interestGraceMonths);
    }

    public function test_legacy_dua_mingguan_maps_to_two_month_interval(): void
    {
        // Tabel legacy `sistem_angsuran` id 25 = "Dua Mingguan" dengan
        // kolom sistem = 2. Pacuan hanya memakai +7 hari untuk id 12,
        // sehingga id 25 berperilaku interval 2 bulan.
        $loan = $this->normalize([
            'sistem_angsuran' => '25',
            'sa_jasa' => '25',
        ]);

        self::assertSame('bimonthly', $loan->principalFrequency);
        self::assertSame('bimonthly', $loan->interestFrequency);
        self::assertSame(0, $loan->principalGraceMonths);
        self::assertSame(0, $loan->interestGraceMonths);
    }

    public function test_legacy_seasonal_uses_monthly_interval(): void
    {
        // Id 5 "Musiman" memiliki kolom sistem = 1 dan tidak punya cabang
        // khusus di pacuan, jadi setara bulanan tanpa grace.
        $loan = $this->normalize(['sistem_angsuran' => '5']);

        self::assertSame('monthly', $loan->principalFrequency);
        self::assertSame(0, $loan->principalGraceMonths);
        self::assertSame(0, $loan->interestGraceMonths);
    }

    public function test_legacy_24_month_system_maps_flat_interest_and_principal_interval(): void
    {
        $loan = $this->normalize(['sistem_angsuran' => '16']);

        self::assertSame('every_24_months', $loan->principalFrequency);
        self::assertSame('monthly', $loan->interestFrequency);
    }

    public function test_legacy_four_month_system_maps_both_components_from_their_own_ids(): void
    {
        $loan = $this->normalize([
            'sistem_angsuran' => '3',
            'sa_jasa' => '3',
        ]);

        self::assertSame('every_4_months', $loan->principalFrequency);
        self::assertSame('every_4_months', $loan->interestFrequency);
        self::assertSame(0, $loan->principalGraceMonths);
        self::assertSame(0, $loan->interestGraceMonths);
    }

    public function test_legacy_mixed_systems_map_principal_and_interest_separately(): void
    {
        $loan = $this->normalize([
            'sistem_angsuran' => '2',
            'sa_jasa' => '1',
        ]);

        self::assertSame('quarterly', $loan->principalFrequency);
        self::assertSame('monthly', $loan->interestFrequency);
    }

    public function test_legacy_effective_interest_maps_to_effective_method(): void
    {
        $loan = $this->normalize([
            'sistem_angsuran' => '1',
            'sa_jasa' => '1',
            'jenis_jasa' => '2',
        ]);

        self::assertEquals('effective', $loan->installmentMethod);
    }

    public function test_unknown_legacy_system_keeps_monthly_default(): void
    {
        $loan = $this->normalize(['sistem_angsuran' => '1']);

        self::assertSame('monthly', $loan->principalFrequency);
        self::assertSame('monthly', $loan->interestFrequency);
        self::assertSame(0, $loan->principalGraceMonths);
        self::assertSame(0, $loan->interestGraceMonths);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function normalize(array $overrides = []): NormalizedGroupLoan
    {
        $this->normalizer->warmCaches();

        $row = (object) [
            'id' => 9001,
            'id_kel' => 1,
            'jenis_pp' => 1,
            'alokasi' => 12000000,
            'pros_jasa' => 1.5,
            'jangka' => 24,
            'pinjaman_ke' => 1,
            'status' => 'A',
            'sistem_angsuran' => '1',
            ...$overrides,
        ];

        $result = $this->normalizer->normalizeGroupLoan($row, 'pinjaman_kelompok', fn () => false);

        self::assertNull($result['error']);
        self::assertInstanceOf(NormalizedGroupLoan::class, $result['ok']);

        return $result['ok'];
    }
}
