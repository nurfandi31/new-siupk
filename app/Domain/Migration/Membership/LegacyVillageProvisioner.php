<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership;

use App\Domain\Migration\Support\LegacyConnection;
use App\Models\Tenant\OrganizationUnit;
use App\Models\Tenant\VillageNaming;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Seed organization_units (type=village) from villages actually used by a legacy tenant.
 *
 * Covers:
 * - standard BPS kd_desa on kelompok.desa / anggota.desa
 * - custom desa rows in legacy `desa` (any kd_kec)
 * - orphan codes present on kelompok/anggota but missing from `desa` master
 *
 * Does NOT depend on tenant.district_code or external regional API.
 */
final class LegacyVillageProvisioner
{
    public function __construct(
        private LegacyConnection $legacy,
        private TenantContext $context,
        private TenantSequenceService $sequences,
    ) {}

    /**
     * @return array{
     *   codes_seen: int,
     *   created: int,
     *   updated: int,
     *   linked_groups: int,
     *   linked_members: int,
     *   unmatched_groups: int,
     *   unmatched_members: int
     * }
     */
    public function sync(string $suffix, bool $backfill = true): array
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');

        $codes = $this->collectUsedCodes($suffix);
        $meta = $this->loadDesaMeta($codes);

        $namingId = $this->ensureVillageNaming();

        $created = 0;
        $updated = 0;
        $codeToRowId = [];

        DB::connection($conn)->transaction(function () use (
            $codes,
            $meta,
            $namingId,
            &$created,
            &$updated,
            &$codeToRowId,
        ): void {
            foreach ($codes as $code) {
                $info = $meta[$code] ?? null;
                $name = $info['name'] ?? ('Desa '.$code);
                $address = $info['address'] ?? null;
                $phone = $info['phone'] ?? null;

                $existing = OrganizationUnit::query()
                    ->villages()
                    ->where('code', $code)
                    ->first();

                if ($existing === null) {
                    $unit = OrganizationUnit::query()->create([
                        'id' => $this->sequences->next('organization_units'),
                        'code' => $code,
                        'name' => $name,
                        'type' => 'village',
                        'address' => $address,
                        'phone' => $phone,
                        'village_naming_id' => $namingId,
                        'parent_row_id' => null,
                        'is_active' => true,
                    ]);
                    $codeToRowId[$code] = (int) $unit->row_id;
                    $created++;
                } else {
                    $patch = [];
                    // Only fill empty name/address; don't clobber user edits with same code.
                    if (trim((string) $existing->name) === '' || str_starts_with((string) $existing->name, 'Desa ')) {
                        if ($name !== '' && $name !== (string) $existing->name) {
                            $patch['name'] = $name;
                        }
                    }
                    if ($existing->address === null && $address !== null) {
                        $patch['address'] = $address;
                    }
                    if ($existing->phone === null && $phone !== null) {
                        $patch['phone'] = $phone;
                    }
                    if ($existing->village_naming_id === null && $namingId !== null) {
                        $patch['village_naming_id'] = $namingId;
                    }
                    if ($patch !== []) {
                        $existing->update($patch);
                        $updated++;
                    }
                    $codeToRowId[$code] = (int) $existing->row_id;
                }
            }
        });

        // Refresh map from DB (includes pre-existing units)
        $units = DB::connection($conn)->table('organization_units')
            ->where('tenant_id', $tenantId)
            ->where('type', 'village')
            ->get(['row_id', 'code']);
        foreach ($units as $u) {
            $codeToRowId[strtolower(trim((string) $u->code))] = (int) $u->row_id;
            $compact = $this->compactCode((string) $u->code);
            if ($compact !== '' && $compact !== strtolower(trim((string) $u->code))) {
                $codeToRowId[$compact] = (int) $u->row_id;
            }
        }

        $linkedGroups = 0;
        $linkedMembers = 0;
        $unmatchedGroups = 0;
        $unmatchedMembers = 0;

        if ($backfill) {
            [$linkedGroups, $unmatchedGroups] = $this->backfillGroups($suffix, $codeToRowId);
            [$linkedMembers, $unmatchedMembers] = $this->backfillMembers($suffix, $codeToRowId);
        }

