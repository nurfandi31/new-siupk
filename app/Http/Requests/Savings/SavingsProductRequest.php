<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use App\Domain\Savings\Models\SavingsProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavingsProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:12'],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(array_keys(SavingsProduct::CATEGORIES))],
            'form_type' => ['required', 'integer', 'in:1,2,3'],
            'interest_rate' => ['required', 'numeric', 'between:0,100'],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'admin_fee' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'opening_fee' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'minimum_balance' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
