<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\Member;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanInstallmentJournalRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $cashAccountExists = Rule::exists(Account::class, 'row_id')
            ->where(fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('is_postable', true)
                ->where('code', 'like', '1.1.01.%'));

        $memberExists = Rule::exists(Member::class, 'row_id')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'));

        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'loan_id' => ['required', 'integer', Rule::exists(Loan::class, 'row_id')],
            'installment_row_id' => ['nullable', 'integer'],
            'installment_number' => ['nullable', 'integer', 'min:1'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'interest_amount' => ['required', 'numeric', 'min:0'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'cash_account_row_id' => ['required', 'integer', $cashAccountExists],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['required', 'integer', $memberExists],
            'member_allocations' => ['nullable', 'array'],
            'member_allocations.*.member_row_id' => ['required_with:member_allocations', 'integer', $memberExists],
            'member_allocations.*.principal_paid' => ['required_with:member_allocations', 'numeric', 'min:0'],
            'member_allocations.*.interest_paid' => ['required_with:member_allocations', 'numeric', 'min:0'],
            'member_allocations.*.penalty_paid' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $allocations = $this->input('member_allocations');
            if (! is_array($allocations) || $allocations === []) {
                return;
            }

            $principalTotal = 0.0;
            $interestTotal = 0.0;
            $penaltyTotal = 0.0;
            foreach ($allocations as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $principalTotal += (float) ($row['principal_paid'] ?? 0);
                $interestTotal += (float) ($row['interest_paid'] ?? 0);
                $penaltyTotal += (float) ($row['penalty_paid'] ?? 0);
            }

            $expectedPrincipal = round((float) $this->input('principal_amount', 0), 2);
            $expectedInterest = round((float) $this->input('interest_amount', 0), 2);
            $expectedPenalty = round((float) ($this->input('penalty_amount') ?? 0), 2);

            if (round($principalTotal, 2) !== $expectedPrincipal) {
                $validator->errors()->add(
                    'member_allocations',
                    'Total catatan pokok per-anggota ('.number_format($principalTotal, 0, ',', '.').') harus sama dengan nominal pokok jurnal ('.number_format($expectedPrincipal, 0, ',', '.').').'
                );
            }
            if (round($interestTotal, 2) !== $expectedInterest) {
                $validator->errors()->add(
                    'member_allocations',
                    'Total catatan jasa per-anggota ('.number_format($interestTotal, 0, ',', '.').') harus sama dengan nominal jasa jurnal ('.number_format($expectedInterest, 0, ',', '.').').'
                );
            }
            if ($expectedPenalty > 0 && round($penaltyTotal, 2) !== $expectedPenalty) {
                $validator->errors()->add(
                    'member_allocations',
                    'Total catatan denda per-anggota ('.number_format($penaltyTotal, 0, ',', '.').') harus sama dengan nominal denda jurnal ('.number_format($expectedPenalty, 0, ',', '.').').'
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'transaction_date' => 'tanggal angsuran',
            'loan_id' => 'pinjaman',
            'installment_row_id' => 'angsuran',
            'installment_number' => 'angsuran',
            'principal_amount' => 'nominal pokok',
            'interest_amount' => 'nominal jasa',
            'penalty_amount' => 'nominal denda',
            'cash_account_row_id' => 'tujuan',
            'description' => 'keterangan',
            'reference' => 'penyetor',
            'member_allocations' => 'catatan per-anggota',
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_date.before_or_equal' => 'tanggal angsuran harus berupa tanggal sebelum atau sama dengan hari ini.',
        ];
    }
}
