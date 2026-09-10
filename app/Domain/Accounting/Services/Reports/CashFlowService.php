<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arus kas langsung dari jurnal posted yang menyentuh akun kas (1.1.01*).
 *
 * Beda dari legacy: tidak pakai tabel mapping ArusKas per baris teks.
 * Klasifikasi lawan kas by account_type + prefix kode (lebih auditabel).
 */
final class CashFlowService
{
    private const CASH_PREFIX = '1.1.01';

    public function __construct(
        private readonly AccountBalanceQuery $balances,
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $from = CarbonImmutable::parse($period['from'])->startOfDay();
        $until = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $tenantId = $this->context->id();

        $cashMutationAccountIds = Account::query()
            ->from('accounts as cash_mutations')
            ->join('journal_lines as lines', 'lines.account_row_id', '=', 'cash_mutations.row_id')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $from->toDateString())
            ->where('entries.transaction_date', '<', $until->toDateString())
            ->select('cash_mutations.row_id');

        $cashAccounts = Account::query()
            ->where('is_postable', true)
            ->where('code', 'like', self::CASH_PREFIX.'%')
            ->where(function ($q) use ($from, $until, $cashMutationAccountIds): void {
                $q->where(function ($relevant) use ($from, $until): void {
                    $relevant->whereDate('created_at', '<=', $until->toDateString())
                        ->where(function ($active) use ($from): void {
                            $active->where('is_active', true)
                                ->orWhereNull('deactivated_at')
                                ->orWhere('deactivated_at', '>=', $from->toDateString());
                        });
                })->orWhereIn('row_id', $cashMutationAccountIds);
            })
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance']);

        $cashIds = $cashAccounts->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $openingCash = 0.0;
        $closingCash = 0.0;
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        foreach ($cashAccounts as $account) {
            $openingCash += $this->balanceBeforePeriod($account, $from, $yearStart, $year);
            $closingCash += $this->balances->asOfRaw($account, $asOf)['signed'];
        }
        $openingCash = round($openingCash, 2);
        $closingCash = round($closingCash, 2);

        $sections = [
            'operating' => ['key' => 'operating', 'label' => 'Arus kas dari aktivitas operasi', 'lines' => [], 'total' => 0.0],
            'investing' => ['key' => 'investing', 'label' => 'Arus kas dari aktivitas investasi', 'lines' => [], 'total' => 0.0],
            'financing' => ['key' => 'financing', 'label' => 'Arus kas dari aktivitas pendanaan', 'lines' => [], 'total' => 0.0],
        ];

        if ($cashIds === []) {
            return $this->payload($period, $openingCash, $closingCash, $sections, $cashAccounts);
        }

