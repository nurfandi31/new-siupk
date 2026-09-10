<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class IdentityRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'url', 'max:255'],
            'timezone' => ['required', 'string', 'max:50'],
            'operational_start_date' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'legal_name' => 'nama legal',
            'short_name' => 'nama pendek',
            'registration_number' => 'nomor badan hukum',
            'tax_number' => 'NPWP',
            'address' => 'alamat',
            'phone' => 'telepon',
            'email' => 'email',
            'website' => 'website',
            'timezone' => 'zona waktu',
            'operational_start_date' => 'tanggal operasional',
        ];
    }
}
