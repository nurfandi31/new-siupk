<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Daftar Simpanan — lists savings-liability accounts grouped by savings type
 * (Pokok / Wajib / Sukarela / Berjangka) with opening, period movements and
 * closing balance.
 *
 * Implementation notes:
 *  - Source of truth is the `accounts` table (chart of accounts). There is no
 *    dedicated `savings` / `simpanan` projection in the schema, so detection
 *    uses code/name heuristics on liability accounts.
 *  - Account classification into simpanan types relies on:
 *      1. level-2 / level-3 code prefix (e.g. 2.1.10.* → Pokok),
 *      2. name keyword fallback (Pokok / Wajib / Sukarela / Berjangka),
 *      3. fallback bucket "Lainnya" when the account is clearly savings-like
 *         but does not match a specific kind.
 *  - Balances are computed live from journal_lines + opening balances via
 *    AccountBalanceQuery, mirroring the other accounting reports.
 *
 * @phpstan-type SavingsRow array{
 *     row_id: int,
 *     code: string,
 *     name: string,
 *     parent_code: string|null,
 *     parent_name: string|null,
 *     kind: string,
 *     kind_label: string,
 *     opening_balance: float,
 *     period_debit: float,
 *     period_credit: float,
 *     closing_balance: float
 * }
 */
