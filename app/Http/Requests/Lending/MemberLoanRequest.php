<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Membership\Models\Member;
use App\Enums\Frequency;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MemberLoanRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'loan_product_id' => [
                'required',
                'integer',
                Rule::exists(LoanProduct::class, 'row_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'member_id' => [
                'required',
                'integer',
                Rule::exists(Member::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'proposed_at' => ['required', 'date', 'before_or_equal:today'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'service_rate_total' => ['required', 'numeric', 'min:0', 'max:5000'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_method' => ['required', Rule::in(['flat', 'annuity', 'effective'])],
            'principal_frequency' => ['required', 'string', Rule::in(Frequency::values())],
            'interest_frequency' => ['required', 'string', Rule::in(Frequency::values())],
            'principal_grace_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'interest_grace_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'rounding_step' => ['nullable', 'integer', 'in:0,100,500,1000,5000,10000,50000'],
            'collateral' => ['nullable'],
            'collateral.type' => ['nullable', 'string', 'in:kendaraan,sertifikat_tanah,bpkb,lainnya'],
            'collateral.description' => ['nullable', 'string', 'max:500'],
            'collateral.value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'collateral.reference' => ['nullable', 'string', 'max:120'],
            'verification_remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'loan_product_id' => 'produk pinjaman',
            'member_id' => 'anggota peminjam',
            'proposed_at' => 'tanggal pengajuan',
            'principal_amount' => 'plafon pinjaman',
            'service_rate_total' => 'prosentase jasa',
            'term_months' => 'jangka waktu',
            'installment_method' => 'jenis jasa',
            'principal_frequency' => 'sistem angsuran pokok',
            'interest_frequency' => 'sistem angsuran jasa',
            'principal_grace_months' => 'grace period pokok',
            'interest_grace_months' => 'grace period jasa',
            'rounding_step' => 'pembulatan angsuran',
            'collateral.type' => 'jenis jaminan',
            'collateral.description' => 'deskripsi jaminan',
            'collateral.value' => 'nilai jaminan',
            'collateral.reference' => 'nomor dokumen jaminan',
            'verification_remarks' => 'catatan verifikasi',
        ];
    }

    /**
     * Siapkan payload yang sudah ternormalisasi untuk LoanService::createMemberProposal.
     *
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();
        $collateral = null;

        if ($this->has('collateral') && is_array($this->input('collateral'))) {
            $raw = $this->input('collateral');
            if (($raw['description'] ?? '') !== '' || (isset($raw['value']) && $raw['value'] !== '' && $raw['value'] !== null) || ($raw['reference'] ?? '') !== '') {
                $collateral = [
                    'type' => $raw['type'] ?? 'lainnya',
                    'description' => $raw['description'] ?? null,
                    'value' => isset($raw['value']) && $raw['value'] !== '' ? (float) $raw['value'] : null,
                    'reference' => $raw['reference'] ?? null,
                ];
            }
        }

        $data['collateral'] = $collateral;

        return $data;
    }
}
