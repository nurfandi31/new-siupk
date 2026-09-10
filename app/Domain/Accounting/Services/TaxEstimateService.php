<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Taksiran PPh dari mutasi jurnal posted (bukan salinan saldo legacy).
 *
 * - PPh Final 0.5% dari total pendapatan terpilih
 * - PPh Badan 11% dari laba (pendapatan − beban non-pajak)
 */
final class TaxEstimateService
{
    public const RATE_FINAL = 0.5;

    public const RATE_CORPORATE = 11.0;

    /**
     * @return array{
     *     identity: array{legal_name:string,short_name:?string,tax_number:?string},
     *     year: int,
     *     month: int|null,
     *     period_label: string,
     *     rates: array{final:float,corporate:float},
     *     revenue_accounts: list<array{row_id:int,code:string,name:string,amount:float,selected:bool}>,
     *     expense_total: float,
     *     totals: array{revenue:float,expense:float,profit:float,pph_final:float,pph_corporate:float}
     * }
     */
    public function estimate(int $year, ?int $month, array $selectedAccountIds = []): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new DomainException('Tahun pajak tidak valid.');
        }
        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new DomainException('Masa pajak tidak valid.');
        }

        [$from, $until] = $this->periodBounds($year, $month);
        $movements = $this->movementsByAccount($from, $until);

        $revenueAccounts = Account::query()
            ->where('account_type', 'revenue')
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance']);

        $expenseAccounts = Account::query()
            ->where('account_type', 'expense')
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance']);

        $selectedSet = array_fill_keys(array_map('intval', $selectedAccountIds), true);
        $autoSelect = $selectedAccountIds === [];

        $revenueRows = [];
        $revenueTotal = 0.0;
        foreach ($revenueAccounts as $account) {
            $amount = $this->signedAmount($account, $movements->get((int) $account->row_id));
            $selected = $autoSelect ? $amount != 0.0 : isset($selectedSet[(int) $account->row_id]);
            if ($selected) {
                $revenueTotal += $amount;
            }
            $revenueRows[] = [
                'row_id' => (int) $account->row_id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'amount' => $amount,
                'selected' => $selected,
            ];
        }

        $expenseTotal = 0.0;
        foreach ($expenseAccounts as $account) {
            if ($this->isTaxExpenseAccount((string) $account->code, (string) $account->name)) {
                continue;
            }
            $expenseTotal += $this->signedAmount($account, $movements->get((int) $account->row_id));
        }

        $profit = $revenueTotal - $expenseTotal;
        $pphFinal = round($revenueTotal * (self::RATE_FINAL / 100), 2);
        $pphCorporate = round(max($profit, 0) * (self::RATE_CORPORATE / 100), 2);

        $profile = OrganizationProfile::query()->first();

        return [
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? ''),
                'short_name' => $profile?->short_name,
                'tax_number' => $profile?->tax_number,
            ],
            'year' => $year,
            'month' => $month,
            'period_label' => $this->periodLabel($year, $month),
            'rates' => [
                'final' => self::RATE_FINAL,
                'corporate' => self::RATE_CORPORATE,
            ],
            'revenue_accounts' => $revenueRows,
            'expense_total' => round($expenseTotal, 2),
            'totals' => [
                'revenue' => round($revenueTotal, 2),
                'expense' => round($expenseTotal, 2),
                'profit' => round($profit, 2),
                'pph_final' => $pphFinal,
                'pph_corporate' => $pphCorporate,
            ],
        ];
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function periodBounds(int $year, ?int $month): array
    {
        if ($month === null) {
            $from = CarbonImmutable::create($year, 1, 1)->startOfDay();
            $until = $from->addYear();

            return [$from, $until];
        }

        $from = CarbonImmutable::create($year, $month, 1)->startOfDay();

        return [$from, $from->addMonth()];
    }

    private function periodLabel(int $year, ?int $month): string
    {
        if ($month === null) {
            return "Januari – Desember {$year}";
        }

        $labels = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return ($labels[$month] ?? "Bulan {$month}")." {$year}";
    }

    /**
     * @return Collection<int, object{debit:string,credit:string}>
     */
    private function movementsByAccount(CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $from->toDateString())
            ->where('entries.transaction_date', '<', $until->toDateString())
            ->groupBy('lines.account_row_id')
            ->selectRaw('lines.account_row_id')
            ->selectRaw('CAST(COALESCE(SUM(lines.debit), 0) AS CHAR) AS debit')
            ->selectRaw('CAST(COALESCE(SUM(lines.credit), 0) AS CHAR) AS credit')
            ->get()
            ->keyBy(fn ($row) => (int) $row->account_row_id);
    }

    private function signedAmount(Account $account, ?object $movement): float
    {
        $debit = (float) ($movement->debit ?? 0);
        $credit = (float) ($movement->credit ?? 0);

        // Revenue (C): credit − debit; Expense (D): debit − credit
        if ($account->normal_balance === 'C' || $account->account_type === 'revenue') {
            return round($credit - $debit, 2);
        }

        return round($debit - $credit, 2);
    }

    private function isTaxExpenseAccount(string $code, string $name): bool
    {
        if (str_starts_with($code, '5.4')) {
            return true;
        }

        $needle = mb_strtolower($name);

        return str_contains($needle, 'pph')
            || str_contains($needle, 'pajak penghasilan')
            || str_contains($needle, 'taksiran pajak');
    }
}
