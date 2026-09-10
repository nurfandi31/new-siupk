<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Domain\Membership\Models\Person;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuickMemberRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'nik' => ['required', 'digits:16', Rule::unique(Person::class, 'national_identity_number')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'name' => ['required', 'string', 'max:180'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'village_id' => ['required', 'integer', Rule::exists(OrganizationUnit::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('type', 'village')->where('is_active', true))],
        ];
    }

    public function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'name' => 'nama',
            'gender' => 'jenis kelamin',
            'village_id' => 'desa',
        ];
    }
}
