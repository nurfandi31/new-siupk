<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership;

use App\Domain\Migration\Membership\DTO\NormalizedGroupBundle;
use App\Domain\Migration\Membership\DTO\NormalizedMemberBundle;
use App\Domain\Migration\Membership\Support\LegacyRow;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class LegacyMembershipNormalizer
{
    /** @var array<string, int> name/code lower => row_id */
    private array $unitsByCode = [];

    /** @var array<string, int> */
    private array $unitsByName = [];

    private ?int $defaultBusinessTypeId = null;

    private ?int $defaultActivityTypeId = null;

    private ?int $defaultGroupLevelId = null;

    private ?int $defaultGroupFunctionId = null;

    /** @var array<string, true> */
    private array $seenNik = [];

    public function __construct(
        private TenantContext $context,
    ) {}

    public function warmCaches(): void
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');

        $units = DB::connection($conn)->table('organization_units')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['row_id', 'code', 'name']);

        $this->unitsByCode = [];
        $this->unitsByName = [];
        foreach ($units as $u) {
            $code = strtolower(trim((string) $u->code));
            $name = strtolower(trim((string) $u->name));
            if ($code !== '') {
                $this->unitsByCode[$code] = (int) $u->row_id;
                // Also index digit-only form (kode_desa with dots → kd_desa).
                $compact = preg_replace('/\D+/', '', $code) ?? '';
                if ($compact !== '' && $compact !== $code) {
                    $this->unitsByCode[$compact] = (int) $u->row_id;
                }
            }
            if ($name !== '') {
                $this->unitsByName[$name] = (int) $u->row_id;
            }
        }

        $this->defaultBusinessTypeId = $this->firstActiveId('business_types');
        $this->defaultActivityTypeId = $this->firstActiveId('activity_types');
        $this->defaultGroupLevelId = $this->firstActiveId('group_levels');
        $this->defaultGroupFunctionId = $this->firstActiveId('group_functions');
        $this->seenNik = [];
    }

    /**
     * @return array{ok: NormalizedMemberBundle|null, error: string|null, skip: bool}
     */
    public function normalizeAnggota(object $row, string $sourceTable, callable $isMapped): array
    {
        if (LegacyRow::isDeleted($row)) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $legacyId = LegacyRow::int($row, ['id', 'id_anggota', 'ida']);
        if ($legacyId === null || $legacyId <= 0) {
            return ['ok' => null, 'error' => 'anggota missing id', 'skip' => false];
        }

        if ($isMapped($sourceTable, (string) $legacyId, 'member')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $fullName = LegacyRow::str($row, ['namadepan', 'nama', 'nama_lengkap', 'name', 'nm_anggota', 'nama_anggota']);
        if ($fullName === null || $fullName === '') {
            return ['ok' => null, 'error' => "anggota id={$legacyId}: missing name", 'skip' => false];
        }

        $rawNik = LegacyRow::str($row, ['nik', 'no_nik', 'no_ktp', 'ktp']);
        $nik = $this->normalizeNik($rawNik);
        // First occurrence keeps NIK; later dups drop NIK (loader also guards unique).
        if ($nik !== null) {
            if (isset($this->seenNik[$nik])) {
                $nik = null;
            } else {
                $this->seenNik[$nik] = true;
            }
        }

        $memberNumber = $nik ?? (string) $legacyId;
        $gender = $this->normalizeGender(LegacyRow::str($row, ['jk', 'jenis_kelamin', 'kelamin', 'gender', 'sex']));
        $registeredAt = LegacyRow::date($row, ['terdaftar', 'tgl_masuk', 'tanggal_masuk', 'tgl_daftar', 'registered_at', 'created_at', 'tgl'])
            ?? date('Y-m-d');
        $status = $this->normalizeMemberStatus(LegacyRow::str($row, ['status', 'stat', 'aktif', 'status_anggota']));

        $unitId = $this->resolveUnit(
            LegacyRow::str($row, ['desa', 'kode_desa', 'desa_kode', 'kd_desa', 'id_desa', 'desa_id', 'kelurahan', 'nama_desa'])
        );

        $businessName = LegacyRow::str($row, ['usaha', 'nama_usaha', 'jenis_usaha', 'nm_usaha']);
        $guarantorName = LegacyRow::str($row, ['penjamin', 'nama_penjamin', 'nm_penjamin']);
        $guarantorNik = $this->normalizeNik(LegacyRow::str($row, ['nik_penjamin', 'no_nik_penjamin', 'ktp_penjamin']));
        $guarantorRel = $this->normalizeHubungan(LegacyRow::str($row, ['hubungan', 'hubungan_penjamin', 'relasi_penjamin', 'hub_penjamin']));

        $phone = LegacyRow::str($row, ['hp', 'no_hp', 'telpon', 'telepon', 'phone', 'no_telp', 'wa']);
        // legacy often stores placeholder "08"
        if ($phone !== null && preg_match('/^0?8$/', $phone) === 1) {
            $phone = null;
        }

        return [
            'ok' => new NormalizedMemberBundle(
                legacyId: $legacyId,
                fullName: $fullName,
                nik: $nik,
                memberNumber: $memberNumber,
                gender: $gender,
                birthPlace: LegacyRow::str($row, ['tempat_lahir', 'tmpt_lahir', 'birth_place']),
                birthDate: LegacyRow::date($row, ['tgl_lahir', 'tanggal_lahir', 'birth_date']),
                phone: $phone,
                familyCardNumber: $this->normalizeNik(LegacyRow::str($row, ['kk', 'no_kk', 'nokk', 'family_card_number'])),
                address: LegacyRow::str($row, ['alamat', 'alamat_lengkap', 'address']),
                organizationUnitRowId: $unitId,
                registeredAt: $registeredAt,
                status: $status,
                businessName: $businessName,
                businessDescription: LegacyRow::str($row, ['ket_usaha', 'deskripsi_usaha', 'keterangan_usaha']),
                guarantorName: $guarantorName,
                guarantorNik: $guarantorNik,
                guarantorRelationship: $guarantorRel,
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * @return array{ok: NormalizedGroupBundle|null, error: string|null, skip: bool}
     */
    public function normalizeKelompok(object $row, string $sourceTable, callable $isMapped): array
    {
        if (LegacyRow::isDeleted($row)) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $legacyId = LegacyRow::int($row, ['id', 'id_kelompok', 'idk']);
        if ($legacyId === null || $legacyId <= 0) {
            return ['ok' => null, 'error' => 'kelompok missing id', 'skip' => false];
        }

        if ($isMapped($sourceTable, (string) $legacyId, 'group')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $name = LegacyRow::str($row, ['nama_kelompok', 'nama', 'nm_kelompok', 'name', 'kelompok']);
        if ($name === null || $name === '') {
            return ['ok' => null, 'error' => "kelompok id={$legacyId}: missing name", 'skip' => false];
        }

        $code = LegacyRow::str($row, ['kd_kelompok', 'kode', 'kode_kelompok', 'code']) ?? (string) $legacyId;
        $unitId = $this->resolveUnit(
            LegacyRow::str($row, ['desa', 'kode_desa', 'desa_kode', 'kd_desa', 'id_desa', 'desa_id', 'nama_desa'])
        );

        $memberIds = $this->parseMemberIdList(LegacyRow::str($row, [
            'anggota', 'id_anggota', 'anggota_ids', 'list_anggota', 'id_anggotas', 'members',
        ]));

        // Single-member FK style
        $singleMember = LegacyRow::int($row, ['id_anggota', 'anggota_id']);
        if ($singleMember !== null && $singleMember > 0 && ! in_array($singleMember, $memberIds, true)) {
            $memberIds[] = $singleMember;
        }

        $officers = [];
        foreach ([
            'chair' => ['ketua', 'id_ketua', 'ketua_id', 'nm_ketua', 'nama_ketua'],
            'secretary' => ['sekretaris', 'id_sekretaris', 'sekretaris_id', 'nm_sekretaris', 'nama_sekretaris'],
            'treasurer' => ['bendahara', 'id_bendahara', 'bendahara_id', 'nm_bendahara', 'nama_bendahara'],
        ] as $position => $aliases) {
            $v = LegacyRow::str($row, $aliases);
            if ($v === null || $v === '0' || $v === '-') {
                continue;
            }
            $officers[$position] = is_numeric($v) ? (int) $v : $v;
        }

        $status = $this->normalizeGroupStatus(LegacyRow::str($row, ['status', 'stat', 'aktif', 'online']));

        $phone = LegacyRow::str($row, ['telpon', 'telepon', 'no_hp', 'phone', 'hp']);
        if ($phone !== null && preg_match('/^0?8$/', $phone) === 1) {
            $phone = null;
        }

        return [
            'ok' => new NormalizedGroupBundle(
                legacyId: $legacyId,
                code: $code,
                name: $name,
                address: LegacyRow::str($row, ['alamat_kelompok', 'alamat', 'address']),
                phone: $phone,
                establishedAt: LegacyRow::date($row, ['tgl_berdiri', 'tanggal_berdiri', 'tgl_diri', 'established_at', 'tgl']),
                status: $status,
                organizationUnitRowId: $unitId,
                businessTypeRowId: $this->defaultBusinessTypeId,
                activityTypeRowId: $this->defaultActivityTypeId,
                groupLevelRowId: $this->defaultGroupLevelId,
                groupFunctionRowId: $this->defaultGroupFunctionId,
                memberLegacyIds: $memberIds,
                officers: $officers,
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    private function normalizeHubungan(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        // legacy often stores numeric codes
        $map = [
            '1' => 'Suami/Istri',
            '2' => 'Orang Tua',
            '3' => 'Anak',
            '4' => 'Saudara',
            '5' => 'Lainnya',
        ];
        $key = trim($raw);
        if (isset($map[$key])) {
            return $map[$key];
        }

        return $raw;
    }

    private function normalizeNik(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) !== 16) {
            return null;
        }

        return $digits;
    }

    private function normalizeGender(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $v = strtoupper(trim($raw));
        if (in_array($v, ['L', 'LAKI', 'LAKI-LAKI', 'LAKI LAKI', 'PRIA', 'M', 'MALE', '1'], true)) {
            return 'L';
        }
        if (in_array($v, ['P', 'PEREMPUAN', 'WANITA', 'F', 'FEMALE', '2'], true)) {
            return 'P';
        }
        if (str_starts_with($v, 'L')) {
            return 'L';
        }
        if (str_starts_with($v, 'P')) {
            return 'P';
        }

        return null;
    }

    private function normalizeMemberStatus(?string $raw): string
    {
        if ($raw === null) {
            return 'active';
        }
        $v = strtolower(trim($raw));
        if (in_array($v, ['1', 'a', 'aktif', 'active', 'ya', 'y', 'true'], true)) {
            return 'active';
        }
        if (in_array($v, ['keluar', 'exited', 'exit', 'nonaktif', 'inactive', '0', 'tidak', 'n'], true)) {
            return 'exited';
        }
        if (in_array($v, ['meninggal', 'deceased', 'wafat', 'mati'], true)) {
            return 'deceased';
        }

        return 'active';
    }

    private function normalizeGroupStatus(?string $raw): string
    {
        if ($raw === null) {
            return 'active';
        }
        $v = strtolower(trim($raw));
        if (in_array($v, ['0', 'nonaktif', 'inactive', 'bubar', 'tutup'], true)) {
            return 'inactive';
        }

        return 'active';
    }

    private function resolveUnit(?string $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $key = strtolower(trim($raw));
        if (isset($this->unitsByCode[$key])) {
            return $this->unitsByCode[$key];
        }
        // Compact BPS / custom: strip non-digits (kode_desa dotted → kd_desa)
        $compact = preg_replace('/\D+/', '', $key) ?? '';
        if ($compact !== '' && isset($this->unitsByCode[$compact])) {
            return $this->unitsByCode[$compact];
        }
        if (isset($this->unitsByName[$key])) {
            return $this->unitsByName[$key];
        }
        // numeric may equal organization_units.id (legacy local id) OR code
        if (ctype_digit($key) || $compact !== '') {
            $tenantId = $this->context->id();
            $conn = (string) config('tenancy.tenant_connection', 'tenant');
            $needle = $compact !== '' ? $compact : $key;
            $row = DB::connection($conn)->table('organization_units')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($needle, $key): void {
                    $q->where('code', $needle)
                        ->orWhere('code', $key)
                        ->orWhere('id', (int) $needle);
                })
                ->value('row_id');
            if ($row !== null) {
                return (int) $row;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function parseMemberIdList(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        // JSON array
        if (str_starts_with($raw, '[') || str_starts_with($raw, '{')) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $ids = [];
                    foreach ($decoded as $item) {
                        if (is_numeric($item)) {
                            $ids[] = (int) $item;
                        } elseif (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                            $ids[] = (int) $item['id'];
                        }
                    }

                    return array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0)));
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        $parts = preg_split('/[\s,;|]+/', $raw) ?: [];
        $ids = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && ctype_digit($p)) {
                $ids[] = (int) $p;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0)));
    }

    private function firstActiveId(string $table): ?int
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $id = DB::connection($conn)->table($table)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('row_id')
            ->value('row_id');

        return $id !== null ? (int) $id : null;
    }
}
