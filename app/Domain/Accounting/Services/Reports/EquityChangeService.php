<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;

/**
 * Laporan Perubahan Ekuitas — lebih informatif dari legacy LPM
 * (legacy hanya list saldo akun modal; Next: opening → mutasi → laba → closing).
 */
final class EquityChangeService
{
    public function __construct(
        private readonly AccountBalanceQuery $balances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $from = CarbonImmutable::parse($period['from'])->startOfDay();
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();

        $equityMutationAccountIds = Account::query()
            ->from('accounts as equity_mutations')
            ->join('journal_lines as lines', 'lines.account_row_id', '=', 'equity_mutations.row_id')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '<', $asOf->addDay()->toDateString())
            ->select('equity_mutations.row_id');

        $equityAccounts = Account::query()
            ->where('account_type', 'equity')
            ->where('is_postable', true)
            ->where(function ($q) use ($from, $asOf, $equityMutationAccountIds): void {
                $q->where(function ($relevant) use ($from, $asOf): void {
                    $relevant->whereDate('created_at', '<=', $asOf->toDateString())
                        ->where(function ($active) use ($from): void {
                            $active->where('is_active', true)
                                ->orWhereNull('deactivated_at')
                                ->orWhere('deactivated_at', '>=', $from->toDateString());
                        });
                })->orWhereIn('row_id', $equityMutationAccountIds);
            })
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance']);

        $openingRows = [];
        $closingRows = [];
        $movementRows = [];
        $openingTotal = 0.0;
        $closingTotal = 0.0;
        $movementTotal = 0.0;
        $earningsOpening = 0.0;
        $earningsClosing = 0.0;

        foreach ($equityAccounts as $account) {
            $code = (string) $account->code;
            $isEarnings = $code === AccountBalanceQuery::CURRENT_EARNINGS_CODE;

            $openBal = $this->balanceAtStart($account, $from, $yearStart, $year);
            $closeBal = $isEarnings
                ? $this->balances->netIncome($asOf)
                : $this->balances->asOfRaw($account, $asOf)['signed'];
            $move = round($closeBal - $openBal, 2);

            if ($isEarnings) {
                $earningsOpening = $openBal;
                $earningsClosing = $closeBal;
            }

            if (abs($openBal) < 0.005 && abs($closeBal) < 0.005 && abs($move) < 0.005) {
                continue;
            }

            $row = [
                'row_id' => (int) $account->row_id,
                'code' => $code,
                'name' => (string) $account->name,
                'is_earnings' => $isEarnings,
                'opening' => round($openBal, 2),
                'movement' => $move,
                'closing' => round($closeBal, 2),
            ];

            $openingRows[] = $row;
            $closingRows[] = $row;
            if (abs($move) >= 0.005) {
                $movementRows[] = $row;
            }

            $openingTotal = round($openingTotal + $openBal, 2);
            $closingTotal = round($closingTotal + $closeBal, 2);
            $movementTotal = round($movementTotal + $move, 2);
        }

        // If earnings account missing from COA, still plug net income into closing.
        $hasEarnings = $equityAccounts->contains(
            fn (Account $a) => (string) $a->code === AccountBalanceQuery::CURRENT_EARNINGS_CODE,
        );
        if (! $hasEarnings) {
            $ni = $this->balances->netIncome($asOf);
            $priorNi = $from->equalTo($yearStart)
                ? 0.0
                : $this->balances->netIncome($from->subDay());
            $move = round($ni - $priorNi, 2);
            $row = [
                'row_id' => 0,
                'code' => AccountBalanceQuery::CURRENT_EARNINGS_CODE,
                'name' => 'Laba/Rugi Tahun Berjalan',
                'is_earnings' => true,
                'opening' => round($priorNi, 2),
                'movement' => $move,
                'closing' => round($ni, 2),
            ];
            $openingRows[] = $row;
            $closingRows[] = $row;
            if (abs($move) >= 0.005) {
                $movementRows[] = $row;
            }
            $openingTotal = round($openingTotal + $priorNi, 2);
            $closingTotal = round($closingTotal + $ni, 2);
            $movementTotal = round($movementTotal + $move, 2);
            $earningsOpening = $priorNi;
            $earningsClosing = $ni;
        }

        $periodNetIncome = round($earningsClosing - $earningsOpening, 2);
        // Non-earnings equity movement = capital inject / withdraw / reclass
        $otherEquityMove = round($movementTotal - $periodNetIncome, 2);

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
            ],
            'rows' => $openingRows,
            'summary' => [
                'opening_total' => $openingTotal,
                'period_net_income' => $periodNetIncome,
                'other_equity_movement' => $otherEquityMove,
                'movement_total' => $movementTotal,
                'closing_total' => $closingTotal,
            ],
            'bridge' => [
                ['key' => 'opening', 'label' => 'Ekuitas awal periode', 'amount' => $openingTotal],
                ['key' => 'income', 'label' => 'Laba (rugi) periode berjalan', 'amount' => $periodNetIncome],
                ['key' => 'other', 'label' => 'Mutasi ekuitas lain (modal/setoran/penyesuaian)', 'amount' => $otherEquityMove],
                ['key' => 'closing', 'label' => 'Ekuitas akhir periode', 'amount' => $closingTotal],
            ],
        ];
    }

    private function balanceAtStart(
        Account $account,
        CarbonImmutable $from,
        CarbonImmutable $yearStart,
        int $year,
    ): float {
        $code = (string) $account->code;
        if ($code === AccountBalanceQuery::CURRENT_EARNINGS_CODE) {
            if ($from->equalTo($yearStart) || $from->lessThanOrEqualTo($yearStart)) {
                return 0.0;
            }

            return $this->balances->netIncome($from->subDay());
        }

        if ($from->equalTo($yearStart) || $from->lessThanOrEqualTo($yearStart)) {
            $pair = $this->balances->movementPair(
                $this->balances->openings($year)->get((int) $account->row_id),
            );

            return $this->balances->signedBalance($account, $pair['debit'], $pair['credit']);
        }

        return $this->balances->asOfRaw($account, $from->subDay())['signed'];
    }
}
