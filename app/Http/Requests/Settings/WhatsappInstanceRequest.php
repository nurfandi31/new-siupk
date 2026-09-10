<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class WhatsappInstanceRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'daily_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama instance',
            'phone_number' => 'nomor WhatsApp',
            'status' => 'status',
            'is_default' => 'default',
            'is_active' => 'aktif',
            'daily_limit' => 'batas harian',
        ];
    }
}
