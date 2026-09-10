<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class ManualOpeningBalanceRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_row_id' => ['required', 'integer', 'min:1'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'fiscal_year' => 'tahun fiskal',
            'lines' => 'baris saldo awal',
            'lines.*.account_row_id' => 'akun',
            'lines.*.debit' => 'debit',
            'lines.*.credit' => 'kredit',
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.account_row_id.required' => 'Akun pada baris :position harus dipilih.',
            'lines.*.account_row_id.min' => 'Akun pada baris :position tidak valid.',
        ];
    }
}
