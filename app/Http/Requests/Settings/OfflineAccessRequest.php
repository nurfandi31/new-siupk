<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class OfflineAccessRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'user_id' => ['nullable', 'integer', 'exists:App\Models\User,row_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'Pengguna yang dipilih tidak ditemukan.',
        ];
    }
}
