<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Migration\Accounting\DTO\NormalizedOpening;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LegacyOpeningBalanceLoader
{
    public function __construct(
        private TenantContext $context,
        private TenantSequenceService $sequences,
    ) {}

    /**
     * @param  list<NormalizedOpening>  $openings
     */
    public function load(int $batchRowId, string $sourceTable, array $openings): int
    {
        if ($openings === []) {
            return 0;
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId,
            $batchRowId,
            $sourceTable,
            $openings,
            $now,
            &$inserted,
        ): void {
            foreach ($openings as $o) {
                $existing = $db->table('account_opening_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('account_row_id', $o->accountRowId)
                    ->where('fiscal_year', $o->fiscalYear)
                    ->first(['row_id']);

                if ($existing !== null) {
                    $mapped = $db->table('legacy_record_mappings')
                        ->where('tenant_id', $tenantId)
                        ->where('source_table', $sourceTable)
                        ->where('source_id', $o->sourceId)
                        ->where('source_secondary_key', '0')
                        ->exists();
                    if (! $mapped) {
                        throw new RuntimeException(
                            "Opening exists without mapping for {$o->sourceId}; refuse silent overwrite."
                        );
                    }

                    continue;
                }

                $localId = $this->sequences->next('account_opening_balances');
                $rowId = $db->table('account_opening_balances')->insertGetId([
                    'tenant_id' => $tenantId,
                    'id' => $localId,
                    'account_row_id' => $o->accountRowId,
                    'fiscal_year' => $o->fiscalYear,
                    'debit' => $o->debit,
                    'credit' => $o->credit,
                    'source' => 'migration',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'row_id');

                $db->table('legacy_record_mappings')->insert([
                    'tenant_id' => $tenantId,
                    'batch_row_id' => $batchRowId,
                    'source_table' => $sourceTable,
                    'source_id' => $o->sourceId,
                    'source_secondary_key' => '0',
                    'target_table' => 'account_opening_balances',
                    'target_row_id' => $rowId,
                    'target_local_id' => $localId,
                    'source_snapshot' => json_encode([
                        'kode_akun' => $o->accountCode,
                        'tahun' => $o->fiscalYear,
                        'debit' => $o->debit,
                        'credit' => $o->credit,
                    ], JSON_THROW_ON_ERROR),
                    'migrated_at' => $now,
                    'created_at' => $now,
                ]);

                $inserted++;
            }
        }, 5);

        return $inserted;
    }
}
