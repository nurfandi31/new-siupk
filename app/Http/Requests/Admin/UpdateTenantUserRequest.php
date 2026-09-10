<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('role') === '') {
            $this->merge(['role' => null]);
        }
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->row_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($userId, 'row_id')],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($userId, 'row_id')],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+?62|0)8\d{7,12}$/', Rule::unique('users', 'phone')->ignore($userId, 'row_id')],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'role' => ['nullable', 'string', Rule::in(array_keys(config('permissions.roles', [])))],
            'is_village_user' => ['nullable', 'boolean'],
            'village_row_id' => ['nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'username' => 'username',
            'email' => 'email',
            'phone' => 'nomor HP (WhatsApp)',
            'password' => 'password',
            'status' => 'status',
            'role' => 'role',
            'village_row_id' => 'desa',
        ];
    }
}
