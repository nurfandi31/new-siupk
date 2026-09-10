<?php

declare(strict_types=1);

namespace App\Http\Requests\Access;

use App\Domain\Membership\Models\Member;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateTenantUserRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        /** @var User|null $targetUser */
        $targetUser = $this->route('user');
        $targetId = $targetUser?->row_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique(User::class, 'username')->ignore($targetId, 'row_id')],
            'email' => ['nullable', 'email', 'max:150', Rule::unique(User::class, 'email')->ignore($targetId, 'row_id')],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+?62|0)8\d{7,12}$/', Rule::unique(User::class, 'phone')->ignore($targetId, 'row_id')],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'appointed_at' => ['nullable', 'date'],
            'term_end_at' => ['nullable', 'date', 'after_or_equal:appointed_at'],
            'role' => ['nullable', 'string', 'max:80'],
            'member_row_id' => ['required_if:role,anggota', 'nullable', 'integer', Rule::exists(Member::class, 'row_id')],
            'is_village_user' => ['nullable', 'boolean'],
            'village_row_id' => ['nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'username' => 'username',
            'email' => 'email',
            'phone' => 'nomor HP (WhatsApp)',
            'password' => 'password',
            'status' => 'status',
            'appointed_at' => 'mulai menjabat',
            'term_end_at' => 'selesai menjabat',
            'role' => 'role',
            'member_row_id' => 'anggota terhubung',
            'is_village_user' => 'operator desa',
            'village_row_id' => 'desa',
        ];
    }
}
