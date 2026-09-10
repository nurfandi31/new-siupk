<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Services\Reports\DocumentKindClassifier;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Daftar jurnal level header (ops harian) — beda dari laporan baris.
 */
final class JournalBrowseService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentKindClassifier $classifier,
    ) {}

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   pagination: array{page:int,per_page:int,total:int,last_page:int},
     *   filters: array{from:string,to:string,q:?string,source:?string}
     * }
     */
    public function list(
        string $from,
        string $to,
        ?string $q = null,
        ?string $source = null,
        int $page = 1,
        int $perPage = 25,
    ): array {
        $fromDate = $this->dateOr($from, CarbonImmutable::today()->startOfMonth()->toDateString());
        $toDate = $this->dateOr($to, CarbonImmutable::today()->toDateString());
        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $tenantId = $this->context->id();
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));

        $base = DB::connection('tenant')
            ->table('journal_entries as e')
            ->where('e.tenant_id', $tenantId)
            ->where('e.status', 'posted')
            ->where('e.transaction_date', '>=', $fromDate)
            ->where('e.transaction_date', '<=', $toDate);

        if (is_string($q) && trim($q) !== '') {
            $term = '%'.trim($q).'%';
            $base->where(function ($w) use ($term): void {
                $w->where('e.description', 'like', $term)
                    ->orWhere('e.journal_number', 'like', $term)
                    ->orWhere('e.id', 'like', ltrim($term, '%'));
            });
        }

        if (is_string($source) && $source !== '' && $source !== 'all') {
            $base->where('e.source_type', $source);
        }

        $total = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $entries = (clone $base)
            ->orderByDesc('e.transaction_date')
            ->orderByDesc('e.id')
            ->forPage($page, $perPage)
            ->get([
                'e.row_id',
                'e.id',
                'e.journal_number',
                'e.transaction_date',
                'e.description',
                'e.source_type',
                'e.source_row_id',
                'e.reversed_entry_row_id',
                'e.posted_at',
            ]);

        $rowIds = $entries->pluck('row_id')->map(fn ($id) => (int) $id)->all();
        $amounts = [];
        $reversedOf = [];
        $sideCodes = [];
        if ($rowIds !== []) {
            $amountRows = DB::connection('tenant')
                ->table('journal_lines')
                ->where('tenant_id', $tenantId)
                ->whereIn('journal_entry_row_id', $rowIds)
                ->groupBy('journal_entry_row_id')
                ->selectRaw('journal_entry_row_id, CAST(COALESCE(SUM(debit), 0) AS CHAR) AS debit_total')
                ->get();
            foreach ($amountRows as $ar) {
                $amounts[(int) $ar->journal_entry_row_id] = round((float) $ar->debit_total, 2);
            }

            $revRows = DB::connection('tenant')
                ->table('journal_entries')
                ->where('tenant_id', $tenantId)
                ->whereIn('reversed_entry_row_id', $rowIds)
                ->get(['row_id', 'id', 'reversed_entry_row_id']);
            foreach ($revRows as $rr) {
                $reversedOf[(int) $rr->reversed_entry_row_id] = [
                    'row_id' => (int) $rr->row_id,
                    'id' => (int) $rr->id,
                ];
            }

            $sideRows = DB::connection('tenant')
                ->table('journal_lines as l')
                ->join('accounts as a', function ($j) use ($tenantId): void {
                    $j->on('a.row_id', '=', 'l.account_row_id')
                        ->where('a.tenant_id', '=', $tenantId);
                })
                ->where('l.tenant_id', $tenantId)
                ->whereIn('l.journal_entry_row_id', $rowIds)
                ->where('l.line_number', '<=', 2)
                ->get(['l.journal_entry_row_id', 'l.line_number', 'l.debit', 'l.credit', 'a.code as account_code']);
            foreach ($sideRows as $sr) {
                $key = (int) $sr->journal_entry_row_id;
                if (! isset($sideCodes[$key])) {
                    $sideCodes[$key] = ['debit' => null, 'credit' => null];
                }
                if ((float) $sr->debit > 0 && $sideCodes[$key]['debit'] === null) {
                    $sideCodes[$key]['debit'] = (string) $sr->account_code;
                } elseif ((float) $sr->credit > 0 && $sideCodes[$key]['credit'] === null) {
                    $sideCodes[$key]['credit'] = (string) $sr->account_code;
                }
            }
        }

        $rows = [];
        foreach ($entries as $e) {
            $rowId = (int) $e->row_id;
            // reversed_entry_row_id set ⇒ this row IS a reversal of another entry.
            $isReversal = $e->reversed_entry_row_id !== null
                || (string) $e->source_type === 'journal_reversal';
            $alreadyReversed = isset($reversedOf[$rowId]);
            $canReverse = ! $isReversal && ! $alreadyReversed;
            $canEdit = $canReverse
                && in_array((string) ($e->source_type ?? ''), JournalEditService::EDITABLE_SOURCE_TYPES, true);

            $rows[] = [
                'row_id' => $rowId,
                'id' => (int) $e->id,
                'journal_number' => $e->journal_number ?: (string) $e->id,
                'transaction_date' => (string) $e->transaction_date,
                'description' => (string) ($e->description ?? ''),
                'source_type' => (string) ($e->source_type ?? ''),
                'source_row_id' => $e->source_row_id ? (int) $e->source_row_id : null,
                'amount' => $amounts[$rowId] ?? 0.0,
                'posted_at' => $e->posted_at ? (string) $e->posted_at : null,
                'is_reversal' => $isReversal,
                'already_reversed' => $alreadyReversed,
                'reversal' => $reversedOf[$rowId] ?? null,
                'can_reverse' => $canReverse,
                'can_edit' => $canEdit,
                'receipt_url' => $this->receiptUrl($rowId, (string) ($e->source_type ?? ''), (string) ($e->description ?? '')),
                'cash_evidence_kind' => $this->cashEvidenceKind($sideCodes[$rowId] ?? ['debit' => null, 'credit' => null]),
                'cash_evidence_url' => '/accounting/journals/'.$rowId.'/cash-evidence',
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
            'filters' => [
                'from' => $fromDate,
                'to' => $toDate,
                'q' => $q,
                'source' => $source ?: 'all',
            ],
        ];
    }

    private function dateOr(string $value, string $fallback): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        return $fallback;
    }

    private function receiptUrl(int $rowId, string $sourceType, string $description): ?string
    {
        if ($sourceType === 'loan_installment') {
            return '/accounting/journal-entries/'.$rowId.'/installment-receipt';
        }
        // Migrated angsuran journals keep source_type=legacy_transaksi
        if ($sourceType === 'legacy_transaksi' && preg_match('/\bAngs\.?\b/iu', $description) === 1) {
            return '/accounting/journal-entries/'.$rowId.'/installment-receipt';
        }

        return null;
    }

    /**
     * @param  array{debit: ?string, credit: ?string}  $codes
     */
    private function cashEvidenceKind(array $codes): string
    {
        return $this->classifier->classify($codes['debit'], $codes['credit']);
    }
}
