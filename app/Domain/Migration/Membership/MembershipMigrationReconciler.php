<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership;

use App\Domain\Migration\Support\LegacyConnection;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class MembershipMigrationReconciler
{
    public function __construct(
        private TenantContext $context,
        private LegacyConnection $legacy,
        private LegacyMembershipExtractor $extractor,
    ) {}

    /**
     * @return list<array{scope: string, status: string, source_count: int, target_count: int, details: array<string, mixed>}>
     */
    public function run(int $batchRowId, string $suffix): array
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $anggotaTable = $this->legacy->anggotaTable($suffix);
        $kelompokTable = $this->legacy->kelompokTable($suffix);
        $results = [];

        $sourceMembers = $this->extractor->activeAnggotaCount($suffix);
        $mappedMembers = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $anggotaTable)
            ->where('source_secondary_key', 'member')
            ->where('target_table', 'members')
            ->count();
        $memberRows = (int) DB::connection($conn)->table('members')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->count();

        $results[] = $this->persist($batchRowId, 'members', [
            'source_count' => $sourceMembers,
            'target_count' => $mappedMembers,
            'status' => $sourceMembers === $mappedMembers ? 'matched' : 'mismatch',
            'details' => [
                'legacy_active' => $sourceMembers,
                'mappings' => $mappedMembers,
                'members_table' => $memberRows,
            ],
        ]);

        $sourceGroups = $this->extractor->activeKelompokCount($suffix);
        $mappedGroups = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $kelompokTable)
            ->where('source_secondary_key', 'group')
            ->where('target_table', 'groups')
            ->count();
        $groupRows = (int) DB::connection($conn)->table('groups')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->count();

        $results[] = $this->persist($batchRowId, 'groups', [
            'source_count' => $sourceGroups,
            'target_count' => $mappedGroups,
            'status' => $sourceGroups === $mappedGroups ? 'matched' : 'mismatch',
            'details' => [
                'legacy_active' => $sourceGroups,
                'mappings' => $mappedGroups,
                'groups_table' => $groupRows,
            ],
        ]);

        // people with secondary person (1:1 with members)
        $mappedPeople = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $anggotaTable)
            ->where('source_secondary_key', 'person')
            ->count();
        $results[] = $this->persist($batchRowId, 'people', [
            'source_count' => $sourceMembers,
            'target_count' => $mappedPeople,
            'status' => $sourceMembers === $mappedPeople ? 'matched' : 'mismatch',
            'details' => ['mappings_person' => $mappedPeople],
        ]);

        return $results;
    }

    /**
     * @param  list<array{status: string}>  $results
     */
    public function allCriticalMatched(array $results): bool
    {
        foreach ($results as $r) {
            if (($r['status'] ?? '') !== 'matched') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{source_count: int, target_count: int, status: string, details: array<string, mixed>}  $data
     * @return array{scope: string, status: string, source_count: int, target_count: int, details: array<string, mixed>}
     */
    private function persist(int $batchRowId, string $scope, array $data): array
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');

        DB::connection($conn)->table('migration_reconciliation_results')->insert([
            'tenant_id' => $tenantId,
            'batch_row_id' => $batchRowId,
            'scope' => $scope,
            'period_start' => null,
            'period_end' => null,
            'source_count' => $data['source_count'],
            'target_count' => $data['target_count'],
            'source_debit' => null,
            'target_debit' => null,
            'source_credit' => null,
            'target_credit' => null,
            'source_balance' => null,
            'target_balance' => null,
            'status' => $data['status'],
            'difference_details' => json_encode($data['details'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'scope' => $scope,
            'status' => $data['status'],
            'source_count' => $data['source_count'],
            'target_count' => $data['target_count'],
            'details' => $data['details'],
        ];
    }
}
