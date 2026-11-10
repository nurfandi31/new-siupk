<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'member_row_id' => ['required', 'integer', 'min:1'],
            'product_row_id' => ['required', 'integer', 'min:1'],
            'opened_at' => ['required', 'date'],
            'opening_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'opening_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'minimum_closing_date' => ['nullable', 'date'],
            'interest_rate' => ['nullable', 'numeric', 'between:0,100'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'admin_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'authority_type' => ['nullable', 'integer', 'in:1,2'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'institution_position' => ['nullable', 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
