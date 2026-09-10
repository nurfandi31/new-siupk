<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Domain\Accounting\Models\Account;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanDisburseRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'disbursed_at' => ['required', 'date', 'before_or_equal:today'],
            'disbursement_account_row_id' => ['required', 'integer', Rule::exists(Account::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'disbursement_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'disbursed_at' => 'tanggal cair',
            'disbursement_account_row_id' => 'sumber dana',
            'disbursement_notes' => 'catatan pencairan',
        ];
    }
}
