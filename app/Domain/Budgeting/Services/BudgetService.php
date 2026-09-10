<?php

declare(strict_types=1);

namespace App\Domain\Budgeting\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Budgeting\Models\Budget;
use App\Domain\Budgeting\Models\BudgetLine;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BudgetService
{
    /** @var list<string> */
    private const BUDGET_ACCOUNT_TYPES = ['revenue', 'expense'];

    public function ensureForYear(int $year): Budget
    {
        $budget = Budget::query()->where('fiscal_year', $year)->first();
        if ($budget !== null) {
            return $budget;
        }

        return Budget::query()->create([
            'fiscal_year' => $year,
            'name' => "Anggaran {$year}",
            'status' => Budget::STATUS_DRAFT,
        ]);
    }

    /**
     * @return array{
     *     budget: array{row_id:int,fiscal_year:int,name:string,status:string,approved_at:?string},
     *     months: list<array{month:int,line_count:int,revenue:float,expense:float,surplus:float}>
     * }
     */
    public function yearOverview(int $year): array
    {
        $budget = $this->ensureForYear($year);
        $lines = BudgetLine::query()
            ->where('budget_row_id', $budget->row_id)
            ->whereNull('organization_unit_row_id')
            ->with('account:row_id,account_type')
            ->get(['row_id', 'fiscal_month', 'account_row_id', 'amount']);

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthLines = $lines->where('fiscal_month', $month);
            $revenue = (float) $monthLines
                ->filter(fn (BudgetLine $line): bool => $line->account?->account_type === 'revenue')
                ->sum('amount');
            $expense = (float) $monthLines
                ->filter(fn (BudgetLine $line): bool => $line->account?->account_type === 'expense')
                ->sum('amount');

            $months[] = [
                'month' => $month,
                'line_count' => $monthLines->count(),
                'revenue' => $revenue,
                'expense' => $expense,
                'surplus' => $revenue - $expense,
            ];
        }

        return [
            'budget' => $this->budgetPayload($budget),
            'months' => $months,
        ];
    }

    /**
     * @return array{
     *     budget: array{row_id:int,fiscal_year:int,name:string,status:string,approved_at:?string},
     *     month: int,
     *     editable: bool,
     *     has_previous: bool,
     *     groups: list<array{type:string,label:string,accounts:list<array{row_id:int,code:string,name:string,amount:float}>,total:float}>,
     *     totals: array{revenue:float,expense:float,surplus:float}
     * }
     */
    public function monthSheet(int $year, int $month): array
    {
        $this->assertMonth($month);
        $budget = $this->ensureForYear($year);

        $amounts = BudgetLine::query()
            ->where('budget_row_id', $budget->row_id)
            ->where('fiscal_month', $month)
            ->whereNull('organization_unit_row_id')
            ->pluck('amount', 'account_row_id');

        $accounts = $this->budgetableAccounts();
        $groups = [];
        $totals = ['revenue' => 0.0, 'expense' => 0.0, 'surplus' => 0.0];

        foreach (self::BUDGET_ACCOUNT_TYPES as $type) {
            $typed = $accounts->where('account_type', $type)->values();
            $items = [];
            $total = 0.0;
            foreach ($typed as $account) {
                $amount = (float) ($amounts[$account->row_id] ?? 0);
                $total += $amount;
                $items[] = [
                    'row_id' => (int) $account->row_id,
                    'code' => (string) $account->code,
                    'name' => (string) $account->name,
                    'amount' => $amount,
                ];
            }

            $label = $type === 'revenue' ? 'Pendapatan' : 'Beban';
            $groups[] = [
                'type' => $type,
                'label' => $label,
                'accounts' => $items,
                'total' => $total,
            ];
            $totals[$type] = $total;
        }

        $totals['surplus'] = $totals['revenue'] - $totals['expense'];

        $hasPrevious = $month > 1 && BudgetLine::query()
            ->where('budget_row_id', $budget->row_id)
            ->where('fiscal_month', $month - 1)
            ->whereNull('organization_unit_row_id')
            ->exists();

        return [
            'budget' => $this->budgetPayload($budget),
            'month' => $month,
            'editable' => $budget->isEditable(),
            'has_previous' => $hasPrevious,
            'groups' => $groups,
            'totals' => $totals,
        ];
    }

    /**
     * @param  array<int|string, float|int|string|null>  $amounts  keyed by account row_id
     * @return array{imported:int}
     */
    public function saveMonth(int $year, int $month, array $amounts): array
    {
        $this->assertMonth($month);
        $budget = $this->ensureForYear($year);
        $budget->assertEditable();

        $validIds = $this->budgetableAccounts()->pluck('row_id')->map(fn ($id) => (int) $id)->all();
        $validSet = array_fill_keys($validIds, true);

        return DB::connection('tenant')->transaction(function () use ($budget, $month, $amounts, $validSet): array {
            BudgetLine::query()
                ->where('budget_row_id', $budget->row_id)
                ->where('fiscal_month', $month)
                ->whereNull('organization_unit_row_id')
                ->delete();

            $saved = 0;
            foreach ($amounts as $accountRowId => $rawAmount) {
                $accountRowId = (int) $accountRowId;
                if (! isset($validSet[$accountRowId])) {
                    continue;
                }

                $amount = $this->normalizeAmount($rawAmount);
                if ($amount == 0.0) {
                    continue;
                }

                BudgetLine::query()->create([
                    'budget_row_id' => $budget->row_id,
                    'account_row_id' => $accountRowId,
                    'organization_unit_row_id' => null,
                    'fiscal_month' => $month,
                    'amount' => $amount,
                ]);
                $saved++;
            }

            return ['imported' => $saved];
        });
    }

    public function copyFromPreviousMonth(int $year, int $month): int
    {
        $this->assertMonth($month);
        if ($month === 1) {
            throw new DomainException('Bulan Januari tidak punya bulan sebelumnya.');
        }

        $budget = $this->ensureForYear($year);
        $budget->assertEditable();

        $previous = BudgetLine::query()
            ->where('budget_row_id', $budget->row_id)
            ->where('fiscal_month', $month - 1)
            ->whereNull('organization_unit_row_id')
            ->get(['account_row_id', 'amount']);

        if ($previous->isEmpty()) {
            throw new DomainException('Bulan sebelumnya belum punya rencana anggaran.');
        }

        $amounts = [];
        foreach ($previous as $line) {
            $amounts[(int) $line->account_row_id] = (float) $line->amount;
        }

        return $this->saveMonth($year, $month, $amounts)['imported'];
    }

    public function approve(int $year, int $userId): Budget
    {
        $budget = $this->ensureForYear($year);
        if ($budget->isApproved()) {
            return $budget;
        }

        $hasLines = BudgetLine::query()
            ->where('budget_row_id', $budget->row_id)
            ->exists();
        if (! $hasLines) {
            throw new DomainException('Belum ada baris anggaran untuk disetujui.');
        }

        $budget->forceFill([
            'status' => Budget::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $userId,
        ])->save();

        return $budget->refresh();
    }

    public function reopen(int $year): Budget
    {
        $budget = $this->ensureForYear($year);
        if ($budget->isEditable()) {
            return $budget;
        }

        $budget->forceFill([
            'status' => Budget::STATUS_DRAFT,
            'approved_at' => null,
            'approved_by_user_id' => null,
        ])->save();

        return $budget->refresh();
    }

    /**
     * @return Collection<int, Account>
     */
    private function budgetableAccounts(): Collection
    {
        return Account::query()
            ->whereIn('account_type', self::BUDGET_ACCOUNT_TYPES)
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type']);
    }

    private function assertMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            throw new DomainException('Bulan harus antara 1–12.');
        }
    }

    private function normalizeAmount(float|int|string|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return round((float) $value, 2);
    }

    /**
     * @return array{row_id:int,fiscal_year:int,name:string,status:string,approved_at:?string}
     */
    private function budgetPayload(Budget $budget): array
    {
        return [
            'row_id' => (int) $budget->row_id,
            'fiscal_year' => (int) $budget->fiscal_year,
            'name' => (string) $budget->name,
            'status' => (string) $budget->status,
            'approved_at' => $budget->approved_at?->toDateTimeString(),
        ];
    }
}
