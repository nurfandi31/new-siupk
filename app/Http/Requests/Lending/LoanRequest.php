<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Enums\Frequency;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Rules\ValidLoanSchedule;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanRequest extends FormRequest
{
    use AuthorizesPermission;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $amounts = (array) $this->input('beneficiary_amounts', []);
            if ($amounts === []) {
                return;
            }

            $total = array_sum(array_map(fn ($v) => (float) $v, $amounts));
            $principal = (float) $this->input('principal_amount', 0);
            if ($principal > 0 && $total > $principal) {
                $validator->errors()->add(
                    'beneficiary_amounts',
                    'Total pengajuan pemanfaat ('.number_format($total, 0, ',', '.').') melebihi plafon pinjaman.',
                );
            }
        });
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'loan_product_id' => ['required', 'integer', Rule::exists(LoanProduct::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'group_id' => ['required', 'integer', Rule::exists(Group::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'proposed_at' => ['required', 'date', 'before_or_equal:today'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'service_rate_total' => ['required', 'numeric', 'min:0', 'max:5000'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_method' => ['required', Rule::in(['flat', 'annuity', 'effective'])],
            'principal_frequency' => ['required', 'string', Rule::in(Frequency::values())],
            'interest_frequency' => ['required', 'string', Rule::in(Frequency::values())],
            'principal_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],
            'interest_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],
            'rounding_step' => ['nullable', 'integer', 'in:0,100,500,1000,5000,10000,50000'],
            'chair_id' => ['required', 'integer', Rule::exists(Member::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'secretary_id' => ['required', 'integer', 'different:chair_id', Rule::exists(Member::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'treasurer_id' => ['required', 'integer', 'different:chair_id', 'different:secretary_id', Rule::exists(Member::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'beneficiary_ids' => ['required', 'array', 'min:1'],
            'beneficiary_ids.*' => ['integer', Rule::exists(Member::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'beneficiary_amounts' => ['nullable', 'array'],
            'beneficiary_amounts.*' => ['numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'loan_product_id' => 'produk pinjaman',
            'group_id' => 'kelompok',
            'proposed_at' => 'tanggal pengajuan',
            'principal_amount' => 'plafon pinjaman',
            'service_rate_total' => 'prosentase jasa',
            'term_months' => 'jangka waktu',
            'installment_method' => 'jenis jasa',
            'principal_frequency' => 'sistem angsuran pokok',
            'interest_frequency' => 'sistem angsuran jasa',
            'rounding_step' => 'pembulatan angsuran',
            'chair_id' => 'ketua',
            'secretary_id' => 'sekretaris',
            'treasurer_id' => 'bendahara',
            'beneficiary_ids' => 'pemanfaat',
            'beneficiary_amounts' => 'pengajuan pemanfaat',
            'beneficiary_amounts.*' => 'pengajuan pemanfaat',
        ];
    }
}
