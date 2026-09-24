<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports\YearEnd;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Accounting\Services\ProfitAllocationService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Services\TenantSettingService;
use Carbon\CarbonImmutable;

/**
 * Preview Alokasi Laba Tutup Buku.
 *
 * Menampilkan simulasi alokasi surplus tahun buku berjalan ke pos-pos
 * standar koperasi/lemaga keuangan mikro:
 *   - Cadangan Modal
 *   - Jasa Produksi / SHU Anggota
 *   - Dana Pendidikan
 *   - Dana Pengurus / Pengawas
 *   - Dana Sosial
 *   - Laba Ditahan
 *
 * Default persentase mengikuti praktik umum Bumdesma LKD:
 *   Cadangan    : 40%
 *   Jasa Anggota: 30%
 *   Pendidikan  : 10%
 *   Pengurus    : 10%
 *   Sosial      : 5%
 *   Ditahan     : 5%
 *
 * Persentase dapat di-override via input user atau tenant_settings.
 *
 * Laporan ini hanya simulasi; jurnal alokasi sebenarnya diproses
 * via PeriodCloseController + ProfitAllocationService.
 */
final readonly class AllocationService
{
    public const NOTES_KEY = 'year_end.allocation.notes';

    public const NOTES_PCT_KEY = 'year_end.allocation.percentages';

    /** @var list<array{key: string, label: string, default_pct: float, target: string}> */
    public const DEFAULT_LINES = [
        ['key' => 'cadangan',     'label' => 'Cadangan Modal',         'default_pct' => 40.0, 'target' => 'equity_retained'],
        ['key' => 'jasa_anggota', 'label' => 'Jasa Produksi / SHU',     'default_pct' => 30.0, 'target' => 'community'],
        ['key' => 'pendidikan',   'label' => 'Dana Pendidikan',         'default_pct' => 10.0, 'target' => 'community'],
        ['key' => 'pengurus',     'label' => 'Dana Pengurus/Pengawas',  'default_pct' => 10.0, 'target' => 'community'],
        ['key' => 'sosial',       'label' => 'Dana Sosial',             'default_pct' => 5.0,  'target' => 'community'],
        ['key' => 'ditahan',      'label' => 'Laba Ditahan (sisa)',     'default_pct' => 5.0,  'target' => 'equity_retained'],
    ];

    public function __construct(
        private AccountBalanceQuery $balances,
        private TenantSettingService $settings,
    ) {}

    /**
     * Bangun preview alokasi laba.
     *
     * @param  array<string, float>|null  $percentages  Override persentase (key=>pct). Null = gunakan default.
     * @return array<string, mixed>
     */
    public function build(int $year, ?array $percentages = null): array
    {
        $period = $this->balances->resolvePeriod($year, null);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();

        // Surplus = Laba tahun berjalan (pendapatan - beban) YTD
        $surplus = round($this->balances->netIncome($asOf), 2);

        // Pull stored percentages if not provided
        if ($percentages === null) {
            $stored = $this->settings->get(self::NOTES_PCT_KEY, null);
            if (is_array($stored)) {
                $percentages = [];
                foreach ($stored as $k => $v) {
                    $percentages[(string) $k] = (float) $v;
                }
            }
        }

        $lines = [];
        $allocated = 0.0;
        foreach (self::DEFAULT_LINES as $idx => $def) {
            $key = $def['key'];
            $pct = isset($percentages[$key])
                ? (float) $percentages[$key]
                : (float) $def['default_pct'];

            $amount = round($surplus * ($pct / 100.0), 2);
            $lines[] = [
                'no' => $idx + 1,
                'key' => $key,
                'label' => $def['label'],
                'target' => $def['target'],
                'percentage' => $pct,
                'amount' => $amount,
                'note' => $this->targetDescription($def['target']),
            ];

            // Ditahan (sisa) dihitung belakangan sebagai selisih, tapi untuk
            // display kita hitung semua linier dulu. Sebagai catatan:
            // "Laba Ditahan" sebenarnya = surplus - total pos lain.
            // Kita di sini tetap tampilkan sesuai input, kecuali 'ditahan'.
            if ($key !== 'ditahan') {
                $allocated += $amount;
            }
        }

        // Override 'ditahan' sebagai selisih (supaya tepat = surplus)
        // kecuali jika user input eksplisit dengan key 'ditahan'.
        $userExplicit = $percentages !== null && array_key_exists('ditahan', $percentages);
        if ($userExplicit) {
            $ditahanIdx = array_search('ditahan', array_column(self::DEFAULT_LINES, 'key'), true);
            if ($ditahanIdx !== false) {
                $lines[$ditahanIdx]['amount'] = round($surplus * ($lines[$ditahanIdx]['percentage'] / 100.0), 2);
            }
        } else {
            $ditahanIdx = array_search('ditahan', array_column(self::DEFAULT_LINES, 'key'), true);
            if ($ditahanIdx !== false) {
                $remaining = round(max(0, $surplus - $allocated), 2);
                $lines[$ditahanIdx]['amount'] = $remaining;
                $lines[$ditahanIdx]['percentage'] = $surplus > 0
                    ? round(($remaining / $surplus) * 100.0, 2)
                    : 0.0;
            }
        }

        // Total dialokasikan (semua kecuali 'ditahan')
        $totalAllocatedExclRetained = 0.0;
        foreach ($lines as $line) {
            if ($line['key'] !== 'ditahan') {
                $totalAllocatedExclRetained += $line['amount'];
            }
        }
        $totalAllocated = round(array_sum(array_column($lines, 'amount')), 2);
        $sisa = round($surplus - $totalAllocated, 2);

        $profile = OrganizationProfile::query()->first();
        $notes = $this->settings->get(self::NOTES_KEY, '');
        if (! is_string($notes)) {
            $notes = '';
        }

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? config('app.name')),
                'short_name' => $profile?->short_name,
                'address' => $profile?->address,
                'registration_number' => $profile?->registration_number,
            ],
            'is_preview' => true,
            'year' => $year,
            'surplus' => $surplus,
            'lines' => $lines,
            'totals' => [
                'allocated' => $totalAllocated,
                'allocated_excl_retained' => round($totalAllocatedExclRetained, 2),
                'retained' => $lines[$ditahanIdx]['amount'] ?? 0.0,
                'remaining' => $sisa,
                'pct_allocated' => $surplus > 0
                    ? round(($totalAllocated / $surplus) * 100.0, 2)
                    : 0.0,
            ],
            'notes' => $notes,
            'default_lines' => self::DEFAULT_LINES,
            'account_targets' => $this->resolveAllocationAccounts(),
            'summary' => [
                'total_surplus' => $surplus,
                'total_allocated' => $totalAllocated,
                'pct_allocated' => $surplus > 0
                    ? round(($totalAllocated / $surplus) * 100.0, 2)
                    : 0.0,
            ],
        ];
    }

    /**
     * Simpan catatan manajemen untuk laporan alokasi.
     */
    public function saveNotes(int $year, string $notes, ?array $percentages = null): void
    {
        $this->settings->set(self::NOTES_KEY, $notes, 'string');
        if ($percentages !== null) {
            $this->settings->set(self::NOTES_PCT_KEY, $percentages, 'json');
        }
    }

    private function targetDescription(string $target): string
    {
        return match ($target) {
            'equity_retained' => 'Dicatat sebagai tambahan ekuitas / cadangan',
            'community' => 'Dicatat sebagai utang jangka pendek (akan dibagikan)',
            default => '',
        };
    }

    /**
     * Resolve akun-akun tujuan alokasi (untuk referensi jurnal sebenarnya).
     *
     * @return array<string, array{code: string, name: string, row_id: int|null}>
     */
    private function resolveAllocationAccounts(): array
    {
        $codes = [
            'earnings' => ProfitAllocationService::EARNINGS_CODE,
            'retained' => ProfitAllocationService::RETAINED_CODE,
            'community' => ProfitAllocationService::COMMUNITY_CODE,
            'village' => ProfitAllocationService::VILLAGE_CODE,
            'investor' => ProfitAllocationService::INVESTOR_CODE,
        ];

        $out = [];
        foreach ($codes as $key => $code) {
            $account = Account::query()
                ->where('code', $code)
                ->where('is_postable', true)
                ->first();
            $out[$key] = [
                'code' => $code,
                'name' => (string) ($account?->name ?? ''),
                'row_id' => $account ? (int) $account->row_id : null,
            ];
        }

        return $out;
    }
}
