<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Domain\Documents\Services\SignatureTemplateService;
use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class SignaturesRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $keys = array_keys(SignatureTemplateService::REPORT_TYPES);

        return [
            'templates' => [
                'required',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($keys): void {
                    if (! is_array($value)) {
                        return;
                    }
                    $unknown = array_diff(array_keys($value), $keys);
                    if ($unknown !== []) {
                        $fail('Jenis laporan tidak dikenal: '.implode(', ', $unknown).'.');
                    }
                },
            ],
            'templates.*' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'templates' => 'template tanda tangan',
        ];
    }
}
