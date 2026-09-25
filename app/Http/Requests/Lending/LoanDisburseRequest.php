<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Domain\Accounting\Models\Account;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanDisburseRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            // Mirror SIUPK original: tgl_cair TIDAK boleh sebelum tanggal
            // pakai aplikasi lembaga (operational_start_date).
            'disbursed_at' => [
                'required',
                'date',
                'before_or_equal:today',
                $this->operationalStartDateRule(),
            ],
            'disbursement_account_row_id' => ['required', 'integer', Rule::exists(Account::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'disbursement_notes' => ['nullable', 'string', 'max:5000'],
            'spk_no' => ['nullable', 'string', 'max:80'],
            'disbursement_slot' => ['nullable', 'string', 'max:120'],
            'verification_remarks' => ['nullable', 'string', 'max:2000'],
            'funding_source' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function attributes(): array
    {
        return [
            'disbursed_at' => 'tanggal cair',
            'disbursement_account_row_id' => 'sumber dana',
            'disbursement_notes' => 'catatan pencairan',
            'spk_no' => 'nomor SPK',
            'disbursement_slot' => 'waktu & tempat pencairan',
            'verification_remarks' => 'catatan verifikasi',
            'funding_source' => 'sumber dana (kode)',
        ];
    }

    /**
     * Closure rule: disbursed_at tidak boleh sebelum operational_start_date
     * lembaga. Mirror SIUPK original (TransaksiController & PelaporanController
     * yang bandingkan terhadap `kec->tgl_pakai`).
     */
    private function operationalStartDateRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            $profile = OrganizationProfile::query()->first();
            $start = $profile?->operational_start_date;
            if (! $start instanceof CarbonImmutable) {
                return;
            }
            try {
                $disbursed = CarbonImmutable::parse((string) $value);
            } catch (\Throwable) {
                return;
            }
            if ($disbursed->lessThan($start)) {
                $fail("Tanggal cair tidak boleh sebelum tanggal pakai aplikasi ({$start->toDateString()}).");
            }
        };
    }
}
