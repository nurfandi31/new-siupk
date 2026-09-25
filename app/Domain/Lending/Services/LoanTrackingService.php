<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallmentTracking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class LoanTrackingService
{
    /**
     * Insert per-member allocation rows for one installment payment.
     *
     * @param  array<int, array{member_row_id:int, principal_paid:float, interest_paid:float, penalty_paid?:float}>  $rows
     */
    public function recordMemberAllocations(
        int $loanId,
        int $installmentNumber,
        ?int $journalEntryRowId,
        array $rows,
        CarbonImmutable $recordedAt,
    ): void {
        if ($rows === []) {
            return;
        }

        // Load loan beneficiaries untuk validasi overpay (mirror siupk: max
        // total bayar per anggota == alokasi mereka).
        $allocated = DB::connection('tenant')
            ->table('loan_beneficiaries')
            ->where('loan_row_id', $loanId)
            ->get(['member_row_id', 'allocated_amount'])
            ->keyBy('member_row_id');

        // Accumulated paid per member dari tracking s.d. angsuran SEBELUM ini
        // (exclude installmentNumber ini karena belum dicatat).
        $paidSoFar = DB::connection('tenant')
            ->table('loan_installment_tracking')
            ->where('loan_row_id', $loanId)
            ->where('installment_number', '<', $installmentNumber)
            ->groupBy('member_row_id')
            ->selectRaw('member_row_id, SUM(principal_paid) as p, SUM(interest_paid) as i')
            ->get()
            ->keyBy('member_row_id');

        foreach ($rows as $row) {
            $memberRowId = (int) $row['member_row_id'];
            $newPrincipal = round((float) $row['principal_paid'], 2);
            $newInterest = round((float) $row['interest_paid'], 2);
            $newPenalty = round((float) ($row['penalty_paid'] ?? 0), 2);

            // Sanitasi: tolak negatif atau nol.
            if ($newPrincipal < 0 || $newInterest < 0 || $newPenalty < 0) {
                throw new \DomainException(
                    'Catatan per-anggota tidak boleh bernilai negatif (anggota row_id='.$memberRowId.').'
                );
            }

            // Validasi: total pembayaran per anggota untuk pokok tidak boleh
            // melebihi alokasi beneficiaries.
            $beneficiary = $allocated->get($memberRowId);
            if ($beneficiary) {
                $alreadyPrincipal = (float) ($paidSoFar->get($memberRowId)->p ?? 0);
                $cap = (float) $beneficiary->allocated_amount;
                if ($alreadyPrincipal + $newPrincipal > $cap + 0.005) {
                    throw new \DomainException(sprintf(
                        'Pembayaran pokok anggota #%s melebihi alokasi (sudah dibayar %s, akan ditambah %s, alokasi %s).',
                        $memberRowId,
                        number_format($alreadyPrincipal, 0, ',', '.'),
                        number_format($newPrincipal, 0, ',', '.'),
                        number_format($cap, 0, ',', '.'),
                    ));
                }
            }

            // Eloquent path: BelongsToTenant + HasTenantLocalId set tenant_id / id.
            LoanInstallmentTracking::query()->create([
                'loan_row_id' => $loanId,
                'installment_number' => $installmentNumber,
                'member_row_id' => $memberRowId,
                'principal_paid' => $newPrincipal,
                'interest_paid' => $newInterest,
                'penalty_paid' => $newPenalty,
                'journal_entry_row_id' => $journalEntryRowId,
                'recorded_at' => $recordedAt->toDateTimeString(),
            ]);
        }
    }

    /**
     * Load all tracking rows for a loan, grouped by installment_number.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getTrackingForLoan(int $loanId): array
    {
        $rows = LoanInstallmentTracking::query()
            ->where('loan_row_id', $loanId)
            ->orderBy('installment_number')
            ->orderBy('member_row_id')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $inst = (int) $row->installment_number;
            $grouped[$inst][] = [
                'member_row_id' => (int) $row->member_row_id,
                'principal_paid' => (float) $row->principal_paid,
                'interest_paid' => (float) $row->interest_paid,
                'penalty_paid' => (float) $row->penalty_paid,
                'recorded_at' => $row->recorded_at?->toDateTimeString(),
            ];
        }

        return $grouped;
    }

    /**
     * @return array<int, array{row_id:int, full_name:string, status:string}>
     */
    public function getGroupMembers(int $loanId): array
    {
        $loan = Loan::query()->with(['borrower', 'beneficiaries'])->where('row_id', $loanId)->firstOrFail();
        $groupRowId = (int) ($loan->borrower?->group_row_id ?? 0);

        if ($groupRowId === 0) {
            return [];
        }

        $beneficiaryMap = $loan->beneficiaries
            ->keyBy('member_row_id')
            ->map(fn ($b) => (float) $b->allocated_amount);

        return DB::connection('tenant')
            ->table('group_members as gm')
            ->join('members as m', function ($join): void {
                $join->on('m.tenant_id', '=', 'gm.tenant_id')
                    ->on('m.row_id', '=', 'gm.member_row_id');
            })
            ->join('people as p', function ($join): void {
                $join->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->where('gm.tenant_id', $loan->tenant_id)
            ->where('gm.group_row_id', $groupRowId)
            ->whereNull('gm.left_at')
            ->where('m.status', 'active')
            ->whereNull('m.deleted_at')
            ->orderBy('p.full_name')
            ->get(['m.row_id', 'p.full_name', 'm.status'])
            ->map(fn ($r) => [
                'row_id' => (int) $r->row_id,
                'full_name' => (string) $r->full_name,
                'status' => (string) $r->status,
                'allocated_amount' => $beneficiaryMap->get((int) $r->row_id, 0.0),
            ])
            ->all();
    }
}
