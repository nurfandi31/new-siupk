<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavingsTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['setoran', 'penarikan', 'koreksi'])],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999999'],
            'transaction_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
