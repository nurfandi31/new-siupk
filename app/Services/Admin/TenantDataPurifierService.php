<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Domain\Accounting\Services\MonthlyBalanceRecalculator;
use App\Models\Platform\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final readonly class TenantDataPurifierService
{
    public function __construct(
        private TenantContext $context,
        private MonthlyBalanceRecalculator $recalculator,
    ) {}

    /**
     * @param  array{from?: ?string, to?: ?string, q?: ?string, source?: ?string, category?: ?string, page?: int, per_page?: int}  $filters
     */
    public function list(Tenant $tenant, array $filters = []): array
    {
        $tenantId = $tenant->row_id;
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $db = DB::connection($connectionName);

        $from = ! empty($filters['from']) ? (string) $filters['from'] : null;
        $to = ! empty($filters['to']) ? (string) $filters['to'] : null;
        $q = ! empty($filters['q']) ? trim((string) $filters['q']) : null;
        $source = ! empty($filters['source']) && $filters['source'] !== 'all' ? (string) $filters['source'] : null;
        $category = ! empty($filters['category']) ? (string) $filters['category'] : 'all';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 25)));

        $trainingStartedAt = $tenant->training_started_at?->format('Y-m-d H:i:s');
        $trainingEndedAt = $tenant->training_ended_at?->format('Y-m-d H:i:s');

        $baseQuery = $db->table('journal_entries as e')
            ->where('e.tenant_id', $tenantId);

        if ($from) {
            $baseQuery->where('e.transaction_date', '>=', $from);
        }
        if ($to) {
            $baseQuery->where('e.transaction_date', '<=', $to);
        }
        if ($source) {
            $baseQuery->where('e.source_type', $source);
        }

        if ($category === 'training') {
            if ($trainingStartedAt) {
                $baseQuery->where('e.created_at', '>=', $trainingStartedAt);
                if ($trainingEndedAt) {
                    $baseQuery->where('e.created_at', '<=', $trainingEndedAt);
                }
            } else {
                $baseQuery->where(function ($w): void {
                    $w->where('e.source_type', 'NOT LIKE', 'legacy_%')
                        ->orWhereNull('e.source_type');
                });
            }
        } elseif ($category === 'legacy') {
            $baseQuery->where('e.source_type', 'LIKE', 'legacy_%');
        }

        if ($q) {
            $term = '%'.$q.'%';
            $baseQuery->where(function ($w) use ($term): void {
                $w->where('e.description', 'like', $term)
                    ->orWhere('e.journal_number', 'like', $term)
                    ->orWhere('e.id', 'like', ltrim($term, '%'));
            });
        }

        $total = (clone $baseQuery)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $entries = (clone $baseQuery)
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
                'e.status',
                'e.reversed_entry_row_id',
                'e.created_at',
            ]);

        $rowIds = $entries->pluck('row_id')->map(fn ($id) => (int) $id)->all();
        $amounts = [];
        $reversedOf = [];

        if ($rowIds !== []) {
            $amountRows = $db->table('journal_lines')
                ->where('tenant_id', $tenantId)
                ->whereIn('journal_entry_row_id', $rowIds)
                ->groupBy('journal_entry_row_id')
                ->selectRaw('journal_entry_row_id, CAST(COALESCE(SUM(debit), 0) AS CHAR) AS debit_total')
                ->get();
            foreach ($amountRows as $ar) {
                $amounts[(int) $ar->journal_entry_row_id] = round((float) $ar->debit_total, 2);
            }

            $revRows = $db->table('journal_entries')
                ->where('tenant_id', $tenantId)
                ->whereIn('reversed_entry_row_id', $rowIds)
                ->get(['row_id', 'id', 'reversed_entry_row_id']);
            foreach ($revRows as $rr) {
                $reversedOf[(int) $rr->reversed_entry_row_id] = [
                    'row_id' => (int) $rr->row_id,
                    'id' => (int) $rr->id,
                ];
            }
        }

        $rows = [];
        foreach ($entries as $e) {
            $rowId = (int) $e->row_id;
            $isLegacy = str_starts_with((string) ($e->source_type ?? ''), 'legacy_');
            $isReversal = $e->reversed_entry_row_id !== null || (string) $e->source_type === 'journal_reversal';
            $hasReversal = isset($reversedOf[$rowId]);

            $isTraining = $trainingStartedAt !== null
                ? ($e->created_at >= $trainingStartedAt && ($trainingEndedAt === null || $e->created_at <= $trainingEndedAt))
                : ! $isLegacy;

            $rows[] = [
                'row_id' => $rowId,
                'id' => (int) $e->id,
                'journal_number' => $e->journal_number ?: (string) $e->id,
                'transaction_date' => (string) $e->transaction_date,
                'description' => (string) ($e->description ?? ''),
                'source_type' => (string) ($e->source_type ?? 'manual'),
                'source_row_id' => $e->source_row_id ? (int) $e->source_row_id : null,
                'status' => (string) $e->status,
                'amount' => $amounts[$rowId] ?? 0.0,
                'is_legacy' => $isLegacy,
                'is_training' => $isTraining,
                'is_reversal' => $isReversal,
                'already_reversed' => $hasReversal,
                'reversal_id' => $reversedOf[$rowId]['id'] ?? null,
                'created_at' => (string) $e->created_at,
            ];
        }

        // Training count based on time window
        $trainingQuery = $db->table('journal_entries')->where('tenant_id', $tenantId);
        if ($trainingStartedAt) {
            $trainingQuery->where('created_at', '>=', $trainingStartedAt);
            if ($trainingEndedAt) {
                $trainingQuery->where('created_at', '<=', $trainingEndedAt);
            }
        } else {
            $trainingQuery->where(function ($w): void {
                $w->where('source_type', 'NOT LIKE', 'legacy_%')
                    ->orWhereNull('source_type');
            });
        }

        $stats = [
            'total_transactions' => $db->table('journal_entries')->where('tenant_id', $tenantId)->count(),
            'training_transactions_count' => $trainingQuery->count(),
            'reversal_count' => $db->table('journal_entries')
                ->where('tenant_id', $tenantId)
                ->where(function ($w): void {
                    $w->whereNotNull('reversed_entry_row_id')
                        ->orWhere('source_type', 'journal_reversal');
                })
                ->count(),
            'opening_balances_count' => $db->table('account_opening_balances')->where('tenant_id', $tenantId)->count(),
            'is_training_mode' => $tenant->isTraining(),
            'training_started_at' => $tenant->training_started_at?->toIso8601String(),
            'training_ended_at' => $tenant->training_ended_at?->toIso8601String(),
            'is_locked' => ! $tenant->isTraining() && $tenant->training_ended_at !== null,
        ];

        return [
            'rows' => $rows,
            'stats' => $stats,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'q' => $q,
                'source' => $source ?: 'all',
                'category' => $category,
            ],
        ];
    }

    /**
     * Start a training session for the tenant.
     */
    public function startTraining(Tenant $tenant): void
    {
        $tenant->forceFill([
            'is_training_mode' => true,
            'training_started_at' => now(),
            'training_ended_at' => null,
        ])->save();
    }

    /**
     * End training session and switch to Live/Production mode.
     * Optionally purge all training transactions.
     *
     * @return array{deleted_entries: int, deleted_lines: int, deleted_installments: int}
     */
    public function endTraining(Tenant $tenant, bool $purgeTrainingData = false): array
    {
        $deleted = ['deleted_entries' => 0, 'deleted_lines' => 0, 'deleted_installments' => 0];

        if ($purgeTrainingData) {
            $deleted = $this->resetTrainingTransactions($tenant);
        }

        $tenant->forceFill([
            'is_training_mode' => false,
            'training_ended_at' => now(),
        ])->save();

        return $deleted;
    }

    /**
     * Purges specific journal entries and their associated rows cleanly.
     *
     * @param  array<int>  $entryRowIds
     * @return array{deleted_entries: int, deleted_lines: int, deleted_installments: int}
     */
    public function purge(Tenant $tenant, array $entryRowIds, bool $includeReversalPairs = true): array
    {
        if (empty($entryRowIds)) {
            return ['deleted_entries' => 0, 'deleted_lines' => 0, 'deleted_installments' => 0];
        }

        $tenantId = $tenant->row_id;
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $db = DB::connection($connectionName);

        return $db->transaction(function (ConnectionInterface $conn) use ($tenantId, $entryRowIds, $includeReversalPairs): array {
            $allIds = array_map('intval', $entryRowIds);

            if ($includeReversalPairs) {
                // Find any entries that reverse these
                $reversalIds = $conn->table('journal_entries')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('reversed_entry_row_id', $allIds)
                    ->pluck('row_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                // Find original entries if any selected is a reversal
                $reversedOriginalIds = $conn->table('journal_entries')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('row_id', $allIds)
                    ->whereNotNull('reversed_entry_row_id')
                    ->pluck('reversed_entry_row_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $allIds = array_values(array_unique(array_merge($allIds, $reversalIds, $reversedOriginalIds)));
            }

            // Get affected dates for monthly balance recalculation
            $affectedDates = $conn->table('journal_entries')
                ->where('tenant_id', $tenantId)
                ->whereIn('row_id', $allIds)
                ->pluck('transaction_date')
                ->all();

            // 1. Delete loan installment tracking
            $deletedInstallments = $conn->table('loan_installment_tracking')
                ->where('tenant_id', $tenantId)
                ->whereIn('journal_entry_row_id', $allIds)
                ->delete();

            // 2. Clear reversed_entry_row_id references on other entries to prevent FK constraint
            $conn->table('journal_entries')
                ->where('tenant_id', $tenantId)
                ->whereIn('reversed_entry_row_id', $allIds)
                ->update(['reversed_entry_row_id' => null]);

            // 3. Delete journal lines
            $deletedLines = $conn->table('journal_lines')
                ->where('tenant_id', $tenantId)
                ->whereIn('journal_entry_row_id', $allIds)
                ->delete();

            // 4. Delete journal entries
            $deletedEntries = $conn->table('journal_entries')
                ->where('tenant_id', $tenantId)
                ->whereIn('row_id', $allIds)
                ->delete();

            // 5. Recalculate affected monthly balances
            $monthsRecalculated = [];
            foreach ($affectedDates as $d) {
                if ($d) {
                    $c = CarbonImmutable::parse($d);
                    $key = $c->year.'-'.$c->month;
                    if (! isset($monthsRecalculated[$key])) {
                        $monthsRecalculated[$key] = true;
                        try {
                            $this->recalculator->recalculate($c->year, $c->month);
                        } catch (\Throwable) {
                            // Non-fatal if period recalculation fails
                        }
                    }
                }
            }

            return [
                'deleted_entries' => $deletedEntries,
                'deleted_lines' => $deletedLines,
                'deleted_installments' => $deletedInstallments,
            ];
        });
    }

    /**
     * Resets training transactions based on training session window.
     * Keeps opening balances, members, groups, COA, etc. completely untouched.
     */
    public function resetTrainingTransactions(Tenant $tenant): array
    {
        $tenantId = $tenant->row_id;
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $db = DB::connection($connectionName);

        $trainingStartedAt = $tenant->training_started_at?->format('Y-m-d H:i:s');
        $trainingEndedAt = $tenant->training_ended_at?->format('Y-m-d H:i:s');

        $query = $db->table('journal_entries')->where('tenant_id', $tenantId);

        if ($trainingStartedAt) {
            $query->where('created_at', '>=', $trainingStartedAt);
            if ($trainingEndedAt) {
                $query->where('created_at', '<=', $trainingEndedAt);
            }
        } else {
            $query->where(function ($w): void {
                $w->where('source_type', 'NOT LIKE', 'legacy_%')
                    ->orWhereNull('source_type');
            });
        }

        $trainingEntryIds = $query->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        return $this->purge($tenant, $trainingEntryIds, includeReversalPairs: true);
    }
}
