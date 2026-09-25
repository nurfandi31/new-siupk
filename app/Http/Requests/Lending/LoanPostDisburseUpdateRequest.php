<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest untuk edit post-cair (simpan_data di SIUPK).
 * Memungkinkan koreksi spk_no, disbursed_at, waktu, tempat setelah loan
 * dicairkan. Hanya tersedia di status active/disbursed.
 */
final class LoanPostDisburseUpdateRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'disbursed_at' => ['required', 'date', 'before_or_equal:today'],
            'spk_no' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('loans', 'spk_no')
                    ->ignore($this->route('loan')?->row_id, 'row_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'disbursement_slot' => ['nullable', 'string', 'max:120'],
            'disbursement_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'disbursed_at' => 'tanggal cair',
            'spk_no' => 'nomor SPK',
            'disbursement_slot' => 'waktu & tempat pencairan',
            'disbursement_notes' => 'catatan pencairan',
        ];
    }
}
