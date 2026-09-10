<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Services\LoanService;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class MobileExecutiveController
{
    public function summary(Request $request): JsonResponse
    {
        $today = CarbonImmutable::now()->toDateString();
        $startOfMonth = CarbonImmutable::now()->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::now()->endOfMonth()->toDateString();

        // 1. Kas & Bank balances from journal lines
        $cashBalance = (float) DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
            ->join('accounts as a', 'a.row_id', '=', 'jl.account_row_id')
            ->where('je.status', 'posted')
            ->where('a.code', 'like', '1.1.01.%')
            ->selectRaw('COALESCE(SUM(jl.debit - jl.credit), 0) as balance')
            ->value('balance');

        $bankBalance = (float) DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
            ->join('accounts as a', 'a.row_id', '=', 'jl.account_row_id')
            ->where('je.status', 'posted')
            ->where('a.code', 'like', '1.1.02.%')
            ->selectRaw('COALESCE(SUM(jl.debit - jl.credit), 0) as balance')
            ->value('balance');

        // 2. Active loan statistics
        $activeLoans = Loan::query()->whereIn('status', ['active', 'disbursed'])->get();
        $activeLoansCount = $activeLoans->count();
        $activeLoansPrincipal = (float) $activeLoans->sum('principal_amount');

        // Total repaid principal
        $totalRepaidPrincipal = (float) DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
            ->where('je.source_type', 'loan_installment')
            ->where('je.status', 'posted')
            ->where('jl.credit', '>', 0)
            ->where('jl.line_number', 2)
            ->sum('jl.credit');

        $outstandingPrincipal = max(0.0, $activeLoansPrincipal - $totalRepaidPrincipal);

        // 3. Pending pipelines
        $pendingVerificationCount = Loan::query()->whereIn('status', ['draft', 'proposed'])->count();
        $pendingApprovalCount = Loan::query()->where('status', 'verified')->count();

        // 4. Today collections
        $todayCollectionsAmount = (float) DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
            ->where('je.source_type', 'loan_installment')
            ->where('je.status', 'posted')
            ->whereDate('je.transaction_date', $today)
            ->where('jl.debit', '>', 0)
            ->sum('jl.debit');

        $todayCollectionsCount = (int) DB::connection('tenant')
            ->table('journal_entries')
            ->where('source_type', 'loan_installment')
            ->where('status', 'posted')
            ->whereDate('transaction_date', $today)
            ->count();

        // 5. This month disbursement
        $thisMonthDisbursed = (float) Loan::query()
            ->whereIn('status', ['active', 'disbursed', 'completed'])
            ->whereBetween('disbursed_at', [$startOfMonth, $endOfMonth])
            ->sum('principal_amount');

        return ApiResponse::success([
            'as_of_date' => $today,
            'cash_balance' => max(0.0, $cashBalance),
            'bank_balance' => max(0.0, $bankBalance),
            'total_liquidity' => max(0.0, $cashBalance + $bankBalance),
            'active_loans_count' => $activeLoansCount,
            'outstanding_principal' => $outstandingPrincipal,
            'pending_verification_count' => $pendingVerificationCount,
            'pending_approval_count' => $pendingApprovalCount,
            'today_collections_amount' => $todayCollectionsAmount,
            'today_collections_count' => $todayCollectionsCount,
            'this_month_disbursed_amount' => $thisMonthDisbursed,
        ]);
    }

    public function approvals(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $villageId = $request->query('village_id');
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);

        /** @var User|null $user */
        $user = $request->user();

        $query = Loan::query()
            ->with([
                'product:row_id,code,name',
                'borrower.group:row_id,name,address,organization_unit_row_id',
                'borrower.group.village:row_id,name',
                'borrower.member.person:row_id,full_name,phone',
                'borrower.member.village:row_id,name',
                'beneficiaries.member.person:row_id,full_name',
            ])
            ->whereIn('status', ['verified', 'waiting']);

        if ($user !== null && $user->isVillageUser() && $user->village_row_id !== null) {
            $query->whereHas('borrower.group', fn ($q) => $q->where('organization_unit_row_id', $user->village_row_id))
                ->orWhereHas('borrower.member', fn ($q) => $q->where('organization_unit_row_id', $user->village_row_id));
        }

        if ($villageId !== null && is_numeric($villageId)) {
            $query->where(function ($q) use ($villageId): void {
                $q->whereHas('borrower.group', fn ($g) => $g->where('organization_unit_row_id', (int) $villageId))
                    ->orWhereHas('borrower.member', fn ($m) => $m->where('organization_unit_row_id', (int) $villageId));
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('borrower.group', fn ($g) => $g->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('borrower.member.person', fn ($p) => $p->where('full_name', 'like', "%{$search}%"));
            });
        }

        $proposals = $query->orderBy('row_id', 'desc')->paginate($perPage);

        $items = collect($proposals->items())->map(function (Loan $loan): array {
            $borrower = $loan->borrower;
            $group = $borrower?->group;
            $member = $borrower?->member;

            $borrowerName = $group !== null ? $group->name : ($member?->person?->full_name ?? 'Nasabah');
            $borrowerType = $group !== null ? 'Kelompok' : 'Individu';
            $villageName = $group?->village?->name ?? $member?->village?->name ?? '-';

            $totalProposed = (float) $loan->principal_amount;
            $verifiedSum = (float) $loan->beneficiaries->sum('verified_amount');

            return [
                'id' => (int) $loan->id,
                'row_id' => (int) $loan->row_id,
                'public_id' => $loan->public_id,
                'loan_number' => $loan->loan_number ?? "PROP-{$loan->id}",
                'status' => $loan->status,
                'product_name' => $loan->product?->name ?? 'Pinjaman Modal Usaha',
                'product_code' => $loan->product?->code ?? 'SPP',
                'borrower_type' => $borrowerType,
                'borrower_name' => $borrowerName,
                'village_name' => $villageName,
                'proposed_at' => $loan->proposed_at?->format('Y-m-d'),
                'verified_at' => $loan->verified_at?->format('Y-m-d'),
                'proposed_amount' => $totalProposed,
                'verified_amount' => $verifiedSum > 0 ? $verifiedSum : $totalProposed,
                'term_months' => (int) $loan->term_months,
                'beneficiary_count' => $loan->beneficiaries->count(),
                'verification_notes' => $loan->verification_notes,
            ];
        });

        return ApiResponse::success($items->all(), 'Berhasil', 200, [
            'current_page' => $proposals->currentPage(),
            'last_page' => $proposals->lastPage(),
            'per_page' => $proposals->perPage(),
            'total' => $proposals->total(),
            'has_more' => $proposals->hasMorePages(),
        ]);
    }

    public function showApproval(Request $request, string|int $loanId): JsonResponse
    {
        $loan = Loan::query()
            ->with([
                'product',
                'borrower.group.village',
                'borrower.member.person',
                'borrower.member.village',
                'committee.member.person',
                'beneficiaries.member.person',
                'statusHistories',
            ])
            ->where('row_id', $loanId)
            ->orWhere('id', $loanId)
            ->first();

        if ($loan === null) {
            return ApiResponse::error('Data usulan pinjaman tidak ditemukan.', 404);
        }

        $borrower = $loan->borrower;
        $group = $borrower?->group;
        $member = $borrower?->member;

        $borrowerName = $group !== null ? $group->name : ($member?->person?->full_name ?? 'Nasabah');
        $borrowerType = $group !== null ? 'Kelompok' : 'Individu';
        $villageName = $group?->village?->name ?? $member?->village?->name ?? '-';

        $beneficiaries = $loan->beneficiaries->map(fn ($b) => [
            'member_row_id' => (int) $b->member_row_id,
            'member_id' => (int) ($b->member?->id ?? $b->member_row_id),
            'full_name' => $b->member?->person?->full_name ?? "Anggota #{$b->member_row_id}",
            'nik' => $b->member?->person?->national_identity_number ?? '-',
            'proposed_amount' => (float) ($b->proposed_amount ?? $b->allocated_amount),
            'verified_amount' => (float) ($b->verified_amount ?? $b->proposed_amount ?? $b->allocated_amount),
            'allocated_amount' => (float) ($b->allocated_amount ?? $b->verified_amount ?? $b->proposed_amount),
        ]);

        $verifiedSum = (float) $loan->beneficiaries->sum('verified_amount');

        return ApiResponse::success([
            'id' => (int) $loan->id,
            'row_id' => (int) $loan->row_id,
            'public_id' => $loan->public_id,
            'loan_number' => $loan->loan_number ?? "PROP-{$loan->id}",
            'status' => $loan->status,
            'product_name' => $loan->product?->name ?? 'Pinjaman SPP/UEP',
            'product_code' => $loan->product?->code ?? 'SPP',
            'borrower_type' => $borrowerType,
            'borrower_name' => $borrowerName,
            'village_name' => $villageName,
            'proposed_at' => $loan->proposed_at?->format('Y-m-d'),
            'verified_at' => $loan->verified_at?->format('Y-m-d'),
            'suggested_disbursement_date' => CarbonImmutable::now()->addDays(7)->toDateString(),
            'proposed_amount' => (float) $loan->principal_amount,
            'verified_amount' => $verifiedSum > 0 ? $verifiedSum : (float) $loan->principal_amount,
            'term_months' => (int) $loan->term_months,
            'verification_notes' => $loan->verification_notes,
            'beneficiaries' => $beneficiaries->all(),
        ]);
    }

    public function approve(Request $request, string|int $loanId, LoanService $loanService): JsonResponse
    {
        $loan = Loan::query()
            ->with(['beneficiaries'])
            ->where('row_id', $loanId)
            ->orWhere('id', $loanId)
            ->first();

        if ($loan === null) {
            return ApiResponse::error('Data usulan pinjaman tidak ditemukan.', 404);
        }

        if (! in_array($loan->status, ['verified', 'waiting'], true)) {
            return ApiResponse::error("Pinjaman dengan status '{$loan->status}' tidak dapat diapprove.", 422);
        }

        $validator = Validator::make($request->all(), [
            'approved_at' => ['required', 'date', 'before_or_equal:today'],
            'planned_disbursed_at' => ['required', 'date', 'after_or_equal:approved_at'],
            'allocated_principal' => ['required', 'numeric', 'min:0'],
            'allocation_notes' => ['nullable', 'string', 'max:500'],
            'beneficiaries' => ['required', 'array', 'min:1'],
            'beneficiaries.*.member_row_id' => ['required', 'integer'],
            'beneficiaries.*.allocated_amount' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi data persetujuan gagal.', 422, $validator->errors()->toArray());
        }

        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->row_id;

        $data = [
            'approved_at' => $request->input('approved_at'),
            'planned_disbursed_at' => $request->input('planned_disbursed_at'),
            'allocated_principal' => (float) $request->input('allocated_principal'),
            'allocation_notes' => $request->input('allocation_notes') ?? "Disetujui oleh {$user->name} via Mobile App",
            'beneficiaries' => $request->input('beneficiaries'),
        ];

        try {
            $updatedLoan = $loanService->approve($loan, $data, $userId);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        return ApiResponse::success([
            'id' => (int) $updatedLoan->id,
            'row_id' => (int) $updatedLoan->row_id,
            'loan_number' => $updatedLoan->loan_number,
            'status' => $updatedLoan->status,
            'approved_at' => $updatedLoan->approved_at?->format('Y-m-d'),
            'funded_at' => $updatedLoan->funded_at?->format('Y-m-d'),
            'allocated_principal' => (float) $updatedLoan->principal_amount,
        ], 'Persetujuan alokasi pinjaman berhasil ditetapkan.');
    }

    public function reject(Request $request, string|int $loanId, LoanService $loanService): JsonResponse
    {
        $loan = Loan::query()
            ->where('row_id', $loanId)
            ->orWhere('id', $loanId)
            ->first();

        if ($loan === null) {
            return ApiResponse::error('Data usulan pinjaman tidak ditemukan.', 404);
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Alasan penolakan / revisi wajib diisi.', 422, $validator->errors()->toArray());
        }

        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->row_id;

        try {
            $updatedLoan = $loanService->revertToDraft($loan, $userId);

            // Record rejection reason in status history
            $updatedLoan->statusHistories()->create([
                'from_status' => 'verified',
                'to_status' => 'draft',
                'principal_amount' => (float) $updatedLoan->principal_amount,
                'product_row_id' => $updatedLoan->loan_product_row_id,
                'term_months' => (int) $updatedLoan->term_months,
                'notes' => 'Ditolak/Dikembalikan: '.$request->input('reason'),
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        return ApiResponse::success([
            'id' => (int) $updatedLoan->id,
            'row_id' => (int) $updatedLoan->row_id,
            'status' => $updatedLoan->status,
        ], 'Proposal pinjaman telah dikembalikan ke draft / perbaikan.');
    }
}
