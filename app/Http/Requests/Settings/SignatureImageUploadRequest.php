<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Domain\Documents\Services\SignatureTemplateService;
use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SignatureImageUploadRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'report_key' => [
                'required',
                'string',
                Rule::in(array_keys(SignatureTemplateService::REPORT_TYPES)),
            ],
            'image' => [
                'required',
                'string',
                'regex:/^data:image\/(png|jpeg|jpg|webp);base64,/',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'report_key' => 'jenis dokumen',
            'image' => 'gambar tanda tangan',
        ];
    }
}
