<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

final class OtherInstitutionRequest extends OrganizationUnitRequest
{
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $unitId = $this->unitId();

        return [
            'parent_row_id' => [
                'required',
                'integer',
                Rule::exists('organization_units', 'row_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('type', 'village')->where('is_active', true)),
            ],
            'name' => ['required', 'string', 'max:180'],
            'address' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'institution_identity_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('organization_units', 'institution_identity_number')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('type', 'other_institution'))
                    ->ignore($unitId, 'row_id'),
            ],
            'leader_name' => ['required', 'string', 'max:180'],
            'responsible_name' => ['required', 'string', 'max:180'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'parent_row_id' => 'desa',
            'name' => 'nama lembaga',
            'address' => 'alamat',
            'phone' => 'telepon',
            'is_active' => 'status aktif',
            'institution_identity_number' => 'nomor identitas lembaga',
            'leader_name' => 'nama pimpinan',
            'responsible_name' => 'nama penanggungjawab',
        ];
    }
}
