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

        // Eloquent path: BelongsToTenant + HasTenantLocalId set tenant_id / id.
        foreach ($rows as $row) {
            LoanInstallmentTracking::query()->create([
                'loan_row_id' => $loanId,
                'installment_number' => $installmentNumber,
                'member_row_id' => (int) $row['member_row_id'],
                'principal_paid' => round((float) $row['principal_paid'], 2),
                'interest_paid' => round((float) $row['interest_paid'], 2),
                'penalty_paid' => round((float) ($row['penalty_paid'] ?? 0), 2),
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
