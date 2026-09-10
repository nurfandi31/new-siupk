<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class JournalListingService
{
    private const PDF_ROW_CAP = 5000;

    public function __construct(
        private AccountBalanceQuery $balances,
        private TenantContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month, ?string $day = null, int $page = 1, int $perPage = 50, bool $forPdf = false): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $from = CarbonImmutable::parse($period['from'])->startOfDay();
        $until = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();

        if (is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
            $from = CarbonImmutable::parse($day)->startOfDay();
            $until = $from->addDay();
            $period['period_label'] = $from->format('d/m/Y');
            $period['as_of'] = $from->toDateString();
        }

        $tenantId = $this->context->id();

        $base = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->join('accounts as accounts', function ($join): void {
                $join->on('accounts.tenant_id', '=', 'lines.tenant_id')
                    ->on('accounts.row_id', '=', 'lines.account_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $from->toDateString())
            ->where('entries.transaction_date', '<', $until->toDateString());

        $totalLines = (clone $base)->count();

        $query = (clone $base)
            ->orderBy('entries.transaction_date')
            ->orderBy('entries.sequence_number')
            ->orderBy('entries.id')
            ->orderBy('lines.line_number')
            ->select([
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
                'accounts.code as account_code',
                'accounts.name as account_name',
            ]);

        $truncated = false;
        if ($forPdf) {
            if ($totalLines > self::PDF_ROW_CAP) {
                $truncated = true;
            }
            $raw = $query->limit(self::PDF_ROW_CAP)->get();
            $page = 1;
            $perPage = $raw->count();
            $lastPage = 1;
        } else {
            $page = max(1, $page);
            $perPage = max(10, min(200, $perPage));
            $lastPage = max(1, (int) ceil($totalLines / $perPage));
            $raw = $query->forPage($page, $perPage)->get();
        }

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $offset = ($page - 1) * $perPage;

        foreach ($raw as $i => $line) {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            $totalDebit += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'no' => $offset + $i + 1,
                'date' => (string) $line->transaction_date,
                'journal_number' => $line->journal_number ?: (string) $line->entry_id,
                'entry_row_id' => (int) $line->entry_row_id,
                'account_code' => (string) $line->account_code,
                'account_name' => (string) $line->account_name,
                'description' => (string) ($line->line_description ?: $line->entry_description ?: ''),
                'source_type' => $line->source_type,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        // Page totals only; full-period totals for footer when not paginated/pdf
        $periodTotals = $forPdf || $lastPage === 1
            ? ['debit' => round($totalDebit, 2), 'credit' => round($totalCredit, 2)]
            : $this->periodTotals($tenantId, $from, $until);

        $profile = OrganizationProfile::query()->first();

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? ''),
                'short_name' => $profile?->short_name,
            ],
            'day' => $day,
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalLines,
                'last_page' => $lastPage,
            ],
            'totals' => $periodTotals,
            'page_totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
            ],
            'truncated' => $truncated,
            'balanced' => abs($periodTotals['debit'] - $periodTotals['credit']) < 0.02,
        ];
    }

    /**
     * @return array{debit: float, credit: float}
     */
    private function periodTotals(int $tenantId, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $row = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $from->toDateString())
            ->where('entries.transaction_date', '<', $until->toDateString())
            ->selectRaw('COALESCE(SUM(lines.debit),0) as debit, COALESCE(SUM(lines.credit),0) as credit')
            ->first();

        return [
            'debit' => round((float) ($row->debit ?? 0), 2),
            'credit' => round((float) ($row->credit ?? 0), 2),
        ];
    }
}
