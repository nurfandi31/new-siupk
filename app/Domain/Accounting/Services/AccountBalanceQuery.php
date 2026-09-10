<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Account;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Live balances from posted journal lines + year opening balances.
 * Does not depend on account_monthly_balances projection.
 */
final readonly class AccountBalanceQuery
{
    public const CURRENT_EARNINGS_CODE = '3.2.02.01';

    public function __construct(
        private TenantContext $context,
    ) {}

    /**
     * Inclusive from, exclusive until.
     *
     * @return Collection<int, object{debit: string, credit: string}>
     */
    public function movements(CarbonImmutable $from, CarbonImmutable $untilExclusive): Collection
    {
        $tenantId = $this->context->id();

        return DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $from->toDateString())
            ->where('entries.transaction_date', '<', $untilExclusive->toDateString())
            ->groupBy('lines.account_row_id')
            ->selectRaw('lines.account_row_id')
            ->selectRaw('CAST(COALESCE(SUM(lines.debit), 0) AS CHAR) AS debit')
            ->selectRaw('CAST(COALESCE(SUM(lines.credit), 0) AS CHAR) AS credit')
            ->get()
            ->keyBy(fn ($row) => (int) $row->account_row_id);
    }

    /**
     * @return Collection<int, object{debit: string, credit: string}>
     */
    public function openings(int $year): Collection
    {
        $tenantId = $this->context->id();

        return DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $tenantId)
            ->where('fiscal_year', $year)
            ->get(['account_row_id', 'debit', 'credit'])
            ->keyBy(fn ($row) => (int) $row->account_row_id);
    }

    /**
     * @return array{debit: float, credit: float}
     */
    public function movementPair(?object $row): array
    {
        return [
            'debit' => round((float) ($row->debit ?? 0), 2),
            'credit' => round((float) ($row->credit ?? 0), 2),
        ];
    }

    /**
     * Signed balance in the account's normal direction.
     * D-normal: debit − credit; C-normal: credit − debit.
     */
    public function signedBalance(object $account, float $debit, float $credit): float
    {
        $normal = (string) ($account->normal_balance ?? 'D');
        $type = (string) ($account->account_type ?? '');

        if ($normal === 'C' || in_array($type, ['liability', 'equity', 'revenue'], true)) {
            return round($credit - $debit, 2);
        }

        return round($debit - $credit, 2);
    }

    /**
     * YTD raw debit/credit through as-of (inclusive): opening(year) + movements(Jan1 .. asOf+1day).
     *
     * @return array{debit: float, credit: float, signed: float}
     */
    public function asOfRaw(object $account, CarbonImmutable $asOf): array
    {
        $year = (int) $asOf->year;
        $openings = $this->openings($year);
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $until = $asOf->addDay()->startOfDay();
        $movements = $this->movements($yearStart, $until);

        $opening = $this->movementPair($openings->get((int) $account->row_id));
        $movement = $this->movementPair($movements->get((int) $account->row_id));

        $debit = round($opening['debit'] + $movement['debit'], 2);
        $credit = round($opening['credit'] + $movement['credit'], 2);

        return [
            'debit' => $debit,
            'credit' => $credit,
            'signed' => $this->signedBalance($account, $debit, $credit),
        ];
    }

    /**
     * Net income YTD: Σ revenue signed − Σ expense signed (through asOf inclusive).
     */
    public function netIncome(CarbonImmutable $asOf): float
    {
        $year = (int) $asOf->year;
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $until = $asOf->addDay()->startOfDay();
        $openings = $this->openings($year);
        $movements = $this->movements($yearStart, $until);

        $accounts = Account::query()
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('is_postable', true)
            ->get(['row_id', 'code', 'account_type', 'normal_balance']);

        $revenue = 0.0;
        $expense = 0.0;

        foreach ($accounts as $account) {
            $opening = $this->movementPair($openings->get((int) $account->row_id));
            $movement = $this->movementPair($movements->get((int) $account->row_id));
            $debit = $opening['debit'] + $movement['debit'];
            $credit = $opening['credit'] + $movement['credit'];
            $signed = $this->signedBalance($account, $debit, $credit);

            if ($account->account_type === 'revenue') {
                $revenue += $signed;
            } else {
                $expense += $signed;
            }
        }

        return round($revenue - $expense, 2);
    }

    /**
     * @return array{year: int, month: int|null, as_of: string, from: string, until_exclusive: string, period_label: string, is_monthly: bool}
     */
    public function resolvePeriod(int $year, ?int $month): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Tahun tidak valid.');
        }
        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new InvalidArgumentException('Bulan tidak valid.');
        }

        $labels = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        if ($month === null) {
            $from = CarbonImmutable::create($year, 1, 1)->startOfDay();
            $asOf = CarbonImmutable::create($year, 12, 31)->startOfDay();
            $until = $from->addYear();

            return [
                'year' => $year,
                'month' => null,
                'as_of' => $asOf->toDateString(),
                'from' => $from->toDateString(),
                'until_exclusive' => $until->toDateString(),
                'period_label' => "Januari – Desember {$year}",
                'is_monthly' => false,
            ];
        }

        $from = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $asOf = $from->endOfMonth()->startOfDay();
        $until = $from->addMonth();

        return [
            'year' => $year,
            'month' => $month,
            'as_of' => $asOf->toDateString(),
            'from' => $from->toDateString(),
            'until_exclusive' => $until->toDateString(),
            'period_label' => ($labels[$month] ?? "Bulan {$month}")." {$year}",
            'is_monthly' => true,
        ];
    }

    /**
     * Place signed balance on debit or credit column by normal side.
     *
     * @return array{debit: float, credit: float}
     */
    public function splitToColumns(object $account, float $signed): array
    {
        $normal = (string) ($account->normal_balance ?? 'D');
        $type = (string) ($account->account_type ?? '');
        $isDebitSide = $normal === 'D'
            || in_array($type, ['asset', 'expense'], true);

        if ($isDebitSide) {
            return $signed >= 0
                ? ['debit' => $signed, 'credit' => 0.0]
                : ['debit' => 0.0, 'credit' => abs($signed)];
        }

        return $signed >= 0
            ? ['debit' => 0.0, 'credit' => $signed]
            : ['debit' => abs($signed), 'credit' => 0.0];
    }

    public function isBalanceSheetType(string $type): bool
    {
        return in_array($type, ['asset', 'liability', 'equity'], true);
    }

    public function isIncomeStatementType(string $type): bool
    {
        return in_array($type, ['revenue', 'expense'], true);
    }
}
