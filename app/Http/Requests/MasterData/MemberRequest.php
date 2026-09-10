<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Domain\Membership\Models\Person;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MemberRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $member = $this->route('member');

        return [
            'nik' => ['required', 'digits:16', Rule::unique(Person::class, 'national_identity_number')->where(fn ($query) => $query->where('tenant_id', $tenantId))->ignore($member?->person_row_id, 'row_id')],
            'name' => ['required', 'string', 'max:180'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'family_card_number' => ['nullable', 'digits:16'],
            'address' => ['required', 'string', 'max:5000'],
            'village_id' => ['required', 'integer', Rule::exists(OrganizationUnit::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('type', 'village')->where('is_active', true))],
            'registered_at' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['active', 'exited', 'deceased'])],
            'has_guarantor' => ['sometimes', 'boolean'],
            'guarantor_nik' => ['nullable', 'digits:16', Rule::requiredIf(fn () => $this->boolean('has_guarantor'))],
            'guarantor_name' => ['nullable', 'string', 'max:180', Rule::requiredIf(fn () => $this->boolean('has_guarantor'))],
            'guarantor_relationship' => ['nullable', 'string', 'max:50', Rule::requiredIf(fn () => $this->boolean('has_guarantor'))],
            'has_business' => ['sometimes', 'boolean'],
            'business_name' => ['nullable', 'string', 'max:180', Rule::requiredIf(fn () => $this->boolean('has_business'))],
            'business_description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'name' => 'nama',
            'gender' => 'jenis kelamin',
            'phone' => 'telepon',
            'address' => 'alamat',
            'status' => 'status',
            'has_guarantor' => 'punya penjamin',
            'has_business' => 'punya usaha',
            'business_description' => 'deskripsi usaha',
            'family_card_number' => 'nomor KK',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'village_id' => 'desa',
            'registered_at' => 'tanggal terdaftar',
            'guarantor_nik' => 'NIK penjamin',
            'guarantor_name' => 'nama penjamin',
            'guarantor_relationship' => 'hubungan dengan penjamin',
            'business_name' => 'nama usaha',
        ];
    }
}
