<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Migration\Accounting\DTO\NormalizedJournal;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LegacyJournalLoader
{
    public function __construct(
        private TenantContext $context,
        private TenantSequenceService $sequences,
    ) {}

    /**
     * @param  list<NormalizedJournal>  $journals
     * @return array{inserted: int, line_ids_used: int}
     */
    public function loadChunk(int $batchRowId, string $sourceTable, array $journals): array
    {
        if ($journals === []) {
            return ['inserted' => 0, 'line_ids_used' => 0];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId,
            $batchRowId,
            $sourceTable,
            $journals,
            $now,
            &$inserted,
        ): void {
            foreach ($journals as $j) {
                $entryRowId = $db->table('journal_entries')->insertGetId([
                    'tenant_id' => $tenantId,
                    'id' => $j->idt,
                    'public_id' => (string) Str::ulid(),
                    'journal_number' => (string) $j->idt,
                    'transaction_date' => $j->transactionDate,
                    'sequence_number' => $j->sequenceNumber,
                    'source_type' => 'legacy_transaksi',
                    'source_row_id' => null,
                    'description' => $j->description !== '' ? $j->description : null,
                    'legacy_relation' => $j->legacyRelation,
                    'legacy_transaction_type_id' => $j->legacyTransactionTypeId,
                    'legacy_loan_id' => $j->legacyLoanId,
                    'legacy_loan_item_id' => $j->legacyLoanItemId,
                    'legacy_debit_account_code' => $j->debitCode,
                    'legacy_credit_account_code' => $j->creditCode,
                    'legacy_amount_raw' => $j->amountRaw,
                    'status' => 'posted',
                    'reversed_entry_row_id' => null,
                    'posted_at' => $now,
                    'posted_by_user_id' => null,
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'row_id');

                $lineId1 = $this->sequences->next('journal_lines');
                $lineId2 = $this->sequences->next('journal_lines');

                $db->table('journal_lines')->insert([
                    [
                        'tenant_id' => $tenantId,
                        'id' => $lineId1,
                        'journal_entry_row_id' => $entryRowId,
                        'line_number' => 1,
                        'account_row_id' => $j->debitAccountRowId,
                        'organization_unit_row_id' => null,
                        'description' => null,
                        'debit' => $j->amount,
                        'credit' => '0.00',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'id' => $lineId2,
                        'journal_entry_row_id' => $entryRowId,
                        'line_number' => 2,
                        'account_row_id' => $j->creditAccountRowId,
                        'organization_unit_row_id' => null,
                        'description' => null,
                        'debit' => '0.00',
                        'credit' => $j->amount,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);

                $db->table('legacy_record_mappings')->insert([
                    'tenant_id' => $tenantId,
                    'batch_row_id' => $batchRowId,
                    'source_table' => $sourceTable,
                    'source_id' => (string) $j->idt,
                    'source_secondary_key' => '',
                    'target_table' => 'journal_entries',
                    'target_row_id' => $entryRowId,
                    'target_local_id' => $j->idt,
                    'source_snapshot' => json_encode($j->snapshot, JSON_THROW_ON_ERROR),
                    'migrated_at' => $now,
                    'created_at' => $now,
                ]);

                $inserted++;
            }
        }, 5);

        return ['inserted' => $inserted, 'line_ids_used' => $inserted * 2];
    }
}
