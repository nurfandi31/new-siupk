<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports\YearEnd;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;

/**
 * Preview/Simulasi Jurnal Tutup Buku.
 *
 * Generate jurnal yang AKAN dihasilkan pada proses tutup buku tahun fiskal.
 * Logika double-entry mengikuti standar akuntansi:
 *   1) Tutup akun pendapatan ke Ikhtisar Laba Rugi:
 *      Dr Pendapatan (4.x) → Cr Ikhtisar LR (3.6.01)
 *   2) Tutup akun beban dari Ikhtisar Laba Rugi:
 *      Dr Ikhtisar LR (3.6.01) → Cr Beban (5.x)
 *   3) Saldo Ikhtisar LR dipindah ke Laba Ditahan:
 *      Dr Ikhtisar LR (3.6.01) → Cr Laba Ditahan (3.2.01.01)  [jika surplus]
 *      Dr Laba Ditahan (3.2.01.01) → Cr Ikhtisar LR (3.6.01)  [jika defisit]
 *
 * Karena ini simulasi (tidak benar-benar memposting jurnal), setiap
 * perhitungan didasarkan pada saldo akun nominal (revenue/expense) yang
 * sudah terakumulasi sampai akhir tahun buku.
 */
final readonly class ClosingJournalService
{
    /** Akun ikhtisar laba rugi — konvensional SAK. */
    public const SUMMARY_ACCOUNT_CODE = '3.6.01';

    /** Akun laba ditahan (retained earnings). */
    public const RETAINED_CODE = '3.2.01.01';

    /** Akun laba tahun berjalan (current earnings). */
    public const EARNINGS_CODE = AccountBalanceQuery::CURRENT_EARNINGS_CODE;

    public function __construct(
        private AccountBalanceQuery $balances,
    ) {}

    /**
     * Bangun preview jurnal tutup buku.
     *
     * @return array<string, mixed>
     */
    public function build(int $year): array
    {
        $period = $this->balances->resolvePeriod($year, null);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();

        $openings = $this->balances->openings($year);
        $movements = $this->balances->movements($yearStart, $asOf->addDay()->startOfDay());

        // Akun nominal: pendapatan & beban
        $nominalAccounts = Account::query()
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance']);

        $revenueRows = [];
        $expenseRows = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($nominalAccounts as $account) {
            $opening = $this->balances->movementPair($openings->get((int) $account->row_id));
            $movement = $this->balances->movementPair($movements->get((int) $account->row_id));
            $debit = round($opening['debit'] + $movement['debit'], 2);
            $credit = round($opening['credit'] + $movement['credit'], 2);

            // Untuk akun pendapatan (C-normal), saldo di kolom Kredit.
            // Untuk akun beban (D-normal), saldo di kolom Debit.
            if ($account->account_type === 'revenue') {
                $balance = round($credit - $debit, 2);
                if (abs($balance) < 0.005) {
                    continue;
                }
                $revenueRows[] = [
                    'row_id' => (int) $account->row_id,
                    'code' => (string) $account->code,
                    'name' => (string) $account->name,
                    'account_type' => 'revenue',
                    'normal_balance' => 'C',
                    'balance' => $balance,
                    'debit' => $balance, // Untuk menutup, debit pendapatan
                    'credit' => 0.0,
                ];
                $totalRevenue += $balance;
            } else {
                $balance = round($debit - $credit, 2);
                if (abs($balance) < 0.005) {
                    continue;
                }
                $expenseRows[] = [
                    'row_id' => (int) $account->row_id,
                    'code' => (string) $account->code,
                    'name' => (string) $account->name,
                    'account_type' => 'expense',
                    'normal_balance' => 'D',
                    'balance' => $balance,
                    'debit' => 0.0,
                    'credit' => $balance, // Untuk menutup, kredit beban
                ];
                $totalExpense += $balance;
            }
        }

        $totalRevenue = round($totalRevenue, 2);
        $totalExpense = round($totalExpense, 2);

        // Surplus / Defisit = Pendapatan - Beban
        $surplus = round($totalRevenue - $totalExpense, 2);

        // Bangun jurnal baris per baris (satu entry book berisi semua baris)
        $entries = [];

        // === Entry 1: Tutup Pendapatan ke Ikhtisar LR ===
        // Dr Pendapatan  /  Cr Ikhtisar LR
        $entry1Lines = [];
        foreach ($revenueRows as $rev) {
            $entry1Lines[] = [
                'no' => count($entry1Lines) + 1,
                'account_code' => $rev['code'],
                'account_name' => $rev['name'],
                'debit' => $rev['debit'],
                'credit' => 0.0,
                'memo' => 'Penutupan akun pendapatan ke Ikhtisar Laba Rugi',
            ];
        }
        if (abs($totalRevenue) >= 0.005) {
            $entry1Lines[] = [
                'no' => count($entry1Lines) + 1,
                'account_code' => self::SUMMARY_ACCOUNT_CODE,
                'account_name' => 'Ikhtisar Laba Rugi',
                'debit' => 0.0,
                'credit' => $totalRevenue,
                'memo' => 'Penutupan akun pendapatan ke Ikhtisar Laba Rugi',
            ];
        }

        $entries[] = [
            'entry_no' => 1,
            'date' => $asOf->toDateString(),
            'description' => 'Tutup Pendapatan ke Ikhtisar Laba Rugi',
            'reference' => sprintf('CLOSING/%04d/01', $year),
            'lines' => $entry1Lines,
            'total_debit' => round(array_sum(array_column($entry1Lines, 'debit')), 2),
            'total_credit' => round(array_sum(array_column($entry1Lines, 'credit')), 2),
        ];

        // === Entry 2: Tutup Beban dari Ikhtisar LR ===
        // Dr Ikhtisar LR  /  Cr Beban
        $entry2Lines = [];
        if (abs($totalExpense) >= 0.005) {
            $entry2Lines[] = [
                'no' => 1,
                'account_code' => self::SUMMARY_ACCOUNT_CODE,
                'account_name' => 'Ikhtisar Laba Rugi',
                'debit' => $totalExpense,
                'credit' => 0.0,
                'memo' => 'Penutupan akun beban dari Ikhtisar Laba Rugi',
            ];
        }
        foreach ($expenseRows as $exp) {
            $entry2Lines[] = [
                'no' => count($entry2Lines) + 1,
                'account_code' => $exp['code'],
                'account_name' => $exp['name'],
                'debit' => 0.0,
                'credit' => $exp['credit'],
                'memo' => 'Penutupan akun beban dari Ikhtisar Laba Rugi',
            ];
        }

        $entries[] = [
            'entry_no' => 2,
            'date' => $asOf->toDateString(),
            'description' => 'Tutup Beban dari Ikhtisar Laba Rugi',
            'reference' => sprintf('CLOSING/%04d/02', $year),
            'lines' => $entry2Lines,
            'total_debit' => round(array_sum(array_column($entry2Lines, 'debit')), 2),
            'total_credit' => round(array_sum(array_column($entry2Lines, 'credit')), 2),
        ];

        // === Entry 3: Pindahkan Ikhtisar LR ke Laba Ditahan ===
        // Surplus  → Dr Ikhtisar LR, Cr Laba Ditahan
        // Defisit  → Dr Laba Ditahan, Cr Ikhtisar LR
        $entry3Lines = [];
        if (abs($surplus) >= 0.005) {
            if ($surplus > 0) {
                $entry3Lines = [
                    [
                        'no' => 1,
                        'account_code' => self::SUMMARY_ACCOUNT_CODE,
                        'account_name' => 'Ikhtisar Laba Rugi',
                        'debit' => $surplus,
                        'credit' => 0.0,
                        'memo' => 'Pemindahan surplus tahun '.$year.' ke Laba Ditahan',
                    ],
                    [
                        'no' => 2,
                        'account_code' => self::RETAINED_CODE,
                        'account_name' => 'Laba Ditahan',
                        'debit' => 0.0,
                        'credit' => $surplus,
                        'memo' => 'Pemindahan surplus tahun '.$year.' ke Laba Ditahan',
                    ],
                ];
            } else {
                $def = abs($surplus);
                $entry3Lines = [
                    [
                        'no' => 1,
                        'account_code' => self::RETAINED_CODE,
                        'account_name' => 'Laba Ditahan',
                        'debit' => $def,
                        'credit' => 0.0,
                        'memo' => 'Pemindahan defisit tahun '.$year.' dari Laba Ditahan',
                    ],
                    [
                        'no' => 2,
                        'account_code' => self::SUMMARY_ACCOUNT_CODE,
                        'account_name' => 'Ikhtisar Laba Rugi',
                        'debit' => 0.0,
                        'credit' => $def,
                        'memo' => 'Pemindahan defisit tahun '.$year.' dari Laba Ditahan',
                    ],
                ];
            }
        }

        if ($entry3Lines !== []) {
            $entries[] = [
                'entry_no' => 3,
                'date' => $asOf->toDateString(),
                'description' => $surplus > 0
                    ? 'Pindahkan Surplus ke Laba Ditahan'
                    : 'Pindahkan Defisit dari Laba Ditahan',
                'reference' => sprintf('CLOSING/%04d/03', $year),
                'lines' => $entry3Lines,
                'total_debit' => round(array_sum(array_column($entry3Lines, 'debit')), 2),
                'total_credit' => round(array_sum(array_column($entry3Lines, 'credit')), 2),
            ];
        }

        // Flattened rows (untuk view/vue) — gabungkan semua entries
        $rows = [];
        $i = 0;
        $totalAllDebit = 0.0;
        $totalAllCredit = 0.0;
        foreach ($entries as $entry) {
            $rows[] = [
                'is_header' => true,
                'entry_no' => $entry['entry_no'],
                'date' => $entry['date'],
                'description' => $entry['description'],
                'reference' => $entry['reference'],
            ];
            foreach ($entry['lines'] as $line) {
                $rows[] = [
                    'is_header' => false,
                    'no' => ++$i,
                    'date' => $entry['date'],
                    'reference' => $entry['reference'],
                    'entry_no' => $entry['entry_no'],
                    'account_code' => $line['account_code'],
                    'account_name' => $line['account_name'],
                    'memo' => $line['memo'],
                    'debit' => round($line['debit'], 2),
                    'credit' => round($line['credit'], 2),
                ];
                $totalAllDebit += $line['debit'];
                $totalAllCredit += $line['credit'];
            }
        }
        $totalAllDebit = round($totalAllDebit, 2);
        $totalAllCredit = round($totalAllCredit, 2);

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
            'entries' => $entries,
            'rows' => $rows,
            'revenue_rows' => $revenueRows,
            'expense_rows' => $expenseRows,
            'totals' => [
                'revenue' => $totalRevenue,
                'expense' => $totalExpense,
                'surplus' => $surplus,
                'debit' => $totalAllDebit,
                'credit' => $totalAllCredit,
            ],
            'summary_account' => [
                'code' => self::SUMMARY_ACCOUNT_CODE,
                'name' => 'Ikhtisar Laba Rugi',
            ],
            'retained_account' => [
                'code' => self::RETAINED_CODE,
                'name' => 'Laba Ditahan',
            ],
            'balanced' => abs($totalAllDebit - $totalAllCredit) < 0.02,
            'row_count' => count($rows),
        ];
    }
}
