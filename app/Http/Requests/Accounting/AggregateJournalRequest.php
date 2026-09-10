<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class AggregateJournalRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:5', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_row_id' => ['required', 'integer', 'min:1'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'transaction_date' => 'tanggal transaksi',
            'description' => 'keterangan',
            'lines' => 'baris jurnal',
            'lines.*.account_row_id' => 'akun',
            'lines.*.debit' => 'debit',
            'lines.*.credit' => 'kredit',
            'lines.*.description' => 'keterangan baris',
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_date.before_or_equal' => 'Tanggal jurnal agregat tidak boleh di masa depan.',
            'lines.min' => 'Jurnal agregat minimal harus memiliki 2 baris.',
            'lines.*.account_row_id.required' => 'Akun pada baris :position harus dipilih.',
            'lines.*.account_row_id.min' => 'Akun pada baris :position tidak valid.',
        ];
    }
}
