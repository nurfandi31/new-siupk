<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'interest_rate' => ['required', 'numeric', 'between:0,100'],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'admin_fee' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'minimum_closing_date' => ['nullable', 'date'],
            'authority_type' => ['nullable', 'integer', 'in:1,2'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'institution_position' => ['nullable', 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
