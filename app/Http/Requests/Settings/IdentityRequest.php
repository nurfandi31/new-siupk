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
            'district_name' => ['nullable', 'string', 'max:150'],
            'regency_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'url', 'max:255'],
            'timezone' => ['required', 'string', 'max:50'],
            'operational_start_date' => ['nullable', 'date'],
            'manager_name' => ['nullable', 'string', 'max:150'],
            'manager_nik' => ['nullable', 'string', 'max:32'],
            'manager_title' => ['nullable', 'string', 'max:100'],
            'secretary_name' => ['nullable', 'string', 'max:150'],
            'secretary_nik' => ['nullable', 'string', 'max:32'],
            'secretary_title' => ['nullable', 'string', 'max:100'],
            'treasurer_name' => ['nullable', 'string', 'max:150'],
            'treasurer_nik' => ['nullable', 'string', 'max:32'],
            'treasurer_title' => ['nullable', 'string', 'max:100'],
            'verifier_name' => ['nullable', 'string', 'max:150'],
            'verifier_title' => ['nullable', 'string', 'max:100'],
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
            'district_name' => 'kecamatan',
            'regency_name' => 'kabupaten',
            'phone' => 'telepon',
            'email' => 'email',
            'website' => 'website',
            'timezone' => 'zona waktu',
            'operational_start_date' => 'tanggal operasional',
            'manager_name' => 'nama ketua',
            'manager_nik' => 'NIK ketua',
            'manager_title' => 'jabatan ketua',
            'secretary_name' => 'nama sekretaris',
            'secretary_nik' => 'NIK sekretaris',
            'secretary_title' => 'jabatan sekretaris',
            'treasurer_name' => 'nama bendahara',
            'treasurer_nik' => 'NIK bendahara',
            'treasurer_title' => 'jabatan bendahara',
            'verifier_name' => 'nama verifikator',
            'verifier_title' => 'jabatan verifikator',
        ];
    }
}
