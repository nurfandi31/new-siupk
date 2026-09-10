<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class LoanRescheduleCancelRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => 'alasan pembatalan',
        ];
    }
}
