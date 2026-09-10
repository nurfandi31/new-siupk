<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;

final readonly class IncomeStatementService
{
    public function __construct(
        private AccountBalanceQuery $balances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();

        // Period movement bounds
        $periodFrom = CarbonImmutable::parse($period['from'])->startOfDay();
        $periodUntil = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();

        // Prior cumulative: YTD through day before period start (monthly) or 0 (annual)
        $priorUntil = $period['is_monthly']
            ? $periodFrom
            : $yearStart;

        $openings = $this->balances->openings($year);
        $ytdMovements = $this->balances->movements($yearStart, $asOf->addDay()->startOfDay());
        $priorMovements = $period['is_monthly']
            ? $this->balances->movements($yearStart, $priorUntil)
            : collect();
        $periodMovements = $this->balances->movements($periodFrom, $periodUntil);

        $accounts = Account::query()
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('is_postable', true)
            ->whereDate('created_at', '<=', $periodUntil->toDateString())
            ->where(function ($q) use ($periodFrom, $periodUntil): void {
                $q->whereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>=', $periodFrom->toDateString())
                    ->orWhereIn('row_id', Account::query()
                        ->from('accounts as future_accounts')
                        ->join('journal_lines as lines', 'lines.account_row_id', '=', 'future_accounts.row_id')
                        ->join('journal_entries as entries', function ($join): void {
                            $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                                ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
                        })
                        ->where('entries.status', 'posted')
                        ->where('entries.transaction_date', '>=', $periodFrom->toDateString())
                        ->where('entries.transaction_date', '<', $periodUntil->toDateString())
                        ->whereDate('future_accounts.created_at', '<=', $periodUntil->toDateString())
                        ->select('future_accounts.row_id'));
            })
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance', 'level', 'parent_row_id']);

        $level2Parents = Account::query()
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('level', 2)
            ->whereDate('created_at', '<=', $periodUntil->toDateString())
            ->where(function ($q) use ($periodFrom): void {
                $q->whereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>=', $periodFrom->toDateString());
            })
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'level']);

        $groups = [];
        foreach ($level2Parents as $parent) {
            $children = [];
            $sumPrior = 0.0;
            $sumCurrent = 0.0;
            $sumYtd = 0.0;

            foreach ($accounts as $account) {
                if (! $this->isUnderLevel2((string) $account->code, (string) $parent->code)) {
                    continue;
                }

                $ytd = $this->cumulativeSigned($account, $openings, $ytdMovements);
                $prior = $period['is_monthly']
                    ? $this->cumulativeSigned($account, $openings, $priorMovements)
                    : 0.0;
                // period movement only (no opening in period col)
                $periodPair = $this->balances->movementPair($periodMovements->get((int) $account->row_id));
                $current = $this->balances->signedBalance($account, $periodPair['debit'], $periodPair['credit']);

                // Keep zero rows so layout matches legacy full chart listing.
                $children[] = [
                    'row_id' => (int) $account->row_id,
                    'code' => (string) $account->code,
                    'name' => (string) $account->name,
                    'prior' => round($prior, 2),
                    'current' => round($current, 2),
                    'ytd' => round($ytd, 2),
                ];
                $sumPrior += $prior;
                $sumCurrent += $current;
                $sumYtd += $ytd;
            }

            if ($children === []) {
                continue;
            }

            $groups[] = [
                'code' => (string) $parent->code,
                'name' => (string) $parent->name,
                'account_type' => (string) $parent->account_type,
                'bucket' => $this->bucket((string) $parent->code, (string) $parent->account_type),
                'children' => $children,
                'prior' => round($sumPrior, 2),
                'current' => round($sumCurrent, 2),
                'ytd' => round($sumYtd, 2),
            ];
        }

        $sum = fn (string $bucket, string $field): float => round(
            array_sum(array_map(
                fn (array $g) => $g['bucket'] === $bucket ? $g[$field] : 0.0,
                $groups,
            )),
            2,
        );

        $revOpsYtd = $sum('revenue_ops', 'ytd');
        $expOpsYtd = $sum('expense_ops', 'ytd');
        $revNonYtd = $sum('revenue_non', 'ytd');
        $expNonYtd = $sum('expense_non', 'ytd');
        $taxYtd = $sum('tax', 'ytd');

        $aYtd = round($revOpsYtd - $expOpsYtd, 2);
        $bYtd = round($revNonYtd - $expNonYtd, 2);
        $beforeTax = round($aYtd + $bYtd, 2);
        $afterTax = round($beforeTax - $taxYtd, 2);

        $revOpsCur = $sum('revenue_ops', 'current');
        $expOpsCur = $sum('expense_ops', 'current');
        $revNonCur = $sum('revenue_non', 'current');
        $expNonCur = $sum('expense_non', 'current');
        $taxCur = $sum('tax', 'current');
        $aCur = round($revOpsCur - $expOpsCur, 2);
        $bCur = round($revNonCur - $expNonCur, 2);
        $beforeTaxCur = round($aCur + $bCur, 2);
        $afterTaxCur = round($beforeTaxCur - $taxCur, 2);

        $revOpsPrior = $sum('revenue_ops', 'prior');
        $expOpsPrior = $sum('expense_ops', 'prior');
        $revNonPrior = $sum('revenue_non', 'prior');
        $expNonPrior = $sum('expense_non', 'prior');
        $taxPrior = $sum('tax', 'prior');
        $aPrior = round($revOpsPrior - $expOpsPrior, 2);
        $bPrior = round($revNonPrior - $expNonPrior, 2);
        $beforeTaxPrior = round($aPrior + $bPrior, 2);
        $afterTaxPrior = round($beforeTaxPrior - $taxPrior, 2);

        $profile = OrganizationProfile::query()->first();

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? ''),
                'short_name' => $profile?->short_name,
            ],
            'header_lalu' => $period['is_monthly'] ? 'Bulan Lalu' : 'Tahun Lalu',
            'header_sekarang' => $period['is_monthly'] ? 'Bulan Ini' : 'Tahun Ini',
            'groups' => $groups,
            'summary' => [
                'operating' => ['prior' => $aPrior, 'current' => $aCur, 'ytd' => $aYtd],
                'non_operating' => ['prior' => $bPrior, 'current' => $bCur, 'ytd' => $bYtd],
                'before_tax' => ['prior' => $beforeTaxPrior, 'current' => $beforeTaxCur, 'ytd' => $beforeTax],
                'tax' => ['prior' => $taxPrior, 'current' => $taxCur, 'ytd' => $taxYtd],
                'after_tax' => ['prior' => $afterTaxPrior, 'current' => $afterTaxCur, 'ytd' => $afterTax],
            ],
        ];
    }

    private function cumulativeSigned(Account $account, $openings, $movements): float
    {
        $opening = $this->balances->movementPair($openings->get((int) $account->row_id));
        $movement = $this->balances->movementPair($movements->get((int) $account->row_id));

        return $this->balances->signedBalance(
            $account,
            $opening['debit'] + $movement['debit'],
            $opening['credit'] + $movement['credit'],
        );
    }

    private function isUnderLevel2(string $code, string $parentCode): bool
    {
        // parent like 4.1.00.00 → prefix 4.1.
        $parts = explode('.', $parentCode);
        if (count($parts) < 2) {
            return str_starts_with($code, $parentCode);
        }
        $prefix = $parts[0].'.'.$parts[1].'.';

        return str_starts_with($code, $prefix) && $code !== $parentCode;
    }

    private function bucket(string $code, string $type): string
    {
        if (str_starts_with($code, '5.4')) {
            return 'tax';
        }
        if ($type === 'revenue') {
            return str_starts_with($code, '4.1') ? 'revenue_ops' : 'revenue_non';
        }

        // expense: 5.1, 5.2 ops; 5.3 non
        if (str_starts_with($code, '5.1') || str_starts_with($code, '5.2')) {
            return 'expense_ops';
        }

        return 'expense_non';
    }
}
