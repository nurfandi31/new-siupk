<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

use App\Models\Platform\Tenant;
use App\Tenancy\Services\TenantWorkbench;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TenantSnapshotService
{
    /**
     * Tables in dependency order (topological sort for FK insertions on SQLite).
     *
     * @var array<int, string>
     */
    public const TABLES_IN_ORDER = [
        'tenant_registry',
        'tenant_sequences',
        'tenant_settings',
        'organization_profiles',
        'organization_units',
        'village_namings',
        'business_types',
        'activity_types',
        'group_levels',
        'group_functions',
        'roles',
        'user_roles',
        'member_user_links',
        'people',
        'members',
        'member_addresses',
        'member_businesses',
        'member_guarantors',
        'groups',
        'group_members',
        'group_officers',
        'accounts',
        'fiscal_periods',
        'account_opening_balances',
        'account_monthly_balances',
        'journal_entries',
        'journal_lines',
        'loan_products',
        'loans',
        'loan_borrowers',
        'loan_beneficiaries',
        'loan_committee',
        'loan_installments',
        'loan_installment_tracking',
        'loan_payments',
        'loan_payment_allocations',
        'loan_status_histories',
        'loan_write_offs',
        'loan_beneficiary_write_offs',
        'asset_categories',
        'assets',
        'asset_status_histories',
        'budgets',
        'budget_lines',
        'documents',
    ];

    public function __construct(
        private readonly TenantWorkbench $workbench,
        private readonly TenantContext $context,
    ) {}

    /**
     * Export full or delta snapshot for the specified tenant.
     *
     * @return array<string, mixed>
     */
    public function export(Tenant $tenant, ?string $since = null): array
    {
        if ($this->context->isInitialized() && $this->context->id() === (int) $tenant->row_id) {
            return $this->buildPayload($tenant, $since);
        }

        return $this->workbench->run($tenant, fn (Tenant $t) => $this->buildPayload($t, $since));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Tenant $tenant, ?string $since = null): array
    {
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $tenantId = (int) $tenant->row_id;
        $schema = Schema::connection($connectionName);

        $data = [];
        $tableCounts = [];
        $totalRecords = 0;

        $sinceCarbon = null;
        if ($since !== null && trim($since) !== '') {
            try {
                $sinceCarbon = Carbon::parse($since);
            } catch (\Throwable) {
                $sinceCarbon = null;
            }
        }

        foreach (self::TABLES_IN_ORDER as $tableName) {
            if (! $schema->hasTable($tableName)) {
                continue;
            }

            $query = DB::connection($connectionName)->table($tableName);

            if ($tableName === 'tenant_registry') {
                $query->where('id', $tenantId);
            } else {
                if ($schema->hasColumn($tableName, 'tenant_id')) {
                    $query->where('tenant_id', $tenantId);
                }
            }

            if ($sinceCarbon !== null && $schema->hasColumn($tableName, 'updated_at')) {
                $query->where(function ($q) use ($sinceCarbon, $schema, $tableName): void {
                    $q->where('updated_at', '>=', $sinceCarbon->toDateTimeString());
                    if ($schema->hasColumn($tableName, 'created_at')) {
                        $q->orWhere('created_at', '>=', $sinceCarbon->toDateTimeString());
                    }
                });
            }

            $rows = $query->get()->map(function ($row): array {
                $arr = (array) $row;

                return $this->normalizeRowValues($arr);
            })->all();

            $count = count($rows);
            $data[$tableName] = $rows;
            $tableCounts[$tableName] = $count;
            $totalRecords += $count;
        }

        $jsonEncoded = (string) json_encode($data);
        $checksum = hash('sha256', $jsonEncoded);

        return [
            'format' => 'siupk-desktop-snapshot-v1',
            'type' => $sinceCarbon !== null ? 'delta' : 'full',
            'since' => $sinceCarbon?->toIso8601String(),
            'generated_at' => now()->toIso8601String(),
            'tenant' => [
                'id' => (int) $tenant->row_id,
                'code' => (string) $tenant->code,
                'name' => (string) $tenant->name,
                'status' => (string) $tenant->status,
                'district_code' => $tenant->district_code,
                'regency_code' => $tenant->regency_code,
                'regency_name' => $tenant->regency_name,
                'province_code' => $tenant->province_code,
            ],
            'meta' => [
                'total_tables' => count($data),
                'total_records' => $totalRecords,
                'table_counts' => $tableCounts,
                'checksum' => $checksum,
                'tables_order' => self::TABLES_IN_ORDER,
            ],
            'data' => $data,
        ];
    }

    /**
     * Normalize types for JSON transport.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRowValues(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if ($value === null) {
                $normalized[$key] = null;
            } elseif (is_int($value) || is_float($value) || is_bool($value)) {
                $normalized[$key] = $value;
            } else {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }
}
