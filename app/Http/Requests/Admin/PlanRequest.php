<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Platform\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    public function rules(): array
    {
        /** @var Plan|null $plan */
        $plan = $this->route('plan');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('plans', 'code')->ignore($plan?->row_id, 'row_id'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'price_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['IDR', 'USD'])],
            'billing_period' => ['required', Rule::in(['monthly', 'yearly'])],
            'is_active' => ['required', 'boolean'],
            'features' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama',
            'price_amount' => 'harga',
            'currency' => 'mata uang',
            'billing_period' => 'periode',
            'is_active' => 'status aktif',
        ];
    }
}
