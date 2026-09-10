<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Models\Tenant\ActivityType;
use App\Models\Tenant\BusinessType;
use App\Models\Tenant\GroupFunction;
use App\Models\Tenant\GroupLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class TenantGroupMasterDataProvisioner
{
    public function ensureDefaults(): void
    {
        DB::connection('tenant')->transaction(function (): void {
            BusinessType::query()->whereIn('code', ['agriculture', 'livestock', 'fishery', 'trade', 'home_industry', 'service', 'craft'])->update(['is_active' => false]);
            $this->ensure(BusinessType::class, [
                'various_businesses' => 'Aneka Usaha',
                'joint_business' => 'Usaha Bersama',
            ]);
            ActivityType::query()->whereIn('code', ['spp', 'uep', 'production', 'marketing'])->update(['is_active' => false]);
            $this->ensure(ActivityType::class, [
                'agriculture' => 'Pertanian',
                'livestock' => 'Peternakan',
                'fishery' => 'Perikanan',
                'trade' => 'Perdagangan',
                'home_industry' => 'Industri Rumah Tangga',
                'service' => 'Jasa',
                'craft' => 'Kerajinan',
            ]);
            GroupLevel::query()->where('code', 'independent')->update(['is_active' => false]);
            $this->ensure(GroupLevel::class, [
                'beginner' => 'Pemula',
                'developing' => 'Berkembang',
                'ready' => 'Siap',
            ]);
            GroupFunction::query()->whereIn('code', ['business', 'savings_loan', 'social', 'production'])->update(['is_active' => false]);
            $this->ensure(GroupFunction::class, [
                'channeling' => 'Channeling',
                'executing' => 'Executing',
            ]);
        });
    }

    /** @param class-string<Model> $model */
    private function ensure(string $model, array $values): void
    {
        foreach ($values as $code => $name) {
            $model::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
