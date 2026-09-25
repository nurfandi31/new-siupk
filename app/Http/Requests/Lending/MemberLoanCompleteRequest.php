<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest untuk validasi pelunasan pinjaman INDIVIDU
 * (status active/disbursed -> completed).
 *
 * Saat ini identik dengan LoanCompleteRequest; dipisah agar future-proof
 * bila individu butuh aturan khusus (mis. validasi agunan released).
 */
final class MemberLoanCompleteRequest extends FormRequest
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
