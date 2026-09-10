<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Domain\Membership\Models\Member;
use App\Enums\Frequency;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Rules\ValidLoanSchedule;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanApproveRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'approved_at' => ['required', 'date', 'before_or_equal:today'],
            'planned_disbursed_at' => ['required', 'date', 'after_or_equal:approved_at'],
            'allocated_principal' => ['required', 'numeric', 'min:0'],
            'allocation_notes' => ['nullable', 'string', 'max:500'],
            'beneficiaries' => ['required', 'array', 'min:1'],
            'beneficiaries.*.member_row_id' => ['required', 'integer', Rule::exists(Member::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'beneficiaries.*.allocated_amount' => ['required', 'numeric', 'min:0'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'service_rate_total' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'principal_frequency' => ['nullable', 'string', Rule::enum(Frequency::class)],
            'interest_frequency' => ['nullable', 'string', Rule::enum(Frequency::class)],
            'principal_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],
            'interest_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],
        ];
    }

    public function attributes(): array
    {
        return [
            'approved_at' => 'tanggal penetapan',
            'planned_disbursed_at' => 'rencana tanggal pencairan',
            'allocated_principal' => 'plafon alokasi kelompok',
            'beneficiaries' => 'alokasi per anggota',
            'beneficiaries.*.member_row_id' => 'anggota',
            'beneficiaries.*.allocated_amount' => 'nominal alokasi',
            'term_months' => 'jangka waktu',
            'service_rate_total' => 'prosentase jasa',
            'principal_frequency' => 'sistem angsuran pokok',
            'interest_frequency' => 'sistem angsuran jasa',
            'principal_grace_months' => 'grace period pokok',
            'interest_grace_months' => 'grace period jasa',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->route('loan')?->loadMissing(['statusHistories' => fn ($query) => $query->latest('changed_at')]);
        $verification = $this->route('loan')?->statusHistories->firstWhere('to_status', 'verified');

        $this->merge([
            'term_months' => $this->input('term_months') ?? $verification?->term_months ?? $this->route('loan')?->term_months,
            'service_rate_total' => $this->input('service_rate_total') ?? $verification?->service_rate_total ?? $this->route('loan')?->service_rate_total,
            'principal_frequency' => $this->input('principal_frequency') ?? $verification?->principal_frequency ?? $this->route('loan')?->principal_frequency,
            'interest_frequency' => $this->input('interest_frequency') ?? $verification?->interest_frequency ?? $this->route('loan')?->interest_frequency,
            'principal_grace_months' => $this->input('principal_grace_months') ?? $verification?->principal_grace_months ?? $this->route('loan')?->principal_grace_months,
            'interest_grace_months' => $this->input('interest_grace_months') ?? $verification?->interest_grace_months ?? $this->route('loan')?->interest_grace_months,
        ]);
    }
}
