<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Enums\Frequency;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Rules\ValidLoanSchedule;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest untuk penetapan alokasi pinjaman individu (P/V -> W).
 *
 * Mirror SIUPK original: `approved_at`, `planned_disbursed_at`, `spk_no`,
 * snapshot parameter verifikasi (term_months, service_rate_total,
 * principal_frequency, interest_frequency, grace periods).
 *
 * Tidak ada array beneficiaries (individu hanya 1 pemanfaat, namely
 * loan.borrower), sehingga tidak perlu Rule::exists untuk member.
 */
final class MemberLoanApproveRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'approved_at' => ['required', 'date', 'before_or_equal:today'],
            'planned_disbursed_at' => ['required', 'date', 'after_or_equal:approved_at'],
            'allocation_notes' => ['nullable', 'string', 'max:500'],

            // Snapshot parameter verifikasi -> wajib ada (jika tidak dikirim
            // oleh form, akan di-default dari verifikasi di prepareForValidation,
            // tapi rule tetap required supaya eksplisit di payload).
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'service_rate_total' => ['required', 'numeric', 'min:0', 'max:5000'],
            'principal_frequency' => ['required', 'string', Rule::enum(Frequency::class)],
            'interest_frequency' => ['required', 'string', Rule::enum(Frequency::class)],
            'principal_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],
            'interest_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],

            // SPK number — REQUIRED + UNIQUE di tenant (mirror siupk yang
            // meng-UNIQUE-kan spk_no per kecamatan). Snapshot dari verifikasi
            // jika tidak dikirim form.
            'spk_no' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('loans', 'spk_no')
                    ->ignore($this->route('loan')?->row_id, 'row_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'approved_at' => 'tanggal penetapan',
            'planned_disbursed_at' => 'rencana tanggal pencairan',
            'allocation_notes' => 'catatan alokasi',
            'term_months' => 'jangka waktu',
            'service_rate_total' => 'prosentase jasa',
            'principal_frequency' => 'sistem angsuran pokok',
            'interest_frequency' => 'sistem angsuran jasa',
            'principal_grace_months' => 'grace period pokok',
            'interest_grace_months' => 'grace period jasa',
            'spk_no' => 'nomor SPK',
        ];
    }

    public function messages(): array
    {
        return [
            'spk_no.unique' => 'Nomor SPK sudah dipakai oleh pinjaman lain di tenant ini.',
        ];
    }

    /**
     * Snapshot parameter dari verifikasi terakhir (jika tidak dikirim form).
     * Mirror LoanApproveRequest::prepareForValidation supaya logika approval
     * kelompok & individu konsisten.
     */
    protected function prepareForValidation(): void
    {
        $loan = $this->route('loan');
        $loan?->loadMissing(['statusHistories' => fn ($query) => $query->latest('changed_at')]);
        $verification = $loan?->statusHistories->firstWhere('to_status', 'verified');

        $this->merge([
            'term_months' => $this->input('term_months') ?? $verification?->term_months ?? $loan?->term_months,
            'service_rate_total' => $this->input('service_rate_total') ?? $verification?->service_rate_total ?? $loan?->service_rate_total,
            'principal_frequency' => $this->input('principal_frequency') ?? $verification?->principal_frequency ?? $loan?->principal_frequency,
            'interest_frequency' => $this->input('interest_frequency') ?? $verification?->interest_frequency ?? $loan?->interest_frequency,
            'principal_grace_months' => $this->input('principal_grace_months') ?? $verification?->principal_grace_months ?? $loan?->principal_grace_months,
            'interest_grace_months' => $this->input('interest_grace_months') ?? $verification?->interest_grace_months ?? $loan?->interest_grace_months,
            'spk_no' => $this->input('spk_no') ?? $loan?->spk_no,
        ]);
    }
}
