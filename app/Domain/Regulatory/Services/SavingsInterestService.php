<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Daftar Bunga Simpanan (OJK).
 *
 * Pertanyaan OJK: berapa bunga yang menjadi beban (atau Hak anggota)
 * untuk masing-masing jenis simpanan (Pokok / Wajib / Sukarela /
 * Berjangka) selama periode pelaporan.
 *
 * Karena COA default tidak punya akun beban bunga simpanan khusus,
 * pendekatan yang dipakai:
 *   1. Tampilkan saldo rata-rata simpanan per jenis (pembuka & akhir)
 *      lalu kalikan dengan asumsi tarif tahunan configurable.
 *   2. Untuk lembaga yang sudah mencatat `5.1.08.*` Beban Bunga Utang
 *      atau akun beban bunga simpanan lainnya (code-prefix 5.1.08.0X
 *      dan nama mengandung "simpanan"/"tabungan"/"deposito"),
 *      tampilkan mutasi periodenya sebagai beban bunga riil.
 *
 * Hasil akhir adalah tabel 4 baris (Pokok/Wajib/Sukarela/Berjangka)
 * + total. Kolom tarif & bunga estimasi dijelaskan dengan jelas di
 * PDF/Vue agar tidak menyesatkan auditor OJK.
 */
final readonly class SavingsInterestService
{
    /**
     * Tarif asumsi tahunan (% — desimal). Bisa dioverride via env
     * SAVINGS_INTEREST_DEFAULT_RATE (misal "0.06" = 6% per tahun).
     */
    private float $defaultRate;

    /** @var list<array{prefix: string, kind: string}> */
    private const SAVINGS_PREFIXES = [
        ['prefix' => '2.1.13', 'kind' => 'berjangka'],
        ['prefix' => '2.1.12', 'kind' => 'sukarela'],
        ['prefix' => '2.1.11', 'kind' => 'wajib'],
        ['prefix' => '2.1.10', 'kind' => 'pokok'],
    ];

    /** @var array<string, list<string>> */
    private const SAVINGS_KEYWORDS = [
        'pokok' => ['simpanan pokok', 'pokok', 'modal setor', 'modal dasar'],
        'wajib' => ['simpanan wajib', 'wajib'],
        'sukarela' => ['simpanan sukarela', 'sukarela'],
        'berjangka' => ['simpanan berjangka', 'berjangka', 'deposito'],
    ];

    /** @var array<string, string> */
    private const KIND_LABELS = [
        'pokok' => 'Simpanan Pokok',
        'wajib' => 'Simpanan Wajib',
        'sukarela' => 'Simpanan Sukarela',
        'berjangka' => 'Simpanan Berjangka',
        'lainnya' => 'Simpanan Lainnya',
    ];

    public function __construct(
        private AccountBalanceQuery $balances,
        private TenantContext $context,
    ) {
        $envRate = (string) (function_exists('env') ? env('SAVINGS_INTEREST_DEFAULT_RATE', '0.06') : '0.06');
        $envRate = trim($envRate) === '' ? '0.06' : $envRate;
        $this->defaultRate = (float) $envRate;
    }

    /**
     * @return array{
     *   period: array<string, mixed>,
     *   identity: array<string, string|null>,
     *   default_rate: float,
     *   interest_rows: list<array<string, mixed>>,
     *   totals: array<string, float>,
     *   expense_rows: list<array<string, mixed>>,
     *   generated_at: string,
     *   tenant_id: int
     * }
     */
    public function buildReport(int $tenantId, int $year, ?int $month): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $from = CarbonImmutable::parse($period['from'])->startOfDay();
        $until = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();
        $days = max(1, (int) $from->diffInDays($until));
        $isMonthly = (bool) ($period['is_monthly'] ?? false);

        $savingsAccounts = $this->savingsAccounts($asOf);
        $openingBalances = $this->balances->movements(
            CarbonImmutable::create($year, 1, 1)->startOfDay(),
            $from,
        );
        $openings = $this->balances->openings($year);

        $rows = [];
        $buckets = $this->emptyBuckets();
        $totalOpening = 0.0;
        $totalClosing = 0.0;

        foreach ($savingsAccounts as $account) {
            $kind = $this->classifyAccount((string) $account->code, (string) $account->name);
            if ($kind === null) {
                continue;
            }

            $closingRaw = $this->balances->asOfRaw($account, $asOf);
            $closing = round((float) $closingRaw['signed'], 2);

            $openingPair = $this->balances->movementPair($openings->get((int) $account->row_id));
            $priorPair = $this->balances->movementPair($openingBalances->get((int) $account->row_id));
            $opening = round(
                $this->balances->signedBalance($account, (float) $openingPair['debit'] + (float) $priorPair['debit'], (float) $openingPair['credit'] + (float) $priorPair['credit']),
                2,
            );

            $rate = $this->defaultRate;
            $average = round(($opening + $closing) / 2, 2);
            $interest = round($average * $rate * ($days / 365), 2);

            $rows[] = [
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'kind' => $kind,
                'kind_label' => self::KIND_LABELS[$kind] ?? 'Simpanan Lainnya',
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'average_balance' => $average,
                'rate' => $rate,
                'days' => $days,
                'interest_estimated' => $interest,
            ];

            $buckets[$kind]['opening_balance'] = round(((float) $buckets[$kind]['opening_balance']) + $opening, 2);
            $buckets[$kind]['closing_balance'] = round(((float) $buckets[$kind]['closing_balance']) + $closing, 2);
            $buckets[$kind]['interest_estimated'] = round(((float) $buckets[$kind]['interest_estimated']) + $interest, 2);
            $buckets[$kind]['count']++;

            $totalOpening = round($totalOpening + $opening, 2);
            $totalClosing = round($totalClosing + $closing, 2);
        }

        $totalInterest = 0.0;
        $weightRateSum = 0.0;
        $weightCount = 0;
        foreach ($buckets as $bucket) {
            $count = (int) ($bucket['count'] ?? 0);
            if ($count === 0) {
                continue;
            }
            $totalInterest = round($totalInterest + (float) $bucket['interest_estimated'], 2);
            $weightRateSum += (float) ($bucket['rate'] ?? 0) * $count;
            $weightCount += $count;
        }
        $averageRate = $weightCount > 0 ? round($weightRateSum / $weightCount, 4) : $this->defaultRate;

        $expenseRows = $this->collectSavingsInterestExpense($asOf, $from, $until);

        $profile = OrganizationProfile::query()->first();

        return [
            'period' => $period,
            'identity' => $this->identity($profile),
            'default_rate' => $this->defaultRate,
            'is_monthly' => $isMonthly,
            'days_in_period' => $days,
            'interest_rows' => $rows,
            'buckets' => $buckets,
            'totals' => [
                'opening_balance' => $totalOpening,
                'closing_balance' => $totalClosing,
                'average_balance' => round(($totalOpening + $totalClosing) / 2, 2),
                'interest_estimated' => $totalInterest,
                'average_rate' => $averageRate,
            ],
            'expense_rows' => $expenseRows,
            'expense_total' => round(array_sum(array_column($expenseRows, 'period_debit')), 2),
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
            'tenant_id' => $tenantId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month): array
    {
        $tenantId = (int) ($this->context->id() ?? 0);

        return $this->buildReport($tenantId, $year, $month);
    }

    /**
     * @return Collection<int, Account>
     */
    private function savingsAccounts(CarbonImmutable $asOf): Collection
    {
        return Account::query()
            ->where('account_type', 'liability')
            ->where(function ($q) use ($asOf): void {
                $q->where('is_active', true)
                    ->orWhereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>', $asOf->toDateString());
            })
            ->whereDate('created_at', '<=', $asOf->toDateString())
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'normal_balance']);
    }

    private function classifyAccount(string $code, string $name): ?string
    {
        $trimmed = trim($code);
        foreach (self::SAVINGS_PREFIXES as $rule) {
            if ($trimmed !== '' && str_starts_with($trimmed, $rule['prefix'])) {
                return $rule['kind'];
            }
        }
        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }
        foreach (self::SAVINGS_KEYWORDS as $kind => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($needle, $keyword)) {
                    return $kind;
                }
            }
        }
        if (str_contains($needle, 'simpanan') || str_contains($needle, 'tabungan') || str_contains($needle, 'deposit')) {
            return 'lainnya';
        }

        return null;
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function emptyBuckets(): array
    {
        $order = ['pokok', 'wajib', 'sukarela', 'berjangka', 'lainnya'];
        $buckets = [];
        foreach ($order as $kind) {
            $buckets[$kind] = [
                'label' => self::KIND_LABELS[$kind] ?? $kind,
                'opening_balance' => 0.0,
                'closing_balance' => 0.0,
                'interest_estimated' => 0.0,
                'count' => 0,
                'rate' => $this->defaultRate,
            ];
        }

        return $buckets;
    }

    /**
     * Kumpulkan akun beban bunga simpanan (5.1.08.x dengan nama mengandung
     * "simpanan/tabungan/deposito") atau akun 5.x generik dengan keyword
     * "bunga simpanan".
     *
     * @return list<array<string, mixed>>
     */
    private function collectSavingsInterestExpense(CarbonImmutable $asOf, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $expenseAccounts = Account::query()
            ->where('account_type', 'expense')
            ->where(function ($q) use ($asOf): void {
                $q->where('is_active', true)
                    ->orWhereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>', $asOf->toDateString());
            })
            ->whereDate('created_at', '<=', $asOf->toDateString())
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'normal_balance']);

        $movements = $this->balances->movements($from, $until);

        $rows = [];
        foreach ($expenseAccounts as $account) {
            if (! $this->isSavingsInterestExpense((string) $account->code, (string) $account->name)) {
                continue;
            }
            $mv = $this->balances->movementPair($movements->get((int) $account->row_id));
            $debit = round((float) $mv['debit'], 2);
            $credit = round((float) $mv['credit'], 2);
            $rows[] = [
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'period_debit' => $debit,
                'period_credit' => $credit,
                'period_net' => round($debit - $credit, 2),
            ];
        }

        return $rows;
    }

    private function isSavingsInterestExpense(string $code, string $name): bool
    {
        $needle = mb_strtolower($name);

        if (str_starts_with($code, '5.1.08') && (
            str_contains($needle, 'simpanan') || str_contains($needle, 'tabungan') || str_contains($needle, 'deposito')
        )) {
            return true;
        }

        if (str_contains($needle, 'bunga') && (
            str_contains($needle, 'simpanan') || str_contains($needle, 'tabungan') || str_contains($needle, 'deposito')
        )) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, string|null>
     */
    private function identity(?OrganizationProfile $profile): array
    {
        return [
            'legal_name' => (string) ($profile?->legal_name ?? ''),
            'short_name' => $profile?->short_name,
            'logo_url' => $profile?->logo_url,
            'registration_number' => (string) ($profile?->registration_number ?? ''),
            'address' => (string) ($profile?->address ?? ''),
            'phone' => (string) ($profile?->phone ?? ''),
            'district_name' => (string) ($profile?->district_name ?? ''),
            'regency_name' => (string) ($profile?->regency_name ?? ''),
            'manager_name' => (string) ($profile?->manager_name ?? ''),
            'manager_title' => (string) ($profile?->manager_title ?? ''),
            'treasurer_name' => (string) ($profile?->treasurer_name ?? ''),
            'treasurer_title' => (string) ($profile?->treasurer_title ?? ''),
        ];
    }
}
