<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports\YearEnd;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Preview Neraca Tutup Buku — Neraca posisi per akhir tahun buku
 * SETELAH jurnal tutup buku diposting (simulasi).
 *
 * Asumsi simulasi:
 *   - Semua akun nominal (pendapatan 4.x, beban 5.x) sudah ditutup → saldo = 0
 *   - Laba tahun berjalan (3.2.02.01) sudah direklas ke Laba Ditahan (3.2.01.01)
 *
 * Dengan demikian neraca tutup buku ≡ neraca biasa dengan akun nominal
 * di-nolkan dan laba berjalan dipindah ke retained earnings.
 */
final readonly class YearEndBalanceSheetService
{
    public function __construct(
        private AccountBalanceQuery $balances,
    ) {}

    /**
     * Bangun preview neraca tutup buku.
     *
     * @return array<string, mixed>
     */
    public function build(int $year): array
    {
        $period = $this->balances->resolvePeriod($year, null);
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
            ->whereIn('account_type', ['asset', 'liability', 'equity'])
            ->where(function ($q) use ($asOf, $mutatedAccountIds): void {
                $q->whereDate('created_at', '<=', $asOf->toDateString())
                    ->orWhereIn('row_id', $mutatedAccountIds);
            })
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance', 'level', 'parent_row_id', 'is_postable']);

        // Akun retained (3.2.01.01) ditambah surplus tahun ini (karena laba tahun berjalan sudah dipindah)
        $netIncome = $this->balances->netIncome($asOf);
        $retainedCode = ClosingJournalService::RETAINED_CODE;
        $earningsCode = AccountBalanceQuery::CURRENT_EARNINGS_CODE;

        $postableBalances = [];
        foreach ($accounts->where('is_postable', true) as $account) {
            $code = (string) $account->code;
            if ($code === $earningsCode) {
                // Laba berjalan di-nolkan karena sudah direklas
                $postableBalances[(int) $account->row_id] = 0.0;

                continue;
            }
            $postableBalances[(int) $account->row_id] = $this->balances->asOfRaw($account, $asOf)['signed'];
        }

        // Tambahkan surplus ke retained earnings (3.2.01.01)
        $retainedAccount = Account::query()
            ->where('code', $retainedCode)
            ->where('is_postable', true)
            ->first();
        if ($retainedAccount !== null) {
            $postableBalances[(int) $retainedAccount->row_id] = round(
                $postableBalances[(int) $retainedAccount->row_id] ?? 0.0,
                2,
            );
        }

        // Map children for rollup
        $byParent = $accounts->groupBy(fn (Account $a) => (int) ($a->parent_row_id ?? 0));

        $sections = [];
        $totalAsset = 0.0;
        $totalCredit = 0.0;

        $level1 = $accounts->where('level', 1)->values();
        foreach ($level1 as $l1) {
            $l1Sum = 0.0;
            $l2Blocks = [];
            $l2s = $byParent->get((int) $l1->row_id, collect())->where('level', 2)->values();

            foreach ($l2s as $l2) {
                $l3Blocks = [];
                $l3s = $byParent->get((int) $l2->row_id, collect())->where('level', 3)->values();

                foreach ($l3s as $l3) {
                    $sum = $this->sumDescendants($l3, $byParent, $postableBalances);
                    if (abs($sum) < 0.005) {
                        continue;
                    }
                    $l3Blocks[] = [
                        'code' => (string) $l3->code,
                        'name' => (string) $l3->name,
                        'level' => 3,
                        'balance' => round($sum, 2),
                    ];
                    $l1Sum += $sum;
                }

                if ($l3Blocks === []) {
                    continue;
                }

                $l2Blocks[] = [
                    'code' => (string) $l2->code,
                    'name' => (string) $l2->name,
                    'level' => 2,
                    'children' => $l3Blocks,
                ];
            }

            if ($l2Blocks === []) {
                continue;
            }

            $sections[] = [
                'code' => (string) $l1->code,
                'name' => (string) $l1->name,
                'level' => 1,
                'account_type' => (string) $l1->account_type,
                'balance' => round($l1Sum, 2),
                'children' => $l2Blocks,
            ];

            if ($l1->account_type === 'asset') {
                $totalAsset += $l1Sum;
            } else {
                $totalCredit += $l1Sum;
            }
        }

        // Tambahkan Laba Ditahan (3.2.01.01) ke total ekuitas jika belum ada di tree
        $hasRetained = $accounts->contains(fn (Account $a) => (string) $a->code === $retainedCode);
        $retainedAmount = 0.0;
        if ($retainedAccount !== null) {
            $retainedAmount = round($postableBalances[(int) $retainedAccount->row_id] ?? 0.0, 2);
        }
        $retainedBalance = round($retainedAmount + $netIncome, 2);

        if (! $hasRetained && abs($retainedBalance) >= 0.005) {
            $totalCredit += $retainedBalance;
            $sections[] = [
                'code' => '3.2.01',
                'name' => 'Laba Ditahan (setelah tutup buku)',
                'level' => 1,
                'account_type' => 'equity',
                'balance' => $retainedBalance,
                'children' => [[
                    'code' => $retainedCode,
                    'name' => 'Laba Ditahan',
                    'level' => 2,
                    'children' => [[
                        'code' => $retainedCode.'.01',
                        'name' => 'Saldo Laba Ditahan '.$year,
                        'level' => 3,
                        'balance' => $retainedBalance,
                    ]],
                ]],
            ];
        } elseif ($hasRetained && abs($netIncome) >= 0.005) {
            // Update existing retained section to include current year earnings
            foreach ($sections as &$section) {
                if ($section['account_type'] === 'equity') {
                    // Subtract the original retained (without NI) and add the new one
                    $originalRetained = $retainedAmount;
                    $section['balance'] = round($section['balance'] - $originalRetained + $retainedBalance, 2);
                    $totalCredit = round($totalCredit - $originalRetained + $retainedBalance, 2);

                    // Propagate update to L3 rows holding the retained account
                    if (isset($section['children']) && is_array($section['children'])) {
                        foreach ($section['children'] as &$l2) {
                            if (! isset($l2['children']) || ! is_array($l2['children'])) {
                                continue;
                            }
                            foreach ($l2['children'] as &$l3) {
                                if (($l3['code'] ?? '') === $retainedCode) {
                                    $l3['balance'] = $retainedBalance;
                                }
                            }
                            unset($l3);
                        }
                        unset($l2);
                    }
                    break;
                }
            }
            unset($section);
        }

        $profile = OrganizationProfile::query()->first();

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
            'as_of' => $asOf->toDateString(),
            'sections' => $sections,
            'totals' => [
                'assets' => round($totalAsset, 2),
                'liabilities_equity' => round($totalCredit, 2),
                'net_income' => round($netIncome, 2),
                'retained_balance' => $retainedBalance,
            ],
            'balanced' => abs($totalAsset - $totalCredit) < 0.02,
        ];
    }

    /**
     * @param  Collection<int, Collection<int, Account>>  $byParent
     * @param  array<int, float>  $postableBalances
     */
    private function sumDescendants(Account $node, $byParent, array $postableBalances): float
    {
        if ($node->is_postable) {
            return round($postableBalances[(int) $node->row_id] ?? 0.0, 2);
        }

        $sum = 0.0;
        foreach ($byParent->get((int) $node->row_id, collect()) as $child) {
            $sum += $this->sumDescendants($child, $byParent, $postableBalances);
        }

        return round($sum, 2);
    }
}
