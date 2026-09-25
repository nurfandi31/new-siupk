<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest untuk validasi pelunasan (Complete) pinjaman KELOMPOK
 * (status active/disbursed -> completed).
 *
 * Mirror SIUPK original: tgl_lunas required + director-only,
 * notes opsional. Flag `force=true` diizinkan untuk pelunasan administratif
 * walaupun principal_remaining > 0.
 */
final class LoanCompleteRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'completed_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'completed_at' => 'tanggal pelunasan',
            'notes' => 'catatan pelunasan',
            'force' => 'paksa pelunasan administratif',
        ];
    }
}
