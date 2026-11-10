<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

final class SavingsSettingsRequest extends FormRequest
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
            'opening_fee' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'interest_day' => ['required', 'integer', 'between:-28,28'],
            'minimum_balance' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'minimum_taxable_interest' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'minimum_withdrawal_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'interest_method' => ['required', 'integer', 'in:1,2,3'],
            'allow_negative_balance' => ['boolean'],
            'default_product_code' => ['nullable', 'string', 'max:12'],
            'account_number_prefix' => ['nullable', 'string', 'max:12'],
        ];
    }
}
