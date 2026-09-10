<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

final class VillageRequest extends OrganizationUnitRequest
{
    public function rules(): array
    {
        return [
            'address' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'village_head_name' => ['nullable', 'string', 'max:180'],
            'village_head_phone' => ['nullable', 'string', 'max:20'],
            'village_head_nip' => ['nullable', 'string', 'max:30'],
            'village_secretary_name' => ['nullable', 'string', 'max:180'],
            'village_secretary_phone' => ['nullable', 'string', 'max:20'],
            'village_council_name' => ['nullable', 'string', 'max:180'],
            'installment_schedule' => ['nullable', 'string', 'max:100'],
            'village_naming_id' => [
                'required',
                'integer',
                Rule::exists('village_namings', 'row_id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', app(TenantContext::class)->id())
                        ->where('is_active', true),
                ),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'address' => 'alamat',
            'phone' => 'telepon',
            'village_head_name' => 'nama kepala desa',
            'village_head_phone' => 'telepon kepala desa',
            'village_head_nip' => 'NIP kepala desa',
            'village_secretary_name' => 'nama sekretaris desa',
            'village_secretary_phone' => 'telepon sekretaris desa',
            'village_council_name' => 'nama BPD',
            'installment_schedule' => 'jadwal angsuran',
            'village_naming_id' => 'jenis penamaan desa',
        ];
    }
}
