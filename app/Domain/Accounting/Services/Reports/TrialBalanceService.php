<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;

final readonly class TrialBalanceService
{
    public function __construct(
        private AccountBalanceQuery $balances,
    ) {}

    /**
     * @return array{
     *   period: array<string, mixed>,
     *   identity: array{legal_name: string, short_name: ?string},
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float>,
     *   balanced: bool
     * }
     */
    public function build(int $year, ?int $month, bool $includeZero = false): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $mutatedAccountIds = Account::query()
            ->from('accounts as mutation_accounts')
            ->join('journal_lines as lines', 'lines.account_row_id', '=', 'mutation_accounts.row_id')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '<', $asOf->addDay()->toDateString())
            ->select('mutation_accounts.row_id');

        $accounts = Account::query()
            ->where('is_postable', true)
            ->where(function ($q) use ($asOf): void {
                $q->where('is_active', true)
                    ->orWhereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>', $asOf->toDateString());
            })
            ->where(function ($q) use ($asOf, $mutatedAccountIds): void {
                $q->whereDate('created_at', '<=', $asOf->toDateString())
                    ->orWhereIn('row_id', $mutatedAccountIds);
            })
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance', 'level']);

        $rows = [];
        $totals = [
            'ns_debit' => 0.0,
            'ns_credit' => 0.0,
            'lr_debit' => 0.0,
            'lr_credit' => 0.0,
            'bs_debit' => 0.0,
            'bs_credit' => 0.0,
        ];

        foreach ($accounts as $account) {
            $raw = $this->balances->asOfRaw($account, $asOf);
            $signed = $raw['signed'];
            if (! $includeZero && abs($signed) < 0.005) {
                continue;
            }

            $cols = $this->balances->splitToColumns($account, $signed);
            $isBs = $this->balances->isBalanceSheetType((string) $account->account_type);

            $nsDebit = $cols['debit'];
            $nsCredit = $cols['credit'];
            $lrDebit = 0.0;
            $lrCredit = 0.0;
            $bsDebit = 0.0;
            $bsCredit = 0.0;

            if ($isBs) {
                $bsDebit = $nsDebit;
                $bsCredit = $nsCredit;
            } else {
                $lrDebit = $nsDebit;
                $lrCredit = $nsCredit;
            }

            $rows[] = [
                'row_id' => (int) $account->row_id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'account_type' => (string) $account->account_type,
                'ns_debit' => $nsDebit,
                'ns_credit' => $nsCredit,
                'lr_debit' => $lrDebit,
                'lr_credit' => $lrCredit,
                'bs_debit' => $bsDebit,
                'bs_credit' => $bsCredit,
                'signed' => $signed,
            ];

            $totals['ns_debit'] += $nsDebit;
            $totals['ns_credit'] += $nsCredit;
            $totals['lr_debit'] += $lrDebit;
            $totals['lr_credit'] += $lrCredit;
            $totals['bs_debit'] += $bsDebit;
            $totals['bs_credit'] += $bsCredit;
        }

        // Surplus/defisit plug: LR net → equity side of balance sheet (legacy NS footer)
        $lrNet = round($totals['lr_credit'] - $totals['lr_debit'], 2);
        if (abs($lrNet) >= 0.005) {
            if ($lrNet >= 0) {
                $totals['lr_debit'] = round($totals['lr_debit'] + $lrNet, 2);
                $totals['bs_credit'] = round($totals['bs_credit'] + $lrNet, 2);
            } else {
                $surplus = abs($lrNet);
                $totals['lr_credit'] = round($totals['lr_credit'] + $surplus, 2);
                $totals['bs_debit'] = round($totals['bs_debit'] + $surplus, 2);
            }
        }

        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        $profile = OrganizationProfile::query()->first();

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? ''),
                'short_name' => $profile?->short_name,
            ],
            'rows' => $rows,
            'totals' => $totals,
            'net_income' => round($this->balances->netIncome($asOf), 2),
            'balanced' => abs($totals['ns_debit'] - $totals['ns_credit']) < 0.02
                && abs($totals['bs_debit'] - $totals['bs_credit']) < 0.02
                && abs($totals['lr_debit'] - $totals['lr_credit']) < 0.02,
        ];
    }
}
