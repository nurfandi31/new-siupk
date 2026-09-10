<?php

declare(strict_types=1);

namespace App\Domain\Membership\Services;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\ActivityType;
use App\Models\Tenant\BusinessType;
use App\Models\Tenant\GroupFunction;
use App\Models\Tenant\GroupLevel;
use App\Models\Tenant\OrganizationUnit;
use App\Support\Csv;
use App\Tenancy\Services\TenantGroupMasterDataProvisioner;
use App\Tenancy\Services\TenantSequenceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MasterDataCsvService
{
    public function __construct(
        private readonly MemberService $members,
        private readonly TenantGroupMasterDataProvisioner $groupMasterData,
        private readonly TenantSequenceService $sequences,
    ) {}

    public function exportMembers(): StreamedResponse
    {
        $rows = Member::query()
            ->with(['person', 'village', 'address'])
            ->orderBy('row_id')
            ->lazyById(200, 'row_id')
            ->map(fn (Member $member): array => [
                $member->person?->national_identity_number,
                $member->person?->full_name,
                $member->person?->gender,
                $member->address?->address,
                $member->village?->name,
                $member->person?->phone,
                $member->status,
            ]);

        return Csv::download('anggota.csv', [
            'nik', 'nama', 'jenis_kelamin', 'alamat', 'desa', 'no_hp', 'status',
        ], $rows);
    }

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function importMembers(UploadedFile $file, int $userId): array
    {
        [, $rows] = Csv::read($file);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $line = (int) ($row['_line'] ?? 0);
            $nik = preg_replace('/\D+/', '', $row['nik'] ?? '') ?? '';
            $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));
            $gender = strtoupper(trim((string) ($row['jenis_kelamin'] ?? $row['gender'] ?? '')));
            $address = trim((string) ($row['alamat'] ?? $row['address'] ?? ''));
            $villageName = trim((string) ($row['desa'] ?? $row['village'] ?? ''));
            $phone = trim((string) ($row['no_hp'] ?? $row['phone'] ?? ''));
            $status = strtolower(trim((string) ($row['status'] ?? 'active'))) ?: 'active';

            if ($nik === '' && $name === '') {
                $skipped++;

                continue;
            }

            if (strlen($nik) !== 16) {
                $errors[] = "Baris {$line}: NIK harus 16 digit.";

                continue;
            }
            if ($name === '') {
                $errors[] = "Baris {$line}: Nama wajib diisi.";

                continue;
            }
            if (! in_array($gender, ['L', 'P'], true)) {
                $errors[] = "Baris {$line}: Jenis kelamin harus L atau P.";

                continue;
            }
            if ($address === '') {
                $errors[] = "Baris {$line}: Alamat wajib diisi.";

                continue;
            }
            if (! in_array($status, ['active', 'exited', 'deceased'], true)) {
                $status = 'active';
            }

            $village = $this->findVillage($villageName);
            if ($village === null) {
                $errors[] = "Baris {$line}: Desa \"{$villageName}\" tidak ditemukan.";

                continue;
            }

            $exists = Person::query()->where('national_identity_number', $nik)->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            try {
                $this->members->create([
                    'nik' => $nik,
                    'name' => $name,
                    'gender' => $gender,
                    'phone' => $phone !== '' ? $phone : null,
                    'address' => $address,
                    'village_id' => (int) $village->row_id,
                    'registered_at' => now()->toDateString(),
                    'status' => $status,
                    'has_guarantor' => false,
                    'has_business' => false,
                ], $userId);
                $imported++;
            } catch (\Throwable $exception) {
                $errors[] = "Baris {$line}: ".$exception->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    public function exportGroups(): StreamedResponse
    {
        $rows = Group::query()
            ->with('village:row_id,name')
            ->orderBy('row_id')
            ->lazyById(200, 'row_id')
            ->map(fn (Group $group): array => [
                $group->code,
                $group->name,
                $group->village?->name,
                $group->address,
                $group->phone,
                $group->established_at?->format('Y-m-d'),
                $group->status,
            ]);

        return Csv::download('kelompok.csv', [
            'kode', 'nama', 'desa', 'alamat', 'no_hp', 'tanggal_berdiri', 'status',
        ], $rows);
    }

    /**
     * Minimal group import: shell only (name + village). Officers/members diisi lewat form edit.
     *
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function importGroups(UploadedFile $file): array
    {
        $this->groupMasterData->ensureDefaults();
        [, $rows] = Csv::read($file);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $defaults = $this->groupDefaults();
        if ($defaults === null) {
            throw new RuntimeException('Master data kelompok belum tersedia. Buka form tambah kelompok sekali dulu.');
        }

        foreach ($rows as $row) {
            $line = (int) ($row['_line'] ?? 0);
            $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));
            $villageName = trim((string) ($row['desa'] ?? $row['village'] ?? ''));
            $address = trim((string) ($row['alamat'] ?? $row['address'] ?? ''));
            $phone = trim((string) ($row['no_hp'] ?? $row['phone'] ?? ''));
            $established = trim((string) ($row['tanggal_berdiri'] ?? $row['established_at'] ?? ''));
            $status = strtolower(trim((string) ($row['status'] ?? 'active'))) ?: 'active';

            if ($name === '') {
                $skipped++;

                continue;
            }

            $village = $this->findVillage($villageName);
            if ($village === null) {
                $errors[] = "Baris {$line}: Desa \"{$villageName}\" tidak ditemukan.";

                continue;
            }

            if (! in_array($status, ['active', 'inactive'], true)) {
                $status = 'active';
            }

            $exists = Group::query()
                ->where('name', $name)
                ->where('organization_unit_row_id', $village->row_id)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            try {
                $this->createGroupShell([
                    'name' => $name,
                    'village_id' => (int) $village->row_id,
                    'address' => $address !== '' ? $address : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'established_at' => $established !== '' ? $established : null,
                    'status' => $status,
                    ...$defaults,
                ]);
                $imported++;
            } catch (\Throwable $exception) {
                $errors[] = "Baris {$line}: ".$exception->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    public function exportInstitutions(): StreamedResponse
    {
        $rows = OrganizationUnit::query()
            ->otherInstitutions()
            ->with('parent:row_id,name')
            ->orderBy('row_id')
            ->lazyById(200, 'row_id')
            ->map(fn (OrganizationUnit $unit): array => [
                $unit->code,
                $unit->name,
                $unit->parent?->name,
                $unit->institution_identity_number,
                $unit->leader_name,
                $unit->responsible_name,
                $unit->address,
                $unit->phone,
                $unit->is_active ? 'aktif' : 'nonaktif',
            ]);

        return Csv::download('lembaga_lain.csv', [
            'kode', 'nama', 'desa', 'nomor_identitas', 'pimpinan', 'penanggungjawab', 'alamat', 'no_hp', 'status',
        ], $rows);
    }

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function importInstitutions(UploadedFile $file): array
    {
        [, $rows] = Csv::read($file);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $line = (int) ($row['_line'] ?? 0);
            $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));
            $villageName = trim((string) ($row['desa'] ?? $row['village'] ?? ''));
            $identity = trim((string) ($row['nomor_identitas'] ?? $row['institution_identity_number'] ?? ''));
            $leader = trim((string) ($row['pimpinan'] ?? $row['leader_name'] ?? ''));
            $responsible = trim((string) ($row['penanggungjawab'] ?? $row['responsible_name'] ?? ''));
            $address = trim((string) ($row['alamat'] ?? $row['address'] ?? ''));
            $phone = trim((string) ($row['no_hp'] ?? $row['phone'] ?? ''));
            $statusRaw = strtolower(trim((string) ($row['status'] ?? 'aktif')));

            if ($name === '') {
                $skipped++;

                continue;
            }
            if ($identity === '') {
                $errors[] = "Baris {$line}: Nomor identitas wajib diisi.";

                continue;
            }
            if ($leader === '') {
                $errors[] = "Baris {$line}: Nama pimpinan wajib diisi.";

                continue;
            }
            if ($responsible === '') {
                $errors[] = "Baris {$line}: Nama penanggungjawab wajib diisi.";

                continue;
            }

            $village = $this->findVillage($villageName);
            if ($village === null) {
                $errors[] = "Baris {$line}: Desa \"{$villageName}\" tidak ditemukan.";

                continue;
            }

            $exists = OrganizationUnit::query()
                ->otherInstitutions()
                ->where('institution_identity_number', $identity)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            $isActive = ! in_array($statusRaw, ['nonaktif', 'inactive', '0', 'false'], true);

            try {
                $this->createInstitution([
                    'parent_row_id' => (int) $village->row_id,
                    'name' => $name,
                    'address' => $address !== '' ? $address : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'institution_identity_number' => $identity,
                    'leader_name' => $leader,
                    'responsible_name' => $responsible,
                    'is_active' => $isActive,
                ]);
                $imported++;
            } catch (\Throwable $exception) {
                $errors[] = "Baris {$line}: ".$exception->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function findVillage(string $name): ?OrganizationUnit
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return OrganizationUnit::query()
            ->villages()
            ->active()
            ->where(fn ($q) => $q->where('name', $name)->orWhere('code', $name))
            ->first();
    }

    /**
     * @return array{business_type_id: int, activity_type_id: int, group_level_id: int, group_function_id: int}|null
     */
    private function groupDefaults(): ?array
    {
        $business = BusinessType::query()->where('is_active', true)->orderBy('row_id')->value('row_id');
        $activity = ActivityType::query()->where('is_active', true)->orderBy('row_id')->value('row_id');
        $level = GroupLevel::query()->where('is_active', true)->orderBy('row_id')->value('row_id');
        $function = GroupFunction::query()->where('is_active', true)->orderBy('row_id')->value('row_id');

        if (! $business || ! $activity || ! $level || ! $function) {
            return null;
        }

        return [
            'business_type_id' => (int) $business,
            'activity_type_id' => (int) $activity,
            'group_level_id' => (int) $level,
            'group_function_id' => (int) $function,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createGroupShell(array $data): Group
    {
        foreach (range(1, 3) as $attempt) {
            try {
                return DB::connection('tenant')->transaction(function () use ($data): Group {
                    $code = '';
                    foreach (range(1, 14) as $_) {
                        $code .= (string) random_int(0, 9);
                    }

                    return Group::query()->create([
                        'code' => $code,
                        'organization_unit_row_id' => $data['village_id'],
                        'business_type_row_id' => $data['business_type_id'],
                        'activity_type_row_id' => $data['activity_type_id'],
                        'group_level_row_id' => $data['group_level_id'],
                        'group_function_row_id' => $data['group_function_id'],
                        'name' => $data['name'],
                        'address' => $data['address'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'established_at' => $data['established_at'] ?? null,
                        'status' => $data['status'] ?? 'active',
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 3 || ! str_contains($exception->getMessage(), 'uq_groups_code')) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Gagal membuat kelompok.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createInstitution(array $attributes): OrganizationUnit
    {
        foreach (range(1, 3) as $attempt) {
            try {
                return DB::connection('tenant')->transaction(function () use ($attributes): OrganizationUnit {
                    $code = '';
                    foreach (range(1, 14) as $_) {
                        $code .= (string) random_int(0, 9);
                    }

                    return OrganizationUnit::query()->create([
                        ...$attributes,
                        'id' => $this->sequences->next('organization_units'),
                        'code' => $code,
                        'type' => 'other_institution',
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 3 || ! str_contains($exception->getMessage(), 'uq_org_units_code')) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Gagal membuat lembaga.');
    }
}
