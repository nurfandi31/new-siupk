<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

final class SavingsInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'cif' => ['nullable', 'string', 'max:500'],
            'posting_date' => ['nullable', 'date'],
        ];
    }
}
