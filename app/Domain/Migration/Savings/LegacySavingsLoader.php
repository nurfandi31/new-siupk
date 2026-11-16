<?php

declare(strict_types=1);

namespace App\Domain\Migration\Savings;

use App\Domain\Migration\Savings\DTO\NormalizedSavingsAccount;
use App\Domain\Migration\Savings\DTO\NormalizedSavingsProduct;
use App\Domain\Migration\Savings\DTO\NormalizedSavingsTransaction;
use App\Domain\Savings\SavingsModule;
use App\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final class LegacySavingsLoader
{
    public function __construct(
        private TenantContext $context,
    ) {}

    /**
     * @param  list<NormalizedSavingsProduct>  $products
     * @return array<int, int> legacy product id => target product row_id
     */
    public function loadProducts(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $table = SavingsModule::table('products', $tenantId);
        $now = now()->format('Y-m-d H:i:s');
        $map = [];

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId, $table, $products, $now, &$map,
        ): void {
            foreach ($products as $product) {
                $existing = $db->table($table)
                    ->where('tenant_id', $tenantId)
                    ->where('code', $product->code)
                    ->first(['row_id']);

                $payload = [
                    'name' => $product->name,
                    'category' => $product->category,
                    'form_type' => $product->formType,
                    'minimum_balance' => $product->minimumBalance,
                    'is_active' => true,
                    'sort_order' => $product->sortOrder,
                    'updated_at' => $now,
                ];

                if ($existing !== null) {
                    $rowId = (int) $existing->row_id;
                    $db->table($table)->where('row_id', $rowId)->update($payload);
                } else {
                    $rowId = (int) $db->table($table)->insertGetId([
                        'tenant_id' => $tenantId,
                        'code' => $product->code,
                        'interest_rate' => '0.000',
                        'tax_rate' => '0.000',
                        'admin_fee' => '0.00',
                        'opening_fee' => '0.00',
                        'description' => 'Produk simpanan legacy',
                        'created_at' => $now,
                        ...$payload,
                    ], 'row_id');
                }

                $map[$product->legacyId] = $rowId;
            }
        }, 5);

        return $map;
    }

    /**
     * @param  list<NormalizedSavingsAccount>  $accounts
     * @return array{inserted: int, skipped: int, warnings: list<string>}
     */
    public function loadAccounts(int $batchRowId, string $sourceTable, array $accounts): array
    {
        if ($accounts === []) {
            return ['inserted' => 0, 'skipped' => 0, 'warnings' => []];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $table = SavingsModule::table('accounts', $tenantId);
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;
        $skipped = 0;
        $warnings = [];

        $members = DB::connection($connName)->table('members')
            ->where('tenant_id', $tenantId)
            ->pluck('row_id', 'id')
            ->map(fn ($v) => (int) $v)
            ->all();

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId, $batchRowId, $sourceTable, $accounts, $table, $now, $members,
            &$inserted, &$skipped, &$warnings,
        ): void {
            foreach ($accounts as $account) {
                $exists = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $sourceTable)
                    ->where('source_id', (string) $account->legacyId)
                    ->where('source_secondary_key', 'savings_account')
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }

                $memberRowId = $members[$account->memberLegacyId] ?? null;
                if ($memberRowId === null) {
                    $warnings[] = "simpanan id={$account->legacyId}: member nia={$account->memberLegacyId} missing";

                    continue;
                }

                if ($account->productRowId <= 0) {
                    $warnings[] = "simpanan id={$account->legacyId}: product jenis_simpanan={$account->productLegacyId} missing";

                    continue;
                }

                $number = $this->uniqueAccountNumber($db, $table, $tenantId, $account->accountNumber, $account->legacyId);

                $rowId = (int) $db->table($table)->insertGetId([
                    'tenant_id' => $tenantId,
                    'account_number' => $number,
                    'member_row_id' => $memberRowId,
                    'product_row_id' => $account->productRowId,
                    'balance' => $account->openingAmount,
                    'interest_rate' => $account->interestRate,
                    'tax_rate' => $account->taxRate,
                    'admin_fee' => $account->adminFee,
                    'opening_fee' => $account->openingFee,
                    'opened_at' => $account->openedAt,
                    'minimum_closing_date' => $account->minimumClosingDate,
                    'closed_at' => $account->closedAt,
                    'status' => $account->status,
                    'authority_type' => $account->authorityType,
                    'institution_name' => $account->institutionName,
                    'institution_position' => $account->institutionPosition,
                    'guardian_name' => $account->guardianName,
                    'relationship' => $account->relationship,
                    'notes' => $account->notes,
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'row_id');

                $db->table('legacy_record_mappings')->insert([
                    'tenant_id' => $tenantId,
                    'batch_row_id' => $batchRowId,
                    'source_table' => $sourceTable,
                    'source_id' => (string) $account->legacyId,
                    'source_secondary_key' => 'savings_account',
                    'target_table' => 'savings_accounts',
                    'target_row_id' => $rowId,
                    'target_local_id' => $account->legacyId,
                    'source_snapshot' => json_encode($account->snapshot, JSON_THROW_ON_ERROR),
                    'migrated_at' => $now,
                    'created_at' => $now,
                ]);

                $inserted++;
            }
        }, 5);

        return ['inserted' => $inserted, 'skipped' => $skipped, 'warnings' => $warnings];
    }

    /**
     * @param  list<NormalizedSavingsTransaction>  $rows
     * @return array{inserted: int, skipped: int, warnings: list<string>}
     */
    public function loadTransactions(
        int $batchRowId,
        string $sourceTable,
        string $accountSourceTable,
        array $rows,
    ): array {
        if ($rows === []) {
            return ['inserted' => 0, 'skipped' => 0, 'warnings' => []];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $table = SavingsModule::table('transactions', $tenantId);
        $accountsTable = SavingsModule::table('accounts', $tenantId);
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;
        $skipped = 0;
        $warnings = [];

        $accountMap = DB::connection($connName)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $accountSourceTable)
            ->where('source_secondary_key', 'savings_account')
            ->pluck('target_row_id', 'source_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $balances = DB::connection($connName)->table($accountsTable)
            ->where('tenant_id', $tenantId)
            ->pluck('balance', 'row_id')
            ->map(fn ($v) => (string) $v)
            ->all();

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId, $batchRowId, $sourceTable, $rows, $table, $accountsTable, $now,
            $accountMap, &$balances, &$inserted, &$skipped, &$warnings,
        ): void {
            foreach ($rows as $txn) {
                $exists = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $sourceTable)
                    ->where('source_id', (string) $txn->legacyId)
                    ->where('source_secondary_key', 'savings_transaction')
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }

                $accountRowId = $accountMap[$txn->accountLegacyId] ?? null;
                if ($accountRowId === null) {
                    $warnings[] = "real_simpanan id={$txn->legacyId}: account {$txn->accountLegacyId} missing";

                    continue;
                }

                $current = bcadd($balances[$accountRowId] ?? '0.00', '0', 2);
                $computed = $txn->direction === 'in'
                    ? bcadd($current, $txn->amount, 2)
                    : bcsub($current, $txn->amount, 2);
                $balanceAfter = $txn->balanceAfter ?? $computed;

                $rowId = (int) $db->table($table)->insertGetId([
                    'tenant_id' => $tenantId,
                    'account_row_id' => $accountRowId,
                    'transaction_date' => $txn->transactionDate,
                    'type' => $txn->type,
                    'code' => $txn->code,
                    'direction' => $txn->direction,
                    'amount' => $txn->amount,
                    'balance_after' => $balanceAfter,
                    'reference' => $txn->reference,
                    'description' => $txn->description,
                    'interest_run_row_id' => null,
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'row_id');

                $db->table($accountsTable)
                    ->where('row_id', $accountRowId)
                    ->update(['balance' => $balanceAfter, 'updated_at' => $now]);
                $balances[$accountRowId] = $balanceAfter;

                $db->table('legacy_record_mappings')->insert([
                    'tenant_id' => $tenantId,
                    'batch_row_id' => $batchRowId,
                    'source_table' => $sourceTable,
                    'source_id' => (string) $txn->legacyId,
                    'source_secondary_key' => 'savings_transaction',
                    'target_table' => 'savings_transactions',
                    'target_row_id' => $rowId,
                    'target_local_id' => $txn->legacyId,
                    'source_snapshot' => json_encode($txn->snapshot, JSON_THROW_ON_ERROR),
                    'migrated_at' => $now,
                    'created_at' => $now,
                ]);

                $inserted++;
            }
        }, 5);

        return ['inserted' => $inserted, 'skipped' => $skipped, 'warnings' => $warnings];
    }

    /**
     * Create a saldo awal mutation for accounts that have no legacy mutation rows.
     *
     * @param  list<NormalizedSavingsAccount>  $accounts
     */
    public function ensureOpeningTransactions(int $batchRowId, string $accountSourceTable, array $accounts): int
    {
        if ($accounts === []) {
            return 0;
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $transactionsTable = SavingsModule::table('transactions', $tenantId);
        $accountsTable = SavingsModule::table('accounts', $tenantId);
        $now = now()->format('Y-m-d H:i:s');
        $created = 0;

        $accountMap = DB::connection($connName)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $accountSourceTable)
            ->where('source_secondary_key', 'savings_account')
            ->pluck('target_row_id', 'source_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId, $batchRowId, $accountSourceTable, $accounts, $transactionsTable, $accountsTable, $now, $accountMap, &$created,
        ): void {
            foreach ($accounts as $account) {
                $accountRowId = $accountMap[$account->legacyId] ?? null;
                if ($accountRowId === null) {
                    continue;
                }

                $mapped = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $accountSourceTable)
                    ->where('source_id', (string) $account->legacyId)
                    ->where('source_secondary_key', 'opening_transaction')
                    ->exists();
                if ($mapped) {
                    continue;
                }

                $hasMutation = $db->table($transactionsTable)
                    ->where('tenant_id', $tenantId)
                    ->where('account_row_id', $accountRowId)
                    ->exists();
                if ($hasMutation || bccomp($account->openingAmount, '0.00', 2) === 0) {
                    continue;
                }

                $rowId = (int) $db->table($transactionsTable)->insertGetId([
                    'tenant_id' => $tenantId,
                    'account_row_id' => $accountRowId,
                    'transaction_date' => $account->openedAt,
                    'type' => 'saldo_awal',
                    'code' => 1,
                    'direction' => 'in',
                    'amount' => $account->openingAmount,
                    'balance_after' => $account->openingAmount,
                    'reference' => 'OPEN-'.$account->legacyId,
                    'description' => 'Saldo awal migrasi legacy',
                    'interest_run_row_id' => null,
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'row_id');

                $db->table($accountsTable)
                    ->where('row_id', $accountRowId)
                    ->update(['balance' => $account->openingAmount, 'updated_at' => $now]);

                $db->table('legacy_record_mappings')->insert([
                    'tenant_id' => $tenantId,
                    'batch_row_id' => $batchRowId,
                    'source_table' => $accountSourceTable,
                    'source_id' => (string) $account->legacyId,
                    'source_secondary_key' => 'opening_transaction',
                    'target_table' => 'savings_transactions',
                    'target_row_id' => $rowId,
                    'target_local_id' => $account->legacyId,
                    'source_snapshot' => json_encode($account->snapshot, JSON_THROW_ON_ERROR),
                    'migrated_at' => $now,
                    'created_at' => $now,
                ]);

                $created++;
            }
        }, 5);

        return $created;
    }

    public function syncAccountBalances(string $accountSourceTable): int
    {
        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $transactionsTable = SavingsModule::table('transactions', $tenantId);
        $accountsTable = SavingsModule::table('accounts', $tenantId);
        $now = now()->format('Y-m-d H:i:s');
        $updated = 0;

        $accountRowIds = DB::connection($connName)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $accountSourceTable)
            ->where('source_secondary_key', 'savings_account')
            ->pluck('target_row_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        foreach (array_chunk($accountRowIds, 200) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            $rows = DB::connection($connName)->table($transactionsTable)
                ->where('tenant_id', $tenantId)
                ->whereIn('account_row_id', $chunk)
                ->orderBy('account_row_id')
                ->orderBy('transaction_date')
                ->orderBy('row_id')
                ->get(['account_row_id', 'balance_after']);

            $last = [];
            foreach ($rows as $row) {
                $last[(int) $row->account_row_id] = (string) $row->balance_after;
            }

            foreach ($last as $accountRowId => $balance) {
                DB::connection($connName)->table($accountsTable)
                    ->where('row_id', $accountRowId)
                    ->where('tenant_id', $tenantId)
                    ->update(['balance' => $balance, 'updated_at' => $now]);
                $updated++;
            }
        }

        return $updated;
    }

    private function uniqueAccountNumber(
        ConnectionInterface $db,
        string $table,
        int $tenantId,
        string $base,
        int $legacyId,
    ): string {
        $base = trim($base);
        if ($base === '') {
            $base = 'S-'.$legacyId;
        }

        $candidate = $base;
        $counter = 1;
        while ($db->table($table)->where('tenant_id', $tenantId)->where('account_number', $candidate)->exists()) {
            $candidate = $base.'-'.(++$counter);
        }

        return $candidate;
    }
}
