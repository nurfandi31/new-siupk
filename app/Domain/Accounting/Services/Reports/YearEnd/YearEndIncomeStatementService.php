<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports\YearEnd;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;

/**
 * Preview Laba Rugi Tutup Buku.
 *
 * Setelah jurnal tutup buku diposting, akun nominal (pendapatan & beban)
 * bersaldo 0. Laporan ini menampilkan ringkasan AKUMULASI akun nominal
 * selama tahun buku — sumber kebenaran tetap saldo YTD per akun,
 * sebelum jurnal tutup buku diposting.
 *
 * Logika: laporan ini adalah ringkasan akhir tahun, akun nominal
 * masih menampilkan saldo YTD (sebelum tutup buku). Karena di neraca
 * tutup buku saldo akun nominal = 0, kita perlu tampilkan versi
 * "pra-close" sebagai ringkasan akhir tahun.
 */
final readonly class YearEndIncomeStatementService
{
    public function __construct(
        private AccountBalanceQuery $balances,
    ) {}

    /**
     * Bangun preview laba rugi tutup buku.
     *
     * @return array<string, mixed>
     */
    public function build(int $year): array
    {
        $period = $this->balances->resolvePeriod($year, null);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();

        $openings = $this->balances->openings($year);
        $ytdMovements = $this->balances->movements($yearStart, $asOf->addDay()->startOfDay());

        // Akun nominal (pendapatan & beban) — postable only
        $nominalAccounts = Account::query()
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance', 'level', 'parent_row_id']);

        // Parent level-2 untuk grouping
        $level2Parents = Account::query()
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('level', 2)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type']);

        $groups = [];
        foreach ($level2Parents as $parent) {
            $children = [];
            $sumYtd = 0.0;

            foreach ($nominalAccounts as $account) {
                if (! $this->isUnderLevel2((string) $account->code, (string) $parent->code)) {
                    continue;
                }

                $ytd = $this->cumulativeSigned($account, $openings, $ytdMovements);
                if (abs($ytd) < 0.005) {
                    continue;
                }

                $children[] = [
                    'row_id' => (int) $account->row_id,
                    'code' => (string) $account->code,
                    'name' => (string) $account->name,
                    'ytd' => round($ytd, 2),
                ];
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
                'ytd' => round($sumYtd, 2),
            ];
        }

        $sum = fn (string $bucket): float => round(
            array_sum(array_map(
                fn (array $g) => $g['bucket'] === $bucket ? $g['ytd'] : 0.0,
                $groups,
            )),
            2,
        );

        $revOps = $sum('revenue_ops');
        $expOps = $sum('expense_ops');
        $revNon = $sum('revenue_non');
        $expNon = $sum('expense_non');
        $tax = $sum('tax');

        $operating = round($revOps - $expOps, 2);
        $nonOperating = round($revNon - $expNon, 2);
        $beforeTax = round($operating + $nonOperating, 2);
        $afterTax = round($beforeTax - $tax, 2);

        $totalRevenue = round($revOps + $revNon, 2);
        $totalExpense = round($expOps + $expNon + $tax, 2);

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
            'groups' => $groups,
            'summary' => [
                'revenue_ops' => $revOps,
                'expense_ops' => $expOps,
                'operating' => $operating,
                'revenue_non' => $revNon,
                'expense_non' => $expNon,
                'non_operating' => $nonOperating,
                'before_tax' => $beforeTax,
                'tax' => $tax,
                'after_tax' => $afterTax,
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
            ],
            'kpi' => [
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
                'surplus_deficit' => $afterTax,
                'margin_pct' => $totalRevenue > 0
                    ? round(($afterTax / $totalRevenue) * 100.0, 2)
                    : 0.0,
            ],
            'notes' => 'Akun nominal akan bersaldo 0 setelah jurnal tutup buku diposting. Laporan ini menampilkan ringkasan akhir tahun sebelum tutup buku.',
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

        if (str_starts_with($code, '5.1') || str_starts_with($code, '5.2')) {
            return 'expense_ops';
        }

        return 'expense_non';
    }
}
