<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class MonthlyBalanceRecalculator
{
    public function __construct(
        private TenantContext $context,
    ) {}

    public function recalculate(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be between 1 and 12.');
        }

        $tenantId = $this->context->id();
        $connection = DB::connection((string) config('tenancy.tenant_connection', 'tenant'));
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->addMonth();
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $updated = 0;

        $connection->transaction(function (ConnectionInterface $db) use (
            $tenantId,
            $year,
            $month,
            $periodStart,
            $periodEnd,
            $yearStart,
            &$updated,
        ): void {
            $accounts = $db->table('accounts')
                ->where('tenant_id', $tenantId)
                ->where('is_postable', true)
                ->orderBy('row_id')
                ->get(['row_id']);

            foreach ($accounts as $account) {
                $opening = $db->table('account_opening_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('account_row_id', $account->row_id)
                    ->where('fiscal_year', $year)
                    ->first(['debit', 'credit']);

                $previous = $this->movement(
                    $db,
                    $tenantId,
                    (int) $account->row_id,
                    $yearStart,
                    $periodStart,
                );

                $current = $this->movement(
                    $db,
                    $tenantId,
                    (int) $account->row_id,
                    $periodStart,
                    $periodEnd,
                );

                $openingDebit = bcadd((string) ($opening->debit ?? '0.00'), $previous['debit'], 2);
                $openingCredit = bcadd((string) ($opening->credit ?? '0.00'), $previous['credit'], 2);
                $closingDebit = bcadd($openingDebit, $current['debit'], 2);
                $closingCredit = bcadd($openingCredit, $current['credit'], 2);

                $db->table('account_monthly_balances')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'account_row_id' => $account->row_id,
                        'fiscal_year' => $year,
                        'fiscal_month' => $month,
                    ],
                    [
                        'opening_debit' => $openingDebit,
                        'opening_credit' => $openingCredit,
                        'movement_debit' => $current['debit'],
                        'movement_credit' => $current['credit'],
                        'closing_debit' => $closingDebit,
                        'closing_credit' => $closingCredit,
                        'recalculated_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $updated++;
            }
        }, 3);

        return $updated;
    }

    /**
     * @return array{debit:string,credit:string}
     */
    private function movement(
        ConnectionInterface $db,
        int $tenantId,
        int $accountRowId,
        \DateTimeInterface $from,
        \DateTimeInterface $until,
    ): array {
        $row = $db->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('lines.account_row_id', $accountRowId)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $from->format('Y-m-d'))
            ->where('entries.transaction_date', '<', $until->format('Y-m-d'))
            ->selectRaw('CAST(COALESCE(SUM(lines.debit), 0) AS CHAR) AS debit_total')
            ->selectRaw('CAST(COALESCE(SUM(lines.credit), 0) AS CHAR) AS credit_total')
            ->first();

        return [
            'debit' => (string) ($row->debit_total ?? '0.00'),
            'credit' => (string) ($row->credit_total ?? '0.00'),
        ];
    }
}
