<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class LoanWriteOffRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'written_off_at' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'written_off_at' => 'tanggal penghapusan',
            'reason' => 'alasan penghapusan',
        ];
    }
}
