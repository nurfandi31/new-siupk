<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LendingSystemRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'products' => ['required', 'array', 'min:1'],
            'products.*.row_id' => ['required', 'integer'],
            'products.*.default_interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'products.*.default_term_months' => ['required', 'integer', 'min:1', 'max:240'],
            'products.*.rounding_method' => ['required', Rule::in(['decimal_2', 'rupiah_bersih', 'ceil_100', 'floor_100', '0', '100', '500', '1000', '5000', '10000', '50000'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'products' => 'produk pinjaman',
            'products.*.default_interest_rate' => 'default jasa',
            'products.*.default_term_months' => 'default jangka',
            'products.*.rounding_method' => 'metode pembulatan',
        ];
    }
}