        // Journals in period that touch cash
        $entryIds = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($j): void {
                $j->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.status', 'posted')
            ->whereIn('lines.account_row_id', $cashIds)
            ->where('entries.transaction_date', '>=', $from->toDateString())
            ->where('entries.transaction_date', '<', $until->toDateString())
            ->distinct()
            ->pluck('entries.row_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($entryIds === []) {
            return $this->payload($period, $openingCash, $closingCash, $sections, $cashAccounts);
        }

        $lines = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($j): void {
                $j->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->join('accounts as accounts', function ($j): void {
                $j->on('accounts.tenant_id', '=', 'lines.tenant_id')
                    ->on('accounts.row_id', '=', 'lines.account_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->whereIn('lines.journal_entry_row_id', $entryIds)
            ->orderBy('entries.transaction_date')
            ->orderBy('entries.id')
            ->orderBy('lines.line_number')
            ->get([
                'entries.row_id as entry_row_id',
                'entries.id as entry_id',
                'entries.transaction_date',
                'entries.description as entry_description',
                'entries.source_type',
                'lines.account_row_id',
                'lines.debit',
                'lines.credit',
                'lines.description as line_description',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.account_type',
            ])
            ->groupBy('entry_row_id');

        /** @var array<string, array{label: string, amount: float, count: int}> $buckets */
        $buckets = [];

        foreach ($lines as $entryRowId => $entryLines) {
            $cashNet = 0.0; // + inflow (debit cash), − outflow
            $counterWeights = [
                'operating' => 0.0,
                'investing' => 0.0,
                'financing' => 0.0,
            ];
            $labels = [
                'operating' => [],
                'investing' => [],
                'financing' => [],
            ];
            $sourceType = (string) ($entryLines->first()->source_type ?? '');
            $entryDesc = (string) ($entryLines->first()->entry_description ?? '');

            foreach ($entryLines as $line) {
                $debit = (float) $line->debit;
                $credit = (float) $line->credit;
                $isCash = in_array((int) $line->account_row_id, $cashIds, true);

                if ($isCash) {
                    $cashNet = round($cashNet + ($debit - $credit), 2);

                    continue;
                }

                $section = $this->classifyCounter(
                    code: (string) $line->account_code,
                    type: (string) $line->account_type,
                    sourceType: $sourceType,
                );
                $weight = abs($debit - $credit);
                $counterWeights[$section] = round($counterWeights[$section] + $weight, 2);
                $labels[$section][] = trim((string) $line->account_code.' · '.$line->account_name);
            }

            if (abs($cashNet) < 0.009) {
                continue;
            }

            $totalWeight = array_sum($counterWeights);
            if ($totalWeight < 0.009) {
                // Pure cash transfer or unclassified — operating catch-all
                $this->addBucket(
                    $buckets,
                    'operating',
                    $this->lineLabel($sourceType, $entryDesc, ['Mutasi kas']),
                    $cashNet,
                );

                continue;
            }

            // Allocate cash net proportional to counterparty weights
            $allocated = 0.0;
            $parts = [];
            foreach (['operating', 'investing', 'financing'] as $sec) {
                if ($counterWeights[$sec] < 0.009) {
                    continue;
                }
                $share = round($cashNet * ($counterWeights[$sec] / $totalWeight), 2);
                $parts[$sec] = $share;
                $allocated = round($allocated + $share, 2);
            }
            // Fix rounding remainder on largest weight section
            $diff = round($cashNet - $allocated, 2);
            if (abs($diff) >= 0.01 && $parts !== []) {
                $maxSec = array_key_first($parts);
                foreach ($parts as $sec => $amt) {
                    if ($counterWeights[$sec] >= $counterWeights[$maxSec]) {
                        $maxSec = $sec;
                    }
                }
                $parts[$maxSec] = round($parts[$maxSec] + $diff, 2);
            }

            foreach ($parts as $sec => $amt) {
                if (abs($amt) < 0.009) {
                    continue;
                }
                $uniqueLabels = array_values(array_unique($labels[$sec]));
                $this->addBucket(
                    $buckets,
                    $sec,
                    $this->lineLabel($sourceType, $entryDesc, $uniqueLabels),
                    $amt,
                );
            }
        }

        foreach ($buckets as $key => $bucket) {
            [$sectionKey, $label] = explode("\0", $key, 2);
            if (! isset($sections[$sectionKey])) {
                continue;
            }
            $sections[$sectionKey]['lines'][] = [
                'label' => $label,
                'amount' => $bucket['amount'],
                'count' => $bucket['count'],
            ];
            $sections[$sectionKey]['total'] = round($sections[$sectionKey]['total'] + $bucket['amount'], 2);
        }

        foreach ($sections as &$section) {
            usort($section['lines'], fn ($a, $b) => abs($b['amount']) <=> abs($a['amount']));
        }
        unset($section);

        return $this->payload($period, $openingCash, $closingCash, $sections, $cashAccounts);
    }

    /**
     * @param  array<string, array{label: string, amount: float, count: int}>  $buckets
     * @param  list<string>  $counterLabels
     */
    private function addBucket(array &$buckets, string $section, string $label, float $amount): void
    {
        $key = $section."\0".$label;
        if (! isset($buckets[$key])) {
            $buckets[$key] = ['label' => $label, 'amount' => 0.0, 'count' => 0];
        }
        $buckets[$key]['amount'] = round($buckets[$key]['amount'] + $amount, 2);
        $buckets[$key]['count']++;
    }

    /**
     * @param  list<string>  $counterLabels
     */
    private function lineLabel(string $sourceType, string $entryDesc, array $counterLabels): string
    {
        $sourceLabels = [
            'loan_installment' => 'Penerimaan angsuran',
            'loan' => 'Pencairan pinjaman',
            'loan_write_off' => 'Penghapusan piutang',
            'loan_reschedule_close' => 'Reschedule pinjaman',
            'journal_reversal' => 'Reversal jurnal',
            'manual' => 'Jurnal umum',
        ];

        if (isset($sourceLabels[$sourceType]) && in_array($sourceType, ['loan_installment', 'loan', 'loan_write_off', 'loan_reschedule_close'], true)) {
            return $sourceLabels[$sourceType];
        }

        if ($counterLabels !== []) {
            $first = $counterLabels[0];
            if (count($counterLabels) > 1) {
                return $first.' (+'.(count($counterLabels) - 1).' akun)';
            }

            return $first;
        }

        if ($entryDesc !== '') {
            return mb_strlen($entryDesc) > 80 ? mb_substr($entryDesc, 0, 77).'…' : $entryDesc;
        }

        return $sourceLabels[$sourceType] ?? 'Mutasi kas lain';
    }

    private function balanceBeforePeriod(
        Account $account,
        CarbonImmutable $from,
        CarbonImmutable $yearStart,
        int $year,
    ): float {
        // Period starts at year open → opening balances only (no YTD movements yet).
        if ($from->equalTo($yearStart) || $from->lessThanOrEqualTo($yearStart)) {
            $pair = $this->balances->movementPair(
                $this->balances->openings($year)->get((int) $account->row_id),
            );

            return $this->balances->signedBalance($account, $pair['debit'], $pair['credit']);
        }

        return $this->balances->asOfRaw($account, $from->subDay())['signed'];
    }

    private function classifyCounter(string $code, string $type, string $sourceType): string
    {
        // Lending core = operating for BUMDesma/LKD
        if (in_array($sourceType, ['loan_installment', 'loan', 'loan_write_off', 'loan_reschedule_close'], true)) {
            return 'operating';
        }

        if (in_array($type, ['revenue', 'expense'], true)) {
            return 'operating';
        }

        // Piutang / persediaan lancar non-kas → operasi
        if ($type === 'asset' && (str_starts_with($code, '1.1.03') || str_starts_with($code, '1.1.02') || str_starts_with($code, '1.1.04'))) {
            return 'operating';
        }

        // Aset tetap / investasi → investasi
        if ($type === 'asset' && (str_starts_with($code, '1.2') || str_starts_with($code, '1.3'))) {
            return 'investing';
        }

        if (in_array($type, ['liability', 'equity'], true)) {
            return 'financing';
        }

        // residual asset → investing; else operating
        if ($type === 'asset') {
            return 'investing';
        }

        return 'operating';
    }

    /**
     * @param  array<string, array{key:string,label:string,lines:list<array<string,mixed>>,total:float}>  $sections
     * @param  Collection<int, Account>  $cashAccounts
     * @return array<string, mixed>
     */
    private function payload(array $period, float $opening, float $closing, array $sections, $cashAccounts): array
    {
        $net = round(
            $sections['operating']['total']
            + $sections['investing']['total']
            + $sections['financing']['total'],
            2,
        );
        $impliedClosing = round($opening + $net, 2);
        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
            ],
            'cash_accounts' => $cashAccounts->map(fn (Account $a) => [
                'row_id' => (int) $a->row_id,
                'code' => (string) $a->code,
                'name' => (string) $a->name,
            ])->values()->all(),
            'opening_cash' => $opening,
            'closing_cash' => $closing,
            'implied_closing' => $impliedClosing,
            'net_change' => $net,
            'reconciled' => abs($closing - $impliedClosing) < 0.05,
            'sections' => array_values($sections),
        ];
    }
}