final readonly class SimpananReportService
{
    /**
     * Code-prefix heuristics for the four canonical savings types. Longer
     * prefixes win — order matters (most specific first).
     *
     * @var list<array{prefix: string, kind: string}>
     */
    private const KIND_PREFIXES = [
        ['prefix' => '2.1.13', 'kind' => 'berjangka'],
        ['prefix' => '2.1.12', 'kind' => 'sukarela'],
        ['prefix' => '2.1.11', 'kind' => 'wajib'],
        ['prefix' => '2.1.10', 'kind' => 'pokok'],
    ];

    /** @var array<string, list<string>> */
    private const KIND_KEYWORDS = [
        'pokok' => ['simpanan pokok', 'pokok', 'modal setor', 'modal dasar'],
        'wajib' => ['simpanan wajib', 'wajib'],
        'sukarela' => ['simpanan sukarela', 'sukarela'],
        'berjangka' => ['simpanan berjangka', 'berjangka', 'deposito'],
    ];

    private const KIND_ORDER = ['pokok', 'wajib', 'sukarela', 'berjangka', 'lainnya'];

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
    ) {}

    /**
     * Build the Daftar Simpanan payload for the requested period.
     *
     * @return array{
     *     period: array<string, mixed>,
     *     identity: array<string, string|null>,
     *     rows: list<SavingsRow>,
     *     by_kind: array<string, array{label: string, opening_balance: float, period_debit: float, period_credit: float, closing_balance: float, count: int}>,
     *     totals: array{opening_balance: float, period_debit: float, period_credit: float, closing_balance: float},
     *     generated_at: string,
     *     tenant_id: int
     * }
     */
    public function buildReport(int $tenantId, int $year, ?int $month): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $periodFrom = CarbonImmutable::parse($period['from'])->startOfDay();
        $periodUntil = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();

        /** @var list<Account> $accounts */
        $accounts = Account::query()
            ->where('account_type', 'liability')
            ->where(function ($q) use ($asOf): void {
                // Active today OR deactivated but still on the books at the
                // as-of date — same heuristic used by other reports.
                $q->where('is_active', true)
                    ->orWhereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>', $asOf->toDateString());
            })
            ->whereDate('created_at', '<=', $asOf->toDateString())
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'normal_balance', 'level', 'parent_row_id']);

        // Parent-name lookup for nicer labels in the breakdown table.
        $parentLookup = Account::query()
            ->whereIn('row_id', $accounts->pluck('parent_row_id')->filter()->unique()->values()->all())
            ->get(['row_id', 'code', 'name'])
            ->keyBy(fn (Account $a) => (int) $a->row_id);

        $openings = $this->balances->openings($year);
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();

        $rows = [];
        $byKind = $this->emptyBucketMap();

        foreach ($accounts as $account) {
            $kind = $this->classifyAccount((string) $account->code, (string) $account->name);
            if ($kind === null) {
                // Liability account that isn't simpanan-related (Utang Bank,
                // Utang Pajak, etc.) — skip.
                continue;
            }

            // Closing balance (cumulative YTD through as-of, inclusive).
            $raw = $this->balances->asOfRaw($account, $asOf);
            $closing = round((float) $raw['signed'], 2);

            // Opening balance at start of period = openingSigned (year start)
            // + prior movements from Jan 1 .. periodFrom (inclusive).
            $openingPair = $this->balances->movementPair($openings->get((int) $account->row_id));
            $openingSigned = $this->balances->signedBalance($account, $openingPair['debit'], $openingPair['credit']);

            $priorMovements = $this->balances->movements($yearStart, $periodFrom);
            $priorPair = $this->balances->movementPair($priorMovements->get((int) $account->row_id));
            $priorSigned = $this->balances->signedBalance($account, $priorPair['debit'], $priorPair['credit']);

            $openingBalance = round($openingSigned + $priorSigned, 2);

            // Period-only movement (periodFrom .. periodUntil exclusive).
            $inPeriod = $this->balances->movements($periodFrom, $periodUntil)->get((int) $account->row_id);
            $inPeriodPair = $this->balances->movementPair($inPeriod);
            $periodDebit = round($inPeriodPair['debit'], 2);
            $periodCredit = round($inPeriodPair['credit'], 2);

            $parent = $account->parent_row_id !== null ? $parentLookup->get((int) $account->parent_row_id) : null;

            $row = [
                'row_id' => (int) $account->row_id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'parent_code' => $parent !== null ? (string) $parent->code : null,
                'parent_name' => $parent !== null ? (string) $parent->name : null,
                'kind' => $kind,
                'kind_label' => self::KIND_LABELS[$kind],
                'opening_balance' => $openingBalance,
                'period_debit' => $periodDebit,
                'period_credit' => $periodCredit,
                'closing_balance' => $closing,
            ];
            $rows[] = $row;

            $byKind[$kind]['opening_balance'] = round($byKind[$kind]['opening_balance'] + $openingBalance, 2);
            $byKind[$kind]['period_debit'] = round($byKind[$kind]['period_debit'] + $periodDebit, 2);
            $byKind[$kind]['period_credit'] = round($byKind[$kind]['period_credit'] + $periodCredit, 2);
            $byKind[$kind]['closing_balance'] = round($byKind[$kind]['closing_balance'] + $closing, 2);
            $byKind[$kind]['count']++;
        }

        $totals = [
            'opening_balance' => 0.0,
            'period_debit' => 0.0,
            'period_credit' => 0.0,
            'closing_balance' => 0.0,
        ];
        foreach ($byKind as $bucket) {
            foreach (array_keys($totals) as $k) {
                $totals[$k] = round($totals[$k] + $bucket[$k], 2);
            }
        }

        $profile = OrganizationProfile::query()->first();

        return [
            'period' => $period,
            'identity' => [
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
            ],
            'rows' => $rows,
            'by_kind' => $byKind,
            'totals' => $totals,
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
            'tenant_id' => $tenantId,
        ];
    }

    /**
     * Classify a liability account into a savings bucket. Returns null when
     * the account is not savings-related at all (e.g. Utang Bank, Utang Pajak,
     * Utang Gaji, Utang Pembagian Laba, etc.).
     */
    private function classifyAccount(string $code, string $name): ?string
    {
        $trimmedCode = trim($code);
        foreach (self::KIND_PREFIXES as $rule) {
            if ($trimmedCode !== '' && str_starts_with($trimmedCode, $rule['prefix'])) {
                return $rule['kind'];
            }
        }

        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }

        foreach (self::KIND_KEYWORDS as $kind => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($needle, $keyword)) {
                    return $kind;
                }
            }
        }

        // Liability account that mentions "simpanan"/"tabungan"/"deposit"
        // but doesn't match a specific kind → "lainnya".
        if (str_contains($needle, 'simpanan') || str_contains($needle, 'tabungan') || str_contains($needle, 'deposit')) {
            return 'lainnya';
        }

        return null;
    }

    /**
     * @return array<string, array{label: string, opening_balance: float, period_debit: float, period_credit: float, closing_balance: float, count: int}>
     */
    private function emptyBucketMap(): array
    {
        $buckets = [];
        foreach (self::KIND_ORDER as $kind) {
            $buckets[$kind] = [
                'label' => self::KIND_LABELS[$kind],
                'opening_balance' => 0.0,
                'period_debit' => 0.0,
                'period_credit' => 0.0,
                'closing_balance' => 0.0,
                'count' => 0,
            ];
        }

        return $buckets;
    }

    /**
     * Convenience overload — uses the current tenant from TenantContext.
     *
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month): array
    {
        $tenantId = (int) ($this->context->id() ?? 0);

        return $this->buildReport($tenantId, $year, $month);
    }

    /**
     * Lightweight DB integrity check — confirms the tenant connection can be
     * reached. Returned for future use (e.g. health checks) and to ensure the
     * connection is warmed up before a long report run.
     *
     * @internal
     */
    public function ping(int $tenantId): bool
    {
        return (bool) DB::connection('tenant')
            ->table('accounts')
            ->where('tenant_id', $tenantId)
            ->exists();
    }
}
