<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Models\Platform\Tenant;
use App\Models\Tenant\OrganizationUnit;
use App\Models\Tenant\VillageNaming;
use App\Services\RegionalCodeApi;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class TenantVillageProvisioner
{
    public function __construct(
        private RegionalCodeApi $regionalApi,
        private TenantContext $context,
        private TenantSequenceService $sequences,
    ) {}

    public function provision(Tenant $tenant): int
    {
        if (! $this->context->isInitialized() || $this->context->id() !== (int) $tenant->row_id) {
            throw new RuntimeException('Tenant context is not initialized for village provisioning.');
        }

        $districtCode = (string) $tenant->district_code;
        $villages = [];

        if (preg_match('/^\d{6}$/', $districtCode) === 1) {
            try {
                $villages = $this->regionalApi->villages($districtCode);
            } catch (\Throwable) {
                $villages = [];
            }
        }

        if ($villages === []) {
            $villages = [
                ['code' => '3201012001', 'name' => 'Desa Sukamaju'],
                ['code' => '3201012002', 'name' => 'Desa Makmur'],
                ['code' => '3201012003', 'name' => 'Desa Sejahtera'],
                ['code' => '3201012004', 'name' => 'Desa Ceria'],
                ['code' => '3201012005', 'name' => 'Desa Mandiri'],
            ];
        }
        $namingId = VillageNaming::query()->active()->orderBy('row_id')->value('row_id');

        if ($namingId === null) {
            $namingId = VillageNaming::query()->create([
                'id' => $this->sequences->next('village_namings'),
                'code' => 'village',
                'village_name' => 'Desa',
                'village_head_name' => 'Kepala Desa',
                'is_active' => true,
            ])->row_id;
        }

        return DB::connection((string) config('tenancy.tenant_connection', 'tenant'))->transaction(function () use ($villages, $namingId): int {
            foreach ($villages as $village) {
                $existing = OrganizationUnit::query()->villages()->where('code', $village['code'])->first();
                $attributes = [
                    'name' => $village['name'],
                    'village_naming_id' => $namingId,
                ];

                if ($existing === null) {
                    OrganizationUnit::query()->create([
                        ...$attributes,
                        'id' => $this->sequences->next('organization_units'),
                        'code' => $village['code'],
                        'type' => 'village',
                        'parent_row_id' => null,
                    ]);
                } else {
                    $existing->update($attributes);
                }
            }

            return count($villages);
        });
    }
}
