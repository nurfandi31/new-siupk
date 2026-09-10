<?php

declare(strict_types=1);

namespace App\Http\Requests\Access;

use App\Domain\Access\Models\Role;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TenantRoleRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        /** @var Role|null $targetRole */
        $targetRole = $this->route('role');
        $targetId = $targetRole?->row_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique(Role::class, 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($targetId, 'row_id'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama role',
            'code' => 'kode role',
            'description' => 'deskripsi',
            'permissions' => 'hak akses',
        ];
    }
}
