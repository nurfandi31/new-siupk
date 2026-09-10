<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class GeneralLedgerService
{
    public function __construct(
        private AccountBalanceQuery $balances,
        private TenantContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month, int $accountRowId, ?string $day = null): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $periodFrom = CarbonImmutable::parse($period['from'])->startOfDay();
        $periodUntil = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();
        $mutatedAccountIds = Account::query()
            ->from('accounts as mutation_accounts')
            ->join('journal_lines as lines', 'lines.account_row_id', '=', 'mutation_accounts.row_id')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $periodFrom->toDateString())
            ->where('entries.transaction_date', '<', $periodUntil->toDateString())
            ->select('mutation_accounts.row_id');

        $account = Account::query()
            ->whereKey($accountRowId)
            ->where('is_postable', true)
            ->whereDate('created_at', '<=', $periodUntil->toDateString())
            ->where(function ($q) use ($periodFrom, $mutatedAccountIds): void {
                $q->where('is_active', true)
                    ->orWhereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>=', $periodFrom->toDateString())
                    ->orWhereIn('row_id', $mutatedAccountIds);
            })
            ->first(['row_id', 'code', 'name', 'account_type', 'normal_balance']);

        if ($account === null) {
            throw new InvalidArgumentException('Akun tidak ditemukan atau tidak postable.');
        }

        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();

        if (is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
            $periodFrom = CarbonImmutable::parse($day)->startOfDay();
            $periodUntil = $periodFrom->addDay();
            $period['period_label'] = $periodFrom->format('d/m/Y');
            $period['as_of'] = $periodFrom->toDateString();
            $period['from'] = $periodFrom->toDateString();
            $period['until_exclusive'] = $periodUntil->toDateString();
            $period['is_monthly'] = true;
            $month = (int) $periodFrom->month;
        }

        $openings = $this->balances->openings($year);
        $openingPair = $this->balances->movementPair($openings->get((int) $account->row_id));
        $openingSigned = $this->balances->signedBalance($account, $openingPair['debit'], $openingPair['credit']);

        // Prior: Jan1 .. period start
        $priorMovements = $this->balances->movements($yearStart, $periodFrom);
        $priorPair = $this->balances->movementPair($priorMovements->get((int) $account->row_id));
        $priorSigned = $this->balances->signedBalance($account, $priorPair['debit'], $priorPair['credit']);

        $running = round($openingSigned + $priorSigned, 2);

        $tenantId = $this->context->id();
        $lines = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('lines.account_row_id', $accountRowId)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $periodFrom->toDateString())
            ->where('entries.transaction_date', '<', $periodUntil->toDateString())
            ->orderBy('entries.transaction_date')
            ->orderBy('entries.sequence_number')
            ->orderBy('entries.id')
            ->orderBy('lines.line_number')
            ->get([
                'entries.row_id as entry_row_id',
                'entries.id as entry_id',
                'entries.journal_number',
                'entries.transaction_date',
                'entries.description as entry_description',
                'entries.source_type',
                'lines.line_number',
                'lines.description as line_description',
                'lines.debit',
                'lines.credit',
            ]);

        $rows = [];
        $periodDebit = 0.0;
        $periodCredit = 0.0;
        $no = 0;

        foreach ($lines as $line) {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            $delta = $this->balances->signedBalance($account, $debit, $credit);
            $running = round($running + $delta, 2);
            $periodDebit += $debit;
            $periodCredit += $credit;
            $no++;

            $rows[] = [
                'no' => $no,
                'date' => (string) $line->transaction_date,
                'journal_number' => $line->journal_number ?: (string) $line->entry_id,
                'entry_row_id' => (int) $line->entry_row_id,
                'description' => (string) ($line->line_description ?: $line->entry_description ?: ''),
                'source_type' => $line->source_type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
            ];
        }

        // YTD mutasi = prior (Jan1..period start) + period; kumulatif = opening year + YTD.
        $ytdDebit = round($priorPair['debit'] + $periodDebit, 2);
        $ytdCredit = round($priorPair['credit'] + $periodCredit, 2);
        $cumDebit = round($openingPair['debit'] + $ytdDebit, 2);
        $cumCredit = round($openingPair['credit'] + $ytdCredit, 2);

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $monthLabel = $month !== null ? ($monthNames[$month] ?? "Bulan {$month}") : null;

        $accountOptions = Account::query()
            ->where('is_postable', true)
            ->where('code', '!=', AccountBalanceQuery::CURRENT_EARNINGS_CODE)
            ->where(function ($q) use ($periodFrom, $periodUntil, $mutatedAccountIds): void {
                $q->whereDate('created_at', '<=', $periodUntil->toDateString())
                    ->where(function ($active) use ($periodFrom): void {
                        $active->where('is_active', true)
                            ->orWhereNull('deactivated_at')
                            ->orWhere('deactivated_at', '>=', $periodFrom->toDateString());
                    })
                    ->orWhereIn('row_id', $mutatedAccountIds);
            })
            ->orderBy('code')
            ->get(['row_id', 'code', 'name'])
            ->map(fn (Account $a) => [
                'row_id' => (int) $a->row_id,
                'code' => (string) $a->code,
                'name' => (string) $a->name,
                'label' => $a->code.' · '.$a->name,
            ])
            ->all();

        $profile = OrganizationProfile::query()->first();

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? ''),
                'short_name' => $profile?->short_name,
            ],
            'account' => [
                'row_id' => (int) $account->row_id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'normal_balance' => (string) $account->normal_balance,
                'account_type' => (string) $account->account_type,
            ],
            'opening' => [
                'year' => [
                    'label' => "Kumulatif Awal Tahun {$year}",
                    'date' => $yearStart->toDateString(),
                    'debit' => $openingPair['debit'],
                    'credit' => $openingPair['credit'],
                    'balance' => $openingSigned,
                ],
                'prior' => [
                    'label' => 'Kumulatif s/d Awal Periode',
                    'date' => $periodFrom->toDateString(),
                    'debit' => $priorPair['debit'],
                    'credit' => $priorPair['credit'],
                    'balance' => round($openingSigned + $priorSigned, 2),
                ],
            ],
            'rows' => $rows,
            'totals' => [
                // Keep flat keys for older consumers / tests.
                'debit' => round($periodDebit, 2),
                'credit' => round($periodCredit, 2),
                'closing_balance' => $running,
                'period' => [
                    'label' => $monthLabel !== null
                        ? "Total Transaksi Bulan {$monthLabel} {$year}"
                        : "Total Transaksi Tahun {$year}",
                    'debit' => round($periodDebit, 2),
                    'credit' => round($periodCredit, 2),
                    'balance' => null,
                ],
                'ytd' => [
                    'label' => $monthLabel !== null
                        ? "Total Transaksi sampai dengan Bulan {$monthLabel} {$year}"
                        : "Total Transaksi sampai dengan Tahun {$year}",
                    'debit' => $ytdDebit,
                    'credit' => $ytdCredit,
                    'balance' => $running,
                ],
                'cumulative' => [
                    'label' => "Total Transaksi Kumulatif sampai dengan Tahun {$year}",
                    'debit' => $cumDebit,
                    'credit' => $cumCredit,
                    'balance' => null,
                ],
            ],
            'account_options' => $accountOptions,
        ];
    }
}
