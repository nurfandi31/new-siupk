<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload logo dari halaman SOP Lembaga.
 *
 * Logo tetap disimpan pada organization_profiles.logo_path agar seluruh kop
 * dokumen dan pelaporan memakai sumber logo yang sama.
 */
final class SopLogoUploadRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return ['logo' => 'logo'];
    }
}
