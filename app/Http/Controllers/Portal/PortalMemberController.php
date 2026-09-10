<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberUserLink;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class PortalMemberController
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = $this->context->id();
        $member = Member::query()
            ->whereKey(MemberUserLink::query()
                ->where('user_row_id', (int) auth()->user()?->row_id)
                ->value('member_row_id'))
            ->with(['person:row_id,full_name', 'village:row_id,name'])
            ->first();
        abort_unless($member !== null, 404, 'Akun belum terhubung ke data anggota.');

        $loans = $this->loanRows($tenantId, (int) $member->row_id);
        $officers = $this->officerRows($tenantId, (int) $member->row_id);
        $activeGroupIds = collect($officers)->where('ended_at', null)->pluck('group_row_id')->unique()->values();
        $fellowMembers = $this->fellowMembers($tenantId, $activeGroupIds, (int) $member->row_id);

        return Inertia::render('Portal/Index', [
            'profile' => [
                'name' => $member->person?->full_name,
                'member_number' => $member->member_number,
                'status' => $member->status,
                'registered_at' => $member->registered_at?->toDateString(),
                'organization_unit' => $member->village?->name,
            ],
            'loan_summary' => [
                'total' => $loans->count(),
                'total_disbursed' => (float) $loans->sum('principal_amount'),
                'active_count' => $loans->whereNotIn('status', ['completed', 'cancelled'])->count(),
            ],
            'loans' => $this->paginateLoans($request, $loans),
            'officers' => $officers,
            'active_groups' => $activeGroupIds->map(fn ($groupId): array => [
                'group_name' => $officers->firstWhere('group_row_id', (int) $groupId)['group_name'] ?? '',
                'members' => $fellowMembers[$groupId] ?? [],
            ])->values()->all(),
        ]);
    }

    private function paginateLoans(Request $request, Collection $loans): LengthAwarePaginator
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;
        $sort = in_array((string) $request->query('sort'), ['disbursed_at', 'principal_amount', 'status'], true)
            ? (string) $request->query('sort')
            : 'disbursed_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $filtered = $loans
            ->when($search !== '', fn ($rows) => $rows->filter(fn (array $loan): bool => str_contains(
                strtolower($loan['loan_number'] ?? (string) $loan['id']),
                strtolower($search),
            )))
            ->sortBy($sort, SORT_REGULAR, $direction === 'desc')
            ->values();

        return (new LengthAwarePaginator(
            $filtered->forPage(LengthAwarePaginator::resolveCurrentPage(), $perPage)->values(),
            $filtered->count(),
            $perPage,
            LengthAwarePaginator::resolveCurrentPage(),
            ['path' => '/portal'],
        ))
            ->appends($request->query())
            ->withQueryString();
    }

    private function loanRows(int $tenantId, int $memberRowId): Collection
    {
        return Loan::query()
            ->select('loans.*')
            ->join('loan_borrowers', function ($join): void {
                $join->on('loan_borrowers.tenant_id', '=', 'loans.tenant_id')
                    ->on('loan_borrowers.loan_row_id', '=', 'loans.row_id');
            })
            ->where('loan_borrowers.member_row_id', $memberRowId)
            ->with(['installments', 'beneficiaries.member.person:row_id,full_name'])
            ->orderByDesc('loans.disbursed_at')
            ->orderByDesc('loans.row_id')
            ->get()
            ->map(function (Loan $loan) use ($memberRowId): array {
                $installments = $loan->installments->map(fn ($installment): array => [
                    'installment_number' => (int) $installment->installment_number,
                    'due_date' => $installment->due_date?->toDateString(),
                    'status' => $installment->status,
                    'paid_at' => $installment->paid_at?->toDateTimeString(),
                    'principal_due' => (float) $installment->principal_due,
                    'interest_due' => (float) $installment->interest_due,
                    'penalty_due' => (float) $installment->penalty_due,
                    'principal_paid' => (float) $installment->principal_paid,
                    'interest_paid' => (float) $installment->interest_paid,
                    'penalty_paid' => (float) $installment->penalty_paid,
                    'due' => (float) ($installment->principal_due + $installment->interest_due + $installment->penalty_due),
                    'paid' => (float) ($installment->principal_paid + $installment->interest_paid + $installment->penalty_paid),
                ]);
                $due = (float) $installments->sum('due');
                $paid = (float) $installments->sum('paid');
                $today = Carbon::today()->toDateString();
                $hasArrears = $installments->contains(fn (array $installment): bool => $installment['due_date'] < $today && $installment['paid'] < $installment['due']);

                return [
                    'row_id' => (int) $loan->row_id,
                    'id' => (int) $loan->id,
                    'loan_number' => $loan->loan_number,
                    'disbursed_at' => $loan->disbursed_at?->toDateString(),
                    'status' => $loan->status,
                    'principal_amount' => (float) $loan->principal_amount,
                    'term_months' => (int) $loan->term_months,
                    'due' => $due,
                    'principal_due' => (float) $installments->sum('principal_due'),
                    'interest_due' => (float) $installments->sum('interest_due'),
                    'penalty_due' => (float) $installments->sum('penalty_due'),
                    'paid' => $paid,
                    'principal_paid' => (float) $installments->sum('principal_paid'),
                    'interest_paid' => (float) $installments->sum('interest_paid'),
                    'penalty_paid' => (float) $installments->sum('penalty_paid'),
                    'has_arrears' => $hasArrears,
                    'installments' => $installments->values()->all(),
                    'beneficiaries' => $this->beneficiaryRows($loan, $memberRowId),
                ];
            });
    }

    private function beneficiaryRows(Loan $loan, int $memberRowId): array
    {
        $beneficiaries = $loan->beneficiaries
            ->map(fn (LoanBeneficiary $beneficiary): array => [
                'member_row_id' => (int) $beneficiary->member_row_id,
                'name' => $beneficiary->member?->person?->full_name ?? '-',
                'is_self' => (int) $beneficiary->member_row_id === $memberRowId,
                'allocated_amount' => (int) $beneficiary->member_row_id === $memberRowId
                    ? (float) $beneficiary->allocated_amount
                    : null,
            ])
            ->values();

        [$selfRows, $otherRows] = $beneficiaries->partition(
            fn (array $beneficiary): bool => $beneficiary['is_self'],
        );

        return $selfRows
            ->merge($otherRows->sortBy(
                fn (array $beneficiary): string => $beneficiary['name'] ?? '-',
            ))
            ->values()
            ->all();
    }

    private function officerRows(int $tenantId, int $memberRowId): Collection
    {
        return GroupOfficer::query()
            ->where('member_row_id', $memberRowId)
            ->with('group:row_id,name')
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($officer): array => [
                'group_row_id' => (int) $officer->group_row_id,
                'group_name' => $officer->group?->name,
                'position' => $officer->position,
                'started_at' => $officer->started_at?->toDateString(),
                'ended_at' => $officer->ended_at?->toDateString(),
            ]);
    }

    private function fellowMembers(int $tenantId, Collection $groupIds, int $currentMemberId): array
    {
        if ($groupIds->isEmpty()) {
            return [];
        }

        $rows = GroupMember::query()
            ->whereIn('group_row_id', $groupIds)
            ->whereNull('left_at')
            ->with(['member.person:row_id,full_name'])
            ->get()
            ->groupBy('group_row_id');

        return $rows->map(fn ($members, $groupId) => $members
            ->filter(fn ($row): bool => (int) $row->member_row_id !== $currentMemberId)
            ->values()
            ->map(fn ($row): array => [
                'name' => $row->member?->person?->full_name,
                'member_number' => $row->member?->member_number,
                'status' => $row->member?->status,
            ])->all())->all();
    }
}
