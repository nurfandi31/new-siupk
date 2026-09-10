<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership;

use App\Domain\Migration\Membership\DTO\NormalizedGroupBundle;
use App\Domain\Migration\Membership\DTO\NormalizedMemberBundle;
use App\Domain\Migration\Support\LegacyConnection;
use App\Tenancy\Services\TenantGroupMasterDataProvisioner;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class MembershipMigrationPipeline
{
    public function __construct(
        private TenantContext $context,
        private LegacyConnection $legacy,
        private LegacyMembershipExtractor $extractor,
        private LegacyMembershipNormalizer $normalizer,
        private LegacyMemberLoader $memberLoader,
        private LegacyGroupLoader $groupLoader,
        private MembershipMigrationReconciler $reconciler,
        private TenantSequenceService $sequences,
        private TenantGroupMasterDataProvisioner $groupMasterData,
        private LegacyVillageProvisioner $villages,
    ) {}

    /**
     * @return array{
     *   dry_run: bool,
     *   batch_row_id: int|null,
     *   source_members: int,
     *   source_groups: int,
     *   would_insert_members: int,
     *   would_skip_members: int,
     *   would_insert_groups: int,
     *   would_skip_groups: int,
     *   inserted_members: int,
     *   inserted_groups: int,
     *   errors: list<string>,
     *   error_count: int,
     *   recon: list<array<string, mixed>>,
     *   status: string
     * }
     */
    public function run(
        string $suffix,
        bool $dryRun,
        int $chunk,
        bool $failFast,
        bool $skipMembers,
        bool $skipGroups,
        bool $skipReconcile,
    ): array {
        $suffix = (string) $suffix;
        if (! preg_match('/^\d+$/', $suffix)) {
            throw new RuntimeException('Suffix must be numeric.');
        }

        $anggotaTable = $this->legacy->anggotaTable($suffix);
        $kelompokTable = $this->legacy->kelompokTable($suffix);

        if (! $skipMembers && ! $this->legacy->tableExists($anggotaTable)) {
            throw new RuntimeException("Legacy table [{$anggotaTable}] not found.");
        }
        if (! $skipGroups && ! $this->legacy->tableExists($kelompokTable)) {
            throw new RuntimeException("Legacy table [{$kelompokTable}] not found.");
        }

        $this->groupMasterData->ensureDefaults();
        // Villages first (incl. custom desa) so normalizer can resolve kelompok.desa / anggota.desa.
        if (! $dryRun) {
            $this->villages->sync($suffix, backfill: false);
        }
        $this->normalizer->warmCaches();

        $sourceMembers = $skipMembers ? 0 : $this->extractor->activeAnggotaCount($suffix);
        $sourceGroups = $skipGroups ? 0 : $this->extractor->activeKelompokCount($suffix);

        $isMapped = function (string $sourceTable, string $sourceId, string $secondary = ''): bool {
            $tenantId = $this->context->id();
            $conn = (string) config('tenancy.tenant_connection', 'tenant');

            return DB::connection($conn)->table('legacy_record_mappings')
                ->where('tenant_id', $tenantId)
                ->where('source_table', $sourceTable)
                ->where('source_id', $sourceId)
                ->where('source_secondary_key', $secondary)
                ->exists();
        };

        $errors = [];
        $wouldMembers = 0;
        $wouldSkipMembers = 0;
        $wouldGroups = 0;
        $wouldSkipGroups = 0;

        if (! $skipMembers) {
            foreach ($this->extractor->anggotaChunks($suffix, $chunk) as $rows) {
                foreach ($rows as $row) {
                    $r = $this->normalizer->normalizeAnggota($row, $anggotaTable, $isMapped);
                    if ($r['skip']) {
                        $wouldSkipMembers++;

                        continue;
                    }
                    if ($r['error'] !== null) {
                        $errors[] = $r['error'];
                        if ($failFast) {
                            break 2;
                        }

                        continue;
                    }
                    $wouldMembers++;
                }
            }
        }

        if (! $skipGroups && ! ($failFast && $errors !== [])) {
            foreach ($this->extractor->kelompokChunks($suffix, $chunk) as $rows) {
                foreach ($rows as $row) {
                    $r = $this->normalizer->normalizeKelompok($row, $kelompokTable, $isMapped);
                    if ($r['skip']) {
                        $wouldSkipGroups++;

                        continue;
                    }
                    if ($r['error'] !== null) {
                        $errors[] = $r['error'];
                        if ($failFast) {
                            break 2;
                        }

                        continue;
                    }
                    $wouldGroups++;
                }
            }
        }

        $summary = [
            'dry_run' => $dryRun,
            'batch_row_id' => null,
            'source_members' => $sourceMembers,
            'source_groups' => $sourceGroups,
            'would_insert_members' => $wouldMembers,
            'would_skip_members' => $wouldSkipMembers,
            'would_insert_groups' => $wouldGroups,
            'would_skip_groups' => $wouldSkipGroups,
            'inserted_members' => 0,
            'inserted_groups' => 0,
            'errors' => array_slice($errors, 0, 50),
            'error_count' => count($errors),
            'warnings' => [],
            'warning_count' => 0,
            'recon' => [],
            'status' => 'pending',
        ];

        if ($dryRun) {
            $summary['status'] = $errors === [] ? 'dry_run_ok' : 'dry_run_failed';

            return $summary;
        }

        if ($failFast && $errors !== []) {
            $summary['status'] = 'failed';

            return $summary;
        }

        // Reset NIK seen set for second pass
        $this->normalizer->warmCaches();

        // Reserve local ids above legacy max so guarantor/child sequence next() never collides.
        $this->reserveSequences($suffix, $skipMembers, $skipGroups);

        $batchRowId = $this->createBatch($suffix);
        $summary['batch_row_id'] = $batchRowId;

        try {
            if (! $skipMembers) {
                foreach ($this->extractor->anggotaChunks($suffix, $chunk) as $rows) {
                    $chunkNormalized = [];
                    foreach ($rows as $row) {
                        $r = $this->normalizer->normalizeAnggota($row, $anggotaTable, $isMapped);
                        if ($r['skip'] || $r['error'] !== null || $r['ok'] === null) {
                            continue;
                        }
                        /** @var NormalizedMemberBundle $ok */
                        $ok = $r['ok'];
                        $chunkNormalized[] = $ok;
                    }
                    if ($chunkNormalized !== []) {
                        $load = $this->memberLoader->loadChunk($batchRowId, $anggotaTable, $chunkNormalized);
                        $summary['inserted_members'] += $load['inserted'];
                    }
                }
            }

            $warnings = [];
            if (! $skipGroups) {
                foreach ($this->extractor->kelompokChunks($suffix, $chunk) as $rows) {
                    $chunkNormalized = [];
                    foreach ($rows as $row) {
                        $r = $this->normalizer->normalizeKelompok($row, $kelompokTable, $isMapped);
                        if ($r['skip'] || $r['error'] !== null || $r['ok'] === null) {
                            continue;
                        }
                        /** @var NormalizedGroupBundle $ok */
                        $ok = $r['ok'];
                        $chunkNormalized[] = $ok;
                    }
                    if ($chunkNormalized !== []) {
                        $load = $this->groupLoader->loadChunk($batchRowId, $kelompokTable, $chunkNormalized);
                        $summary['inserted_groups'] += $load['inserted'];
                        foreach ($load['errors'] as $err) {
                            $errors[] = $err;
                        }
                        foreach ($load['warnings'] as $w) {
                            $warnings[] = $w;
                        }
                    }
                }
            }

            $this->bumpSequences();

            $summary['errors'] = array_slice($errors, 0, 50);
            $summary['error_count'] = count($errors);
            $summary['warnings'] = array_slice($warnings, 0, 50);
            $summary['warning_count'] = count($warnings);

            if (! $skipReconcile) {
                $summary['recon'] = $this->reconciler->run($batchRowId, $suffix);
                $ok = $this->reconciler->allCriticalMatched($summary['recon']) && $errors === [];
                $summary['status'] = $ok ? 'completed' : 'failed';
            } else {
                $summary['status'] = $errors === [] ? 'completed' : 'failed';
            }

            $this->finishBatch($batchRowId, $summary['status'], $summary);

            return $summary;
        } catch (\Throwable $e) {
            $this->finishBatch($batchRowId, 'failed', ['error' => $e->getMessage()] + $summary);
            throw $e;
        }
    }

    private function createBatch(string $suffix): int
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');

        return (int) DB::connection($conn)->table('legacy_migration_batches')->insertGetId([
            'tenant_id' => $tenantId,
            'public_id' => (string) Str::ulid(),
            'source_database' => (string) config('database.connections.legacy.database'),
            'source_suffix' => $suffix,
            'status' => 'running',
            'started_at' => $now,
            'completed_at' => null,
            'source_checksum' => null,
            'target_checksum' => null,
            'summary' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'row_id');
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function finishBatch(int $batchRowId, string $status, array $summary): void
    {
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        DB::connection($conn)->table('legacy_migration_batches')
            ->where('row_id', $batchRowId)
            ->update([
                'status' => $status,
                'completed_at' => $now,
                'summary' => json_encode($summary, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]);
    }

    private function reserveSequences(string $suffix, bool $skipMembers, bool $skipGroups): void
    {
        if (! $skipMembers && $this->legacy->tableExists($this->legacy->anggotaTable($suffix))) {
            $max = $this->maxLegacyId($this->legacy->anggotaTable($suffix), ['id', 'id_anggota', 'ida']);
            if ($max > 0) {
                $this->sequences->initializeAtLeast('people', $max + 1);
                $this->sequences->initializeAtLeast('members', $max + 1);
            }
        }
        if (! $skipGroups && $this->legacy->tableExists($this->legacy->kelompokTable($suffix))) {
            $max = $this->maxLegacyId($this->legacy->kelompokTable($suffix), ['id', 'id_kelompok', 'idk']);
            if ($max > 0) {
                $this->sequences->initializeAtLeast('groups', $max + 1);
            }
        }
    }

    /**
     * @param  list<string>  $candidates
     */
    private function maxLegacyId(string $table, array $candidates): int
    {
        $cols = $this->legacy->columns($table);
        $names = array_map(static fn (object $c): string => (string) $c->COLUMN_NAME, $cols);
        $idCol = null;
        foreach ($candidates as $c) {
            foreach ($names as $n) {
                if (strcasecmp($n, $c) === 0) {
                    $idCol = $n;
                    break 2;
                }
            }
        }
        if ($idCol === null) {
            return 0;
        }
        $row = $this->legacy->selectOne("SELECT MAX(`{$idCol}`) AS m FROM `{$table}`");

        return (int) ($row->m ?? 0);
    }

    private function bumpSequences(): void
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');

        foreach ([
            'people',
            'members',
            'member_addresses',
            'member_businesses',
            'member_guarantors',
            'groups',
            'group_members',
            'group_officers',
        ] as $table) {
            $max = (int) DB::connection($conn)->table($table)
                ->where('tenant_id', $tenantId)
                ->max('id');
            $this->sequences->initializeAtLeast($table, $max + 1);
        }
    }
}
