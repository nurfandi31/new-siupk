<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class WhatsappRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'pairing_phone' => ['nullable', 'string', 'max:20'],
            'template_billing' => ['nullable', 'string', 'max:2000'],
            'template_installment' => ['nullable', 'string', 'max:2000'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'pairing_phone' => 'nomor WhatsApp',
            'template_billing' => 'pesan tagihan',
            'template_installment' => 'pesan angsuran',
            'is_enabled' => 'Aktif',
        ];
    }
}