        return [
            'codes_seen' => count($codes),
            'created' => $created,
            'updated' => $updated,
            'linked_groups' => $linkedGroups,
            'linked_members' => $linkedMembers,
            'unmatched_groups' => $unmatchedGroups,
            'unmatched_members' => $unmatchedMembers,
        ];
    }

    /**
     * @return list<string> normalized compact codes
     */
    private function collectUsedCodes(string $suffix): array
    {
        $codes = [];

        foreach ([
            $this->legacy->kelompokTable($suffix),
            $this->legacy->anggotaTable($suffix),
        ] as $table) {
            if (! $this->legacy->tableExists($table)) {
                continue;
            }
            $rows = $this->legacy->select(
                "SELECT DISTINCT desa AS code FROM `{$table}` WHERE desa IS NOT NULL AND desa != '' AND desa != '0'"
            );
            foreach ($rows as $row) {
                $raw = trim((string) ($row->code ?? ''));
                if ($raw === '') {
                    continue;
                }
                $compact = $this->compactCode($raw);
                $codes[$compact !== '' ? $compact : strtolower($raw)] = true;
            }
        }

        // Also include every desa row that matches used codes (ensures names)
        // plus any custom desa under same district prefixes seen in data.
        ksort($codes);

        return array_keys($codes);
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, array{name: string, address: ?string, phone: ?string}>
     */
    private function loadDesaMeta(array $codes): array
    {
        $meta = [];
        if ($codes === [] || ! $this->legacy->tableExists('desa')) {
            return $meta;
        }

        // Match by kd_desa (compact) and kode_desa (dotted)
        $chunks = array_chunk($codes, 200);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $dotted = array_map(static function (string $c): string {
                // 10-digit BPS → XX.XX.XX.XXXX rough; keep raw if not 10
                if (preg_match('/^\d{10}$/', $c) === 1) {
                    return substr($c, 0, 2).'.'.substr($c, 2, 2).'.'.substr($c, 4, 2).'.'.substr($c, 6, 4);
                }

                return $c;
            }, $chunk);

            $rows = $this->legacy->select(
                "SELECT kd_desa, kode_desa, nama_desa, alamat_desa, telp_desa
                 FROM desa
                 WHERE kd_desa IN ({$placeholders})
                    OR REPLACE(kode_desa, '.', '') IN ({$placeholders})
                    OR kode_desa IN ({$placeholders})",
                array_merge($chunk, $chunk, $dotted),
            );

            foreach ($rows as $row) {
                $kd = $this->compactCode((string) ($row->kd_desa ?? ''));
                $kode = $this->compactCode((string) ($row->kode_desa ?? ''));
                $name = trim((string) ($row->nama_desa ?? ''));
                if ($name === '') {
                    $name = 'Desa '.($kd !== '' ? $kd : $kode);
                }
                $payload = [
                    'name' => $name,
                    'address' => $this->nullIfEmpty($row->alamat_desa ?? null),
                    'phone' => $this->nullIfEmpty($row->telp_desa ?? null),
                ];
                if ($kd !== '') {
                    $meta[$kd] = $payload;
                }
                if ($kode !== '' && $kode !== $kd) {
                    $meta[$kode] = $payload;
                }
            }
        }

        return $meta;
    }

    /**
     * @param  array<string, int>  $codeToRowId
     * @return array{0: int, 1: int} linked, unmatched
     */
    private function backfillGroups(string $suffix, array $codeToRowId): array
    {
        $table = $this->legacy->kelompokTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return [0, 0];
        }

        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $linked = 0;
        $unmatched = 0;

        $rows = $this->legacy->select("SELECT id, desa FROM `{$table}` WHERE desa IS NOT NULL AND desa != ''");
        foreach ($rows as $row) {
            $legacyId = (int) ($row->id ?? 0);
            $code = $this->compactCode((string) ($row->desa ?? ''));
            if ($legacyId <= 0 || $code === '') {
                continue;
            }
            $unitRowId = $codeToRowId[$code] ?? $codeToRowId[strtolower((string) $row->desa)] ?? null;
            if ($unitRowId === null) {
                $unmatched++;

                continue;
            }
            $n = DB::connection($conn)->table('groups')
                ->where('tenant_id', $tenantId)
                ->where('id', $legacyId)
                ->where(function ($q) use ($unitRowId): void {
                    $q->whereNull('organization_unit_row_id')
                        ->orWhere('organization_unit_row_id', '!=', $unitRowId);
                })
                ->update([
                    'organization_unit_row_id' => $unitRowId,
                    'updated_at' => now(),
                ]);
            $linked += $n;
        }

        return [$linked, $unmatched];
    }

    /**
     * @param  array<string, int>  $codeToRowId
     * @return array{0: int, 1: int}
     */
    private function backfillMembers(string $suffix, array $codeToRowId): array
    {
        $table = $this->legacy->anggotaTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return [0, 0];
        }

        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $linked = 0;
        $unmatched = 0;

        $rows = $this->legacy->select("SELECT id, desa FROM `{$table}` WHERE desa IS NOT NULL AND desa != ''");
        foreach ($rows as $row) {
            $legacyId = (int) ($row->id ?? 0);
            $code = $this->compactCode((string) ($row->desa ?? ''));
            if ($legacyId <= 0 || $code === '') {
                continue;
            }
            $unitRowId = $codeToRowId[$code] ?? $codeToRowId[strtolower((string) $row->desa)] ?? null;
            if ($unitRowId === null) {
                $unmatched++;

                continue;
            }
            $n = DB::connection($conn)->table('members')
                ->where('tenant_id', $tenantId)
                ->where('id', $legacyId)
                ->where(function ($q) use ($unitRowId): void {
                    $q->whereNull('organization_unit_row_id')
                        ->orWhere('organization_unit_row_id', '!=', $unitRowId);
                })
                ->update([
                    'organization_unit_row_id' => $unitRowId,
                    'updated_at' => now(),
                ]);
            $linked += $n;
        }

        return [$linked, $unmatched];
    }

    private function ensureVillageNaming(): ?int
    {
        $existing = VillageNaming::query()->active()->orderBy('row_id')->value('row_id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) VillageNaming::query()->create([
            'id' => $this->sequences->next('village_namings'),
            'code' => 'village',
            'village_name' => 'Desa',
            'village_head_name' => 'Kepala Desa',
            'is_active' => true,
        ])->row_id;
    }

    private function compactCode(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        // Keep pure digit codes (kd_desa); strip separators from dotted kode_desa.
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits !== '' ? $digits : strtolower($raw);
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '' || $s === '-' || $s === '0') {
            return null;
        }

        return $s;
    }
}
