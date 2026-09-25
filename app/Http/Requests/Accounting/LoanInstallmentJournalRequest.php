<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Membership\Models\Member;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanInstallmentJournalRequest extends FormRequest
{
    use AuthorizesPermission;

    /**
     * Tentukan scope loan berdasarkan route name. Dipakai oleh rules() untuk
     * memvalidasi loan_id sesuai dengan route (kelompok vs individu).
     */
    private function expectedLoanScope(): string
    {
        $route = $this->route();
        $name = $route?->getName();
        if ($name === 'accounting.journal-entries.installment-individual.store') {
            return 'member_loan';
        }

        return 'group_loan';
    }

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

        // Validasi loan_id + cross-route guard sekaligus:
        // - Route /installment-individual hanya boleh loan dengan legacy_source='member_loan'.
        // - Route /installment hanya boleh loan dengan legacy_source != 'member_loan' atau NULL.
        $expectedScope = $this->expectedLoanScope();
        $loanExists = Rule::exists(Loan::class, 'row_id')
            ->where(function ($query) use ($expectedScope): void {
                $query->where('tenant_id', app(TenantContext::class)->id());
                if ($expectedScope === 'member_loan') {
                    $query->where('legacy_source', 'member_loan');
                } else {
                    $query->where(function ($q): void {
                        $q->whereNull('legacy_source')->orWhere('legacy_source', '!=', 'member_loan');
                    });
                }
            });

        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'loan_id' => ['required', 'integer', $loanExists],
            'installment_row_id' => ['nullable', 'integer'],
            'installment_number' => ['nullable', 'integer', 'min:1'],
            'principal_amount' => ['required', 'numeric', 'min:0'],
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
            $principalAmount = (float) $this->input('principal_amount', 0);
            $interestAmount = (float) $this->input('interest_amount', 0);
            $penaltyAmount = (float) ($this->input('penalty_amount') ?? 0);

            // Mirror SIUPK: total bayar (pokok + jasa + denda) tidak boleh 0.
            if (round($principalAmount + $interestAmount + $penaltyAmount, 2) <= 0) {
                $validator->errors()->add(
                    'principal_amount',
                    'Total bayar (pokok + jasa + denda) tidak boleh nol.'
                );
            }

            // Mirror SIUPK: jika installment_row_id ditentukan, validasi
            // bahwa nominal tidak melebihi sisa tagihan angsuran tsb.
            $installmentRowId = (int) $this->input('installment_row_id', 0);
            if ($installmentRowId > 0) {
                $tenantId = app(TenantContext::class)->id();
                $inst = LoanInstallment::query()
                    ->where('tenant_id', $tenantId)
                    ->where('row_id', $installmentRowId)
                    ->first(['row_id', 'principal_due', 'principal_paid', 'interest_due', 'interest_paid']);
                if ($inst) {
                    $remainingP = round((float) $inst->principal_due - (float) $inst->principal_paid, 2);
                    $remainingI = round((float) $inst->interest_due - (float) $inst->interest_paid, 2);
                    if ($principalAmount > $remainingP + 0.005) {
                        $validator->errors()->add(
                            'principal_amount',
                            'Nominal pokok ('.number_format($principalAmount, 0, ',', '.').') melebihi sisa tagihan angsuran ini ('.number_format($remainingP, 0, ',', '.').').'
                        );
                    }
                    if ($interestAmount > $remainingI + 0.005) {
                        $validator->errors()->add(
                            'interest_amount',
                            'Nominal jasa ('.number_format($interestAmount, 0, ',', '.').') melebihi sisa tagihan angsuran ini ('.number_format($remainingI, 0, ',', '.').').'
                        );
                    }
                }
            }

            // Validasi konsistensi member_allocations (sudah ada) + cek per-anggota negatif.
            $allocations = $this->input('member_allocations');
            if (! is_array($allocations) || $allocations === []) {
                return;
            }

            $principalTotal = 0.0;
            $interestTotal = 0.0;
            $penaltyTotal = 0.0;
            foreach ($allocations as $idx => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $p = (float) ($row['principal_paid'] ?? 0);
                $i = (float) ($row['interest_paid'] ?? 0);
                $d = (float) ($row['penalty_paid'] ?? 0);
                if ($p < 0 || $i < 0 || $d < 0) {
                    $validator->errors()->add(
                        'member_allocations.'.$idx,
                        'Nilai catatan per-anggota tidak boleh negatif.'
                    );
                }
                $principalTotal += $p;
                $interestTotal += $i;
                $penaltyTotal += $d;
            }

            $expectedPrincipal = round($principalAmount, 2);
            $expectedInterest = round($interestAmount, 2);
            $expectedPenalty = round($penaltyAmount, 2);

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
