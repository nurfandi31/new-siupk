<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest untuk tolak pinjaman KELOMPOK (status draft/verified -> rejected).
 *
 * Mirror SIUPK original: alasan_tolak tidak wajib form (hanya konfirmasi
 * Swal), tapi kita set required untuk audit trail yang lebih baik.
 */
final class LoanRejectRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'notes' => 'alasan tolak',
        ];
    }
}
