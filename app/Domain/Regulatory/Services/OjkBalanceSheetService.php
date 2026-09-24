<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Neraca OJK — Laporan Posisi Keuangan untuk pelaporan OJK.
 *
 * Laporan regulasi OJK yang menampilkan posisi keuangan lembaga dengan
 * format OJK (Aset / Liabilitas / Ekuitas) sesuai standar pelaporan LKM/BPR.
 * Sumber data utama adalah chart of accounts (asset, liability, equity).
 *
 * Standar OJK mengelompokkan rekening dengan format kode singkat:
 *   - 1xx → Aset
 *   - 2xx → Liabilitas
 *   - 3xx → Ekuitas
 *
 * Jika pada database tidak tersedia tabel `rekening_ojk` (mapping OJK),
 * laporan ini mengikuti format OJK pada level section (A/B/C) yang sesuai
 * dengan struktur neraca standar. Format OJK memiliki label khusus:
 *   - A. Aset (Kas, Piutang, Persediaan, Aset Tetap, Aset Lainnya)
 *   - B. Liabilitas (Liabilitas Lancar, Liabilitas Jangka Panjang)
 *   - C. Ekuitas (Modal, Cadangan, Laba Ditahan, Laba Berjalan)
 *
 * Sub-kategori OJK khusus:
 *   - Kas dan Setara Kas (kode OJK 110)
 *   - Liabilitas Lancar (kode OJK 210)
 */
final class OJKBalanceSheetService
{
    public function __construct(
        private readonly AccountBalanceQuery $balances,
    ) {}

    /**
     * @return array{
     *   period: array<string, mixed>,
     *   identity: array<string, string|null>,
     *   sections: list<array<string, mixed>>,
     *   totals: array<string, float>,
     *   kas_dan_setara_kas: float,
     *   liabilitas_lancar: float,
     *   rasio_likuiditas: float|null,
     *   rasio_solvabilitas: float|null,
     *   net_income: float,
     *   balanced: bool
     * }
     */
    public function build(int $year, ?int $month): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $netIncome = $this->balances->netIncome($asOf);

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

        $postableBalances = [];
        foreach ($accounts->where('is_postable', true) as $account) {
            if ((string) $account->code === AccountBalanceQuery::CURRENT_EARNINGS_CODE) {
                $postableBalances[(int) $account->row_id] = $netIncome;

                continue;
            }
            $postableBalances[(int) $account->row_id] = $this->balances->asOfRaw($account, $asOf)['signed'];
        }

        $byParent = $accounts->groupBy(fn (Account $a) => (int) ($a->parent_row_id ?? 0));

        $sections = [];
        $totalAsset = 0.0;
        $totalCredit = 0.0;
        $kasDanSetaraKas = 0.0;
        $liabilitasLancar = 0.0;

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
                    if (abs($sum) < 0.005 && ! $this->hasCurrentEarningsDescendant($l3, $byParent)) {
                        continue;
                    }
                    $l3Blocks[] = [
                        'code' => (string) $l3->code,
                        'name' => (string) $l3->name,
                        'level' => 3,
                        'balance' => round($sum, 2),
                    ];
                    $l1Sum += $sum;

                    // OJK subtotals — heuristic berbasis kode
                    if ((string) $l1->code === '1' && str_starts_with((string) $l3->code, '1.1.01')) {
                        $kasDanSetaraKas += $sum;
                    }
                    if ((string) $l1->code === '2' && str_starts_with((string) $l3->code, '2.1')) {
                        $liabilitasLancar += $sum;
                    }
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

            // Label OJK per section
            $ojkLabel = match ((string) $l1->code) {
                '1' => 'Aset',
                '2' => 'Liabilitas',
                '3' => 'Ekuitas',
                default => (string) $l1->name,
            };

            $sections[] = [
                'code' => (string) $l1->code,
                'name' => (string) $l1->name,
                'ojk_label' => $ojkLabel,
                'letter' => (string) $l1->code === '1' ? 'A' : ((string) $l1->code === '2' ? 'B' : 'C'),
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

        // Ensure current earnings reflected even if 3.2.02.01 missing
        $hasEarnings = $accounts->contains(fn (Account $a) => (string) $a->code === AccountBalanceQuery::CURRENT_EARNINGS_CODE);
        if (! $hasEarnings && abs($netIncome) >= 0.005) {
            $totalCredit += $netIncome;
            $sections[] = [
                'code' => '3',
                'name' => 'Ekuitas (Laba Berjalan)',
                'ojk_label' => 'Ekuitas',
                'letter' => 'C',
                'level' => 1,
                'account_type' => 'equity',
                'balance' => round($netIncome, 2),
                'children' => [[
                    'code' => '3.2',
                    'name' => 'Laba Rugi',
                    'level' => 2,
                    'children' => [[
                        'code' => AccountBalanceQuery::CURRENT_EARNINGS_CODE,
                        'name' => 'Laba/Rugi Tahun Berjalan',
                        'level' => 3,
                        'balance' => round($netIncome, 2),
                    ]],
                ]],
            ];
        }

        $profile = OrganizationProfile::query()->first();

        $rasioLikuiditas = $liabilitasLancar > 0
            ? round(($kasDanSetaraKas / $liabilitasLancar) * 100, 2)
            : null;
        $rasioSolvabilitas = $totalCredit - ($totalCredit - (($totalAsset - $totalCredit) * 0)) > 0
            ? null
            : null;
        // Solvabilitas = Aset / Liabilitas
        $liabilitasTotal = 0.0;
        foreach ($sections as $s) {
            if ($s['account_type'] === 'liability') {
                $liabilitasTotal += $s['balance'];
            }
        }
        $rasioSolvabilitas = $liabilitasTotal > 0
            ? round(($totalAsset / $liabilitasTotal) * 100, 2)
            : null;

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
            ],
            'sections' => $sections,
            'totals' => [
                'assets' => round($totalAsset, 2),
                'liabilities_equity' => round($totalCredit, 2),
                'net_income' => round($netIncome, 2),
                'liabilities' => round($liabilitasTotal, 2),
            ],
            'kas_dan_setara_kas' => round($kasDanSetaraKas, 2),
            'liabilitas_lancar' => round($liabilitasLancar, 2),
            'rasio_likuiditas' => $rasioLikuiditas,
            'rasio_solvabilitas' => $rasioSolvabilitas,
            'net_income' => round($netIncome, 2),
            'balanced' => abs($totalAsset - $totalCredit) < 0.02,
        ];
    }

    /**
     * @param  Collection<int, Collection<int, Account>>  $byParent
     * @param  array<int, float>  $postableBalances
     */
    private function sumDescendants(Account $node, Collection $byParent, array $postableBalances): float
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

    /**
     * @param  Collection<int, Collection<int, Account>>  $byParent
     */
    private function hasCurrentEarningsDescendant(Account $node, Collection $byParent): bool
    {
        if ((string) $node->code === AccountBalanceQuery::CURRENT_EARNINGS_CODE) {
            return true;
        }
        foreach ($byParent->get((int) $node->row_id, collect()) as $child) {
            if ($this->hasCurrentEarningsDescendant($child, $byParent)) {
                return true;
            }
        }

        return false;
    }
}
