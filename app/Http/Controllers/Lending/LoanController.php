<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Lending\Services\Reports\LoanCardService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Http\Requests\Lending\LoanApproveRequest;
use App\Http\Requests\Lending\LoanBeneficiaryWriteOffRequest;
use App\Http\Requests\Lending\LoanDisburseRequest;
use App\Http\Requests\Lending\LoanRequest;
use App\Http\Requests\Lending\LoanRescheduleCancelRequest;
use App\Http\Requests\Lending\LoanRescheduleRequest;
use App\Http\Requests\Lending\LoanUpdateRequest;
use App\Http\Requests\Lending\LoanVerifyRequest;
use App\Http\Requests\Lending\LoanWriteOffRequest;
use App\Support\ReportPdf;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LoanController
{
    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'proposal');
        $view = $request->query('view', 'table') === 'kanban' ? 'kanban' : 'table';
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request->query('per_page'));
        $sort = $this->sort($tab, (string) $request->query('sort', ''));
        $direction = $this->direction((string) $request->query('direction', 'desc'));

        $loan = Loan::class;

        $query = $loan::query()
            ->with([
                'product:row_id,code,name',
                'borrower.group:row_id,name,address,organization_unit_row_id',
                'borrower.group.village:row_id,name',
                'beneficiaries',
                'installments',
                'payments.allocations',
                'statusHistories' => fn ($q) => $q->orderBy('changed_at'),
            ]);

        switch ($tab) {
            case 'proposal':
                $query->where('status', 'draft');
                break;
            case 'verifikasi':
                $query->where('status', 'verified');
                break;
            case 'waiting':
                $query->whereIn('status', ['waiting', 'approved']);
                break;
            case 'aktif':
                $query->whereIn('status', ['active', 'disbursed'])
                    ->whereHas('installments', function ($q) {
                        $q->whereRaw('principal_due > principal_paid');
                    });
                break;
            case 'lunas':
                $query->where(function ($q): void {
                    $q->whereIn('status', ['completed', 'written_off', 'rescheduled'])
                        ->orWhere(function ($inner): void {
                            $inner->whereIn('status', ['active', 'disbursed'])
                                ->whereDoesntHave('installments', function ($inst) {
                                    $inst->whereRaw('principal_due > principal_paid');
                                });
                        });
                });
                break;
            default:
                $query->whereRaw('1 = 0');
                break;
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('loan_number', 'like', "%{$search}%")
                ->orWhereHas('borrower.group', fn ($g) => $g->where('name', 'like', "%{$search}%"))
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"))
            );
        }

        $allowedSort = $this->sortOptions($tab);
        $loans = $query->orderBy($sort ?: ($allowedSort[0] ?? 'proposed_at'), $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Loan $loan): array => $this->presentLoan($loan, $tab));

        if ($view === 'kanban') {
            $grouped = $loan::query()
                ->with([
                    'beneficiaries.member.person',
                    'installments',
                    'statusHistories' => fn ($q) => $q->orderBy('changed_at'),
                ])
                ->with(['borrower.group.village:row_id,name', 'product:row_id,code,name'])
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                    ->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('borrower.group', fn ($g) => $g->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"))))
                ->where(function ($query): void {
                    $query->whereIn('status', ['draft', 'verified', 'waiting', 'approved', 'active', 'disbursed', 'completed'])
                        ->orWhere(function ($inner): void {
                            $inner->whereIn('status', ['active', 'disbursed'])
                                ->whereDoesntHave('installments', fn ($inst) => $inst->whereRaw('principal_due > principal_paid'));
                        });
                })
                ->orderBy('proposed_at')
                ->get()
                ->map(fn (Loan $loan) => $this->presentLoan($loan, 'aktif'))
                ->groupBy(function (array $row): string {
                    if (in_array($row['status'], ['completed', 'written_off', 'rescheduled'], true)) {
                        return 'lunas';
                    }

                    if (in_array($row['status'], ['active', 'disbursed'], true) && (float) $row['principal_remaining'] <= 0) {
                        return 'lunas';
                    }

                    return match ($row['status']) {
                        'draft' => 'proposal',
                        'verified' => 'verifikasi',
                        'waiting', 'approved' => 'waiting',
                        default => 'aktif',
                    };
                })
                ->map(fn ($items) => $items->values()->all());

            return Inertia::render('Lending/Loans/Index', [
                'loans' => [
                    'data' => [],
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => 1,
                    'last_page' => 1,
                ],
                'kanban' => $grouped,
                'tab' => $tab,
                'view' => $view,
                'columns' => $this->columnsFor($tab),
                'sortable' => $allowedSort,
                'search' => $search,
                'perPage' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
                'disbursementAccounts' => Account::query()
                    ->where('is_active', true)
                    ->where('code', 'like', '1.1.01.__')
                    ->where('code', 'not like', '1.1.01.00')
                    ->orderBy('code')
                    ->get(['row_id', 'code', 'name', 'account_type'])
                    ->map(fn (Account $account): array => [
                        'value' => $account->row_id,
                        'label' => $account->code.' · '.$account->name.' ('.$account->account_type.')',
                    ])->all(),
                'today' => now()->toDateString(),
            ]);
        }

        $allowedSort = $this->sortOptions($tab);
        $loans = $query->orderBy($sort ?: ($allowedSort[0] ?? 'proposed_at'), $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Loan $loan): array => $this->presentLoan($loan, $tab));

        return Inertia::render('Lending/Loans/Index', [
            'loans' => $loans,
            'tab' => $tab,
            'view' => $view,
            'columns' => $this->columnsFor($tab),
            'sortable' => $allowedSort,
            'search' => $search,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function beneficiaryOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '');
        $exclude = array_values(array_filter(array_map('intval', (array) $request->query('exclude', [])), fn ($id) => $id > 0));

        $group = $request->filled('group_id')
            ? Group::query()->with('village')->find((int) $request->query('group_id'))
            : null;

        $members = Member::query()
            ->where('status', 'active')
            ->with('person')
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('row_id', $exclude))
            ->when($group !== null && $group->village, fn ($query) => $query->where('organization_unit_row_id', (int) $group->village->row_id))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('member_number', 'like', "%{$search}%")
                ->orWhereHas('person', fn ($person) => $person
                    ->where('national_identity_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%"))))
            ->orderBy('member_number')
            ->limit(20)
            ->get()
            ->map(fn (Member $member): array => [
                'value' => $member->row_id,
                'label' => ($member->person?->full_name ?? '—').' · '.$member->member_number,
            ])
            ->values();

        return response()->json(['data' => $members]);
    }

    /**
     * @return array<int, array{key: string, label: string, sortable: bool, class?: string}>
     */
    private function columnsFor(string $tab): array
    {
        $action = [['key' => 'actions', 'label' => '', 'sortable' => false, 'class' => 'text-right']];

        return match ($tab) {
            'proposal' => [
                ['key' => 'group_name', 'label' => 'Kelompok & Desa'],
                ['key' => 'proposed_at', 'label' => 'Tgl Pengajuan', 'sortable' => true],
                ['key' => 'proposed_amount', 'label' => 'Nominal Pengajuan', 'sortable' => true, 'class' => 'text-right'],
                ['key' => 'service_rate', 'label' => 'Jasa'],
                ['key' => 'term_months', 'label' => 'Jangka', 'sortable' => true, 'class' => 'text-right'],
                ['key' => 'beneficiaries_count', 'label' => 'Pemanfaat', 'class' => 'text-right'],
                ...$action,
            ],
            'verifikasi' => [
                ['key' => 'group_name', 'label' => 'Kelompok & Desa'],
                ['key' => 'proposed_at', 'label' => 'Tgl Pengajuan', 'sortable' => true],
                ['key' => 'verified_at', 'label' => 'Tgl Verifikasi', 'sortable' => true],
                ['key' => 'verification_amount', 'label' => 'Nominal Verifikasi', 'sortable' => true, 'class' => 'text-right'],
                ['key' => 'service_rate', 'label' => 'Jasa'],
                ['key' => 'term_months', 'label' => 'Jangka', 'class' => 'text-right'],
                ...$action,
            ],
            'waiting' => [
                ['key' => 'group_name', 'label' => 'Kelompok & Desa'],
                ['key' => 'funded_at', 'label' => 'Tgl Pendanaan', 'sortable' => true],
                ['key' => 'allocated_amount', 'label' => 'Alokasi', 'sortable' => true, 'class' => 'text-right'],
                ['key' => 'service_rate', 'label' => 'Jasa'],
                ['key' => 'term_months', 'label' => 'Jangka', 'class' => 'text-right'],
                ['key' => 'beneficiaries_count', 'label' => 'Pemanfaat', 'class' => 'text-right'],
                ...$action,
            ],
            'aktif' => [
                ['key' => 'group_name', 'label' => 'Kelompok & Desa'],
                ['key' => 'disbursed_at', 'label' => 'Tgl Pencairan', 'sortable' => true],
                ['key' => 'allocated_amount', 'label' => 'Alokasi', 'sortable' => true, 'class' => 'text-right'],
                ['key' => 'principal_remaining', 'label' => 'Sisa Pokok', 'sortable' => true, 'class' => 'text-right'],
                ['key' => 'next_due_date', 'label' => 'Angsuran Berikutnya', 'sortable' => true],
                ['key' => 'beneficiaries_count', 'label' => 'Pemanfaat', 'class' => 'text-right'],
                ...$action,
            ],
            'lunas' => [
                ['key' => 'group_name', 'label' => 'Kelompok & Desa'],
                ['key' => 'disbursed_at', 'label' => 'Tgl Cair', 'sortable' => true],
                ['key' => 'completed_at', 'label' => 'Tgl Lunas', 'sortable' => true],
                ['key' => 'allocated_amount', 'label' => 'Alokasi', 'sortable' => true, 'class' => 'text-right'],
                ['key' => 'total_interest_paid', 'label' => 'Total Jasa', 'class' => 'text-right'],
                ...$action,
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function sortOptions(string $tab): array
    {
        return match ($tab) {
            'proposal' => ['proposed_at', 'proposed_amount', 'term_months'],
            'verifikasi' => ['proposed_at', 'verified_at', 'verification_amount'],
            'waiting' => ['funded_at', 'allocated_amount', 'term_months'],
            'aktif' => ['disbursed_at', 'allocated_amount', 'next_due_date', 'principal_remaining'],
            'lunas' => ['disbursed_at', 'completed_at', 'allocated_amount'],
            default => [],
        };
    }

    private function sort(string $tab, string $value): string
    {
        $allowed = $this->sortOptions($tab);

        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLoan(Loan $loan, string $tab): array
    {
        $principalRemaining = 0.0;
        $interestPaid = 0.0;
        $nextDue = null;

        foreach ($loan->installments as $installment) {
            $principalRemaining += (float) $installment->principal_due - (float) $installment->principal_paid;
            $interestPaid += (float) $installment->interest_paid;
            if ($nextDue === null && (float) $installment->principal_due > (float) $installment->principal_paid) {
                $nextDue = $installment->due_date;
            }
        }

        $histories = $loan->statusHistories->keyBy('to_status');
        $snapshot = function (string $key) use ($histories, $loan): ?float {
            $row = $histories->get($key);
            $value = $row?->principal_amount;
            if ($value === null) {
                $value = $loan->principal_amount;
            }

            return $value !== null ? (float) $value : null;
        };

        $verifier = $histories->get('verified');
        $approver = $histories->get('approved') ?? $histories->get('waiting');

        $group = $loan->borrower?->group;
        $serviceRate = $histories->get('draft')?->principal_amount !== null
            ? (float) ($histories->get('draft')?->service_rate_total ?? $loan->service_rate_total ?? $loan->interest_rate ?? 0)
            : (float) ($loan->service_rate_total ?? $loan->interest_rate ?? 0);
        $termMonths = (int) ($histories->get('draft')?->term_months ?? $loan->term_months ?? 0);

        return [
            'row_id' => $loan->row_id,
            'id' => $loan->id,
            'loan_number' => $loan->loan_number ?? '—',
            'proposed_at' => $loan->proposed_at?->format('Y-m-d'),
            'verified_at' => $loan->verified_at?->format('Y-m-d'),
            'approved_at' => $loan->approved_at?->format('Y-m-d'),
            'funded_at' => $loan->funded_at?->format('Y-m-d'),
            'disbursed_at' => $loan->disbursed_at?->format('Y-m-d'),
            'completed_at' => $loan->completed_at?->format('Y-m-d'),
            'principal_amount' => (float) $loan->principal_amount,
            'principal_remaining' => round($principalRemaining, 2),
            'total_interest_paid' => round($interestPaid, 2),
            'next_due_date' => $nextDue?->format('Y-m-d'),
            'proposed_amount' => $snapshot('draft'),
            'verification_amount' => $snapshot('verified'),
            'allocated_amount' => $snapshot('active') ?? $snapshot('disbursed'),
            'service_rate' => round($serviceRate, 2),
            'term_months' => $termMonths,
            'verifier_name' => $verifier?->changed_by_user_id ? '#'.$verifier->changed_by_user_id : '—',
            'approver_name' => $approver?->changed_by_user_id ? '#'.$approver->changed_by_user_id : '—',
            'status' => $loan->status,
            'product' => $loan->product?->only(['row_id', 'code', 'name']),
            'group_name' => $group?->name ?? '—',
            'group_address' => trim(($group?->address ?? '').' '.($group?->village?->name ?? '')),
            'beneficiaries_count' => $loan->beneficiaries->count(),
            'beneficiaries' => $loan->beneficiaries->map(fn ($beneficiary): array => [
                'row_id' => $beneficiary->row_id,
                'member_row_id' => $beneficiary->member_row_id,
                'member_id' => $beneficiary->member?->id,
                'name' => $beneficiary->member?->person?->full_name,
                'nik' => $beneficiary->member?->person?->national_identity_number,
                'proposed_amount' => (float) ($beneficiary->proposed_amount ?? $beneficiary->allocated_amount),
                'verified_amount' => $beneficiary->verified_amount !== null ? (float) $beneficiary->verified_amount : null,
                'allocated_amount' => (float) $beneficiary->allocated_amount,
            ])->values()->all(),
        ];
    }

    public function create(TenantLoanProductProvisioner $provisioner): Response
    {
        if (LoanProduct::query()->active()->doesntExist()) {
            $provisioner->ensureDefaults();
        }

        return Inertia::render('Lending/Loans/Form', [
            ...$this->formOptions(),
        ]);
    }

    public function store(LoanRequest $request, LoanService $loans): RedirectResponse
    {
        $loan = $loans->createProposal($request->validated(), (int) $request->user()->row_id);

        return to_route('lending.loans.show', ['loan' => $loan->row_id])->with('success', 'Proposal pinjaman berhasil didaftarkan.');
    }

    public function update(LoanUpdateRequest $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        if (! in_array($loan->status, ['draft', 'verified'], true)) {
            return back()->with('error', 'Proposal tidak dapat diedit setelah masuk tahap alokasi.');
        }

        $loans->updateProposal($loan, $request->validated(), (int) $request->user()->row_id);

        return to_route('lending.loans.show', ['loan' => $loan->row_id])->with('success', 'Proposal pinjaman berhasil diperbarui.');
    }

    public function destroy(Request $request, Loan $loan, LoanService $loans, PermissionChecker $permissions): RedirectResponse
    {
        $permissions->denyUnless($request->user(), 'loans.manage');

        try {
            $loans->deleteProposal($loan);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('lending.loans.index')->with('success', 'Proposal pinjaman berhasil dihapus.');
    }

    public function removeBeneficiary(Request $request, Loan $loan, int $member, LoanService $loans): RedirectResponse
    {
        if (! in_array($loan->status, ['draft', 'verified'], true)) {
            return back()->with('error', 'Pemanfaat tidak dapat dihapus setelah tahap alokasi.');
        }

        try {
            $loans->removeBeneficiary($loan, $member, (int) $request->user()->row_id);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('lending.loans.show', ['loan' => $loan->row_id])->with('success', 'Pemanfaat berhasil dihapus dari proposal.');
    }

    public function show(Loan $loan): Response
    {
        $loan->load([
            'product:row_id,code,name,default_interest_rate,default_term_months',
            'borrower.group.village:row_id,name',
            'borrower.group.activeMemberships.member.person',
            'committee',
            'beneficiaries.member.person',
            'installments',
            'payments.allocations',
            'statusHistories' => fn ($q) => $q->orderBy('changed_at'),
            'statusHistories.changedByUser:row_id,name',
        ]);

        $disbursementAccount = $loan->disbursement_account_row_id
            ? Account::query()->where('row_id', (int) $loan->disbursement_account_row_id)->first(['row_id', 'id', 'code', 'name', 'account_type'])
            : null;

        return Inertia::render('Lending/Loans/Show', [
            'loan' => $this->presentLoanDetail($loan),
            'card_url' => $loan->installments->isNotEmpty()
                ? route('lending.loans.card', ['loan' => $loan->row_id])
                : null,
            'disbursement_account' => $disbursementAccount?->only(['row_id', 'id', 'code', 'name', 'account_type']),
            'disbursementAccounts' => Account::query()
                ->where('is_active', true)
                ->where('code', 'like', '1.1.01.__')
                ->where('code', 'not like', '1.1.01.00')
                ->orderBy('code')
                ->get(['row_id', 'code', 'name', 'account_type'])
                ->map(fn (Account $account): array => [
                    'value' => $account->row_id,
                    'label' => $account->code.' · '.$account->name.' ('.$account->account_type.')',
                ])->all(),
            'today' => now()->toDateString(),
        ]);
    }

    public function cardReprint(Loan $loan, LoanCardService $cards, ReportPdf $pdf): HttpResponse|StreamedResponse
    {
        $loan->load(['installments']);
        try {
            $data = $cards->build($loan);
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        return $pdf->stream(
            'reports.pdf.loan_card_reprint',
            $data,
            'cetak-kartu-angsuran-'.$loan->id.'.pdf',
            'landscape',
        );
    }

    public function card(Loan $loan, LoanCardService $cards, ReportPdf $pdf): HttpResponse|StreamedResponse
    {
        $loan->load(['installments']);
        try {
            $data = $cards->build($loan);
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        return $pdf->stream(
            'reports.pdf.loan_card',
            $data,
            'kartu-angsuran-'.$loan->id.'.pdf',
            'landscape',
        );
    }

    public function verify(LoanVerifyRequest $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        $loans->verify($loan, $request->validated(), (int) $request->user()->row_id);

        if ($request->boolean('from_kanban')) {
            return redirect('/lending/loans?view=kanban')->with('success', 'Pinjaman berhasil diverifikasi.');
        }

        return to_route('lending.loans.show', ['loan' => $loan->row_id])->with('success', 'Pinjaman berhasil diverifikasi.');
    }

    public function approve(LoanApproveRequest $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        $loans->approve($loan, $request->validated(), (int) $request->user()->row_id);

        if ($request->boolean('from_kanban')) {
            return redirect('/lending/loans?view=kanban')->with('success', 'Alokasi pinjaman berhasil ditetapkan.');
        }

        return to_route('lending.loans.show', ['loan' => $loan->row_id])->with('success', 'Alokasi pinjaman berhasil ditetapkan.');
    }

    public function disburse(LoanDisburseRequest $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        $loans->disburse($loan, $request->validated(), (int) $request->user()->row_id);

        if ($request->boolean('from_kanban')) {
            return redirect('/lending/loans?view=kanban')->with('success', 'Pencairan pinjaman berhasil dicatat.');
        }

        return to_route('lending.loans.show', ['loan' => $loan->row_id])->with('success', 'Pencairan pinjaman berhasil dicatat.');
    }

    public function revert(Loan $loan, LoanService $loans): RedirectResponse
    {
        $loans->revertToDraft($loan, (int) request()->user()->row_id);

        if ((bool) request()->query('from_kanban')) {
            return redirect('/lending/loans?view=kanban')->with('success', 'Pinjaman dikembalikan ke status proposal.');
        }

        return to_route('lending.loans.show', ['loan' => $loan->row_id])->with('success', 'Pinjaman dikembalikan ke status proposal.');
    }

    public function reschedule(LoanRescheduleRequest $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        try {
            $newLoan = $loans->reschedule($loan, $request->validated(), (int) $request->user()->row_id);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('lending.loans.show', ['loan' => $newLoan->row_id])
            ->with('success', 'Reschedule berhasil. Pinjaman baru dibuat dari sisa pokok.');
    }

    public function cancelReschedule(LoanRescheduleCancelRequest $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        try {
            $oldLoan = $loans->cancelReschedule($loan, $request->validated(), (int) $request->user()->row_id);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('lending.loans.show', ['loan' => $oldLoan->row_id])
            ->with('success', 'Reschedule berhasil dibatalkan. Pinjaman asal dikembalikan ke status aktif.');
    }

    public function writeOff(LoanWriteOffRequest $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        try {
            $loans->writeOff($loan, $request->validated(), (int) $request->user()->row_id);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('lending.loans.show', ['loan' => $loan->row_id])
            ->with('success', 'Penghapusan piutang berhasil dicatat.');
    }

    public function writeOffBeneficiary(
        LoanBeneficiaryWriteOffRequest $request,
        Loan $loan,
        int $member,
        LoanService $loans,
    ): RedirectResponse {
        try {
            $loans->writeOffBeneficiary($loan, $member, $request->validated(), (int) $request->user()->row_id);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('lending.loans.show', ['loan' => $loan->row_id])
            ->with('success', 'Penghapusan piutang pemanfaat berhasil dicatat.');
    }

    public function complete(Request $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        $validated = $request->validate([
            'completed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $loans->complete($loan, $validated, (int) $request->user()->row_id);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('lending.loans.show', ['loan' => $loan->row_id])
            ->with('success', 'Validasi pelunasan pinjaman berhasil disimpan.');
    }

    public function setCommittee(Request $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        $data = $request->validate([
            'chair_id' => ['required', 'integer', 'min:1'],
            'secretary_id' => ['required', 'integer', 'min:1', 'different:chair_id'],
            'treasurer_id' => ['required', 'integer', 'min:1', 'different:chair_id', 'different:secretary_id'],
        ], [], [
            'chair_id' => 'ketua',
            'secretary_id' => 'sekretaris',
            'treasurer_id' => 'bendahara',
        ]);

        try {
            $loans->setCommittee($loan, $data, (int) $request->user()->row_id);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('lending.loans.show', ['loan' => $loan->row_id])
            ->with('success', 'Pengurus pinjaman berhasil disimpan. Data ini tidak dapat diganti.');
    }

    private function formOptions(): array
    {
        return [
            'products' => LoanProduct::query()->active()->orderBy('name')->get(['row_id', 'code', 'name', 'default_interest_rate', 'default_term_months', 'minimum_amount', 'maximum_amount', 'borrower_scope'])->toArray(),
            'groups' => Group::query()
                ->with([
                    'activeOfficers.member.person',
                    'activeMemberships.member.person',
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (Group $group): array => [
                    'value' => $group->row_id,
                    'label' => $group->name,
                    'chair' => $this->officerOption($group, 'chair'),
                    'secretary' => $this->officerOption($group, 'secretary'),
                    'treasurer' => $this->officerOption($group, 'treasurer'),
                    'members' => $group->activeMemberships->map(fn ($membership): array => [
                        'value' => $membership->member?->row_id,
                        'label' => ($membership->member?->person?->full_name ?? '—').' · '.$membership->member?->member_number,
                    ])->values()->all(),
                ])->values()->all(),
            'committee_members' => Member::query()
                ->where('status', 'active')
                ->with('person')
                ->orderBy('member_number')
                ->limit(500)
                ->get()
                ->map(fn (Member $member): array => [
                    'value' => (int) $member->row_id,
                    'label' => ($member->person?->full_name ?? '—').' · '.$member->member_number,
                ])
                ->values()
                ->all(),
        ];
    }

    private function officerOption(Group $group, string $position): ?array
    {
        $officer = $group->activeOfficers->firstWhere('position', $position);

        if ($officer === null) {
            return null;
        }

        return [
            'member_row_id' => $officer->member_row_id,
            'name' => $officer->member?->person?->full_name,
        ];
    }

    private function perPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [15, 30, 50, 100], true) ? $value : 15;
    }

    private function direction(string $value): string
    {
        return in_array($value, ['asc', 'desc'], true) ? $value : 'desc';
    }

    private function presentLoanDetail(Loan $loan): array
    {
        $principalRemaining = 0.0;
        $interestPaid = 0.0;
        $interestDue = 0.0;
        $principalPaid = 0.0;
        $nextDue = null;
        $paidInstallments = 0;
        $totalInstallments = $loan->installments->pluck('installment_number')->unique()->count();

        $histories = $loan->statusHistories->keyBy('to_status');
        $snapshot = function (string $key) use ($histories, $loan): ?float {
            $row = $histories->get($key);
            $value = $row?->principal_amount;
            if ($value === null) {
                $value = $loan->principal_amount;
            }

            return $value !== null ? (float) $value : null;
        };

        foreach ($loan->installments as $installment) {
            $principalRemaining += (float) $installment->principal_due - (float) $installment->principal_paid;
            $principalPaid += (float) $installment->principal_paid;
            $interestPaid += (float) $installment->interest_paid;
            $interestDue += (float) $installment->interest_due;
            if ((float) $installment->principal_due > 0 && (float) $installment->principal_paid >= (float) $installment->principal_due) {
                $paidInstallments++;
            }
            if ($nextDue === null && (float) $installment->principal_due > (float) $installment->principal_paid) {
                $nextDue = $installment->due_date;
            }
        }

        $group = $loan->borrower?->group;
        $committeeByPosition = $loan->committee->keyBy('position');

        $histories = $loan->statusHistories->map(fn ($h): array => [
            'from_status' => $h->from_status,
            'to_status' => $h->to_status,
            'principal_amount' => $h->principal_amount !== null ? (float) $h->principal_amount : null,
            'notes' => $h->notes,
            'term_months' => $h->term_months !== null ? (int) $h->term_months : null,
            'service_rate_total' => $h->service_rate_total !== null ? (float) $h->service_rate_total : null,
            'principal_frequency' => $h->principal_frequency,
            'interest_frequency' => $h->interest_frequency,
            'principal_grace_months' => $h->principal_grace_months !== null ? (int) $h->principal_grace_months : null,
            'interest_grace_months' => $h->interest_grace_months !== null ? (int) $h->interest_grace_months : null,
            'changed_at' => $h->changed_at?->format('Y-m-d H:i'),
            'changed_by_user_id' => $h->changed_by_user_id,
            'changed_by_user_name' => $h->changedByUser?->name,
        ])->values()->all();

        return [
            'row_id' => $loan->row_id,
            // Local/legacy id for display & reports (docs: id lama immutable).
            'id' => $loan->id,
            'legacy_source' => $loan->legacy_source,
            'loan_number' => $loan->loan_number,
            'status' => $loan->status,
            'proposed_at' => $loan->proposed_at?->format('Y-m-d'),
            'verified_at' => $loan->verified_at?->format('Y-m-d'),
            'approved_at' => $loan->approved_at?->format('Y-m-d'),
            'funded_at' => $loan->funded_at?->format('Y-m-d'),
            'disbursed_at' => $loan->disbursed_at?->format('Y-m-d'),
            'completed_at' => $loan->completed_at?->format('Y-m-d'),
            'principal_amount' => (float) $loan->principal_amount,
            'principal_remaining' => round($principalRemaining, 2),
            'principal_paid' => round($principalPaid, 2),
            'total_interest_due' => round($interestDue, 2),
            'total_interest_paid' => round($interestPaid, 2),
            'proposed_amount' => $snapshot('draft'),
            'verification_amount' => $snapshot('verified'),
            'allocated_amount' => $snapshot('active') ?? $snapshot('disbursed'),
            'interest_rate' => (float) $loan->interest_rate,
            'service_rate_total' => (float) $loan->service_rate_total,
            'term_months' => (int) $loan->term_months,
            'installment_method' => $loan->installment_method,
            'principal_frequency' => $loan->principal_frequency,
            'interest_frequency' => $loan->interest_frequency,
            'principal_grace_months' => (int) ($loan->principal_grace_months ?? 0),
            'interest_grace_months' => (int) ($loan->interest_grace_months ?? 0),
            'rounding_step' => $loan->rounding_step !== null ? (int) $loan->rounding_step : null,
            'verification_notes' => $loan->verification_notes,
            'guidance_notes' => $loan->guidance_notes,
            'disbursement_notes' => $loan->disbursement_notes,
            'disbursement_account_row_id' => $loan->disbursement_account_row_id,
            'rescheduled_from_loan_row_id' => $loan->rescheduled_from_loan_row_id !== null ? (int) $loan->rescheduled_from_loan_row_id : null,
            'next_due_date' => $nextDue?->format('Y-m-d'),
            'paid_installments' => $paidInstallments,
            'total_installments' => $totalInstallments,
            'progress_percent' => $totalInstallments > 0 ? (int) round(($paidInstallments / $totalInstallments) * 100) : 0,
            'product' => $loan->product?->only(['row_id', 'code', 'name', 'default_interest_rate', 'default_term_months', 'rounding_method']),
            'group' => $group ? [
                'row_id' => $group->row_id,
                'name' => $group->name,
                'address' => $group->address,
                'village' => $group->village?->only(['row_id', 'name']),
            ] : null,
            'committee' => [
                'chair' => $this->committeeEntry($committeeByPosition->get('chair')),
                'secretary' => $this->committeeEntry($committeeByPosition->get('secretary')),
                'treasurer' => $this->committeeEntry($committeeByPosition->get('treasurer')),
            ],
            'committee_editable' => $loan->committee->isEmpty(),
            'committee_member_options' => $this->committeeMemberOptions($loan),
            'beneficiaries' => $loan->beneficiaries->map(fn ($b): array => [
                'row_id' => $b->row_id,
                'member_row_id' => $b->member_row_id,
                'member_id' => $b->member?->id,
                'name' => $b->member?->person?->full_name,
                'nik' => $b->member?->person?->national_identity_number,
                'proposed_amount' => (float) ($b->proposed_amount ?? $b->allocated_amount),
                'verified_amount' => $b->verified_amount !== null ? (float) $b->verified_amount : null,
                'allocated_amount' => (float) $b->allocated_amount,
                'written_off_at' => $b->written_off_at?->format('Y-m-d H:i:s'),
                'written_off_amount' => $b->written_off_amount !== null ? (float) $b->written_off_amount : null,
            ])->values()->all(),
            'installments' => $loan->installments->map(fn ($i): array => [
                'row_id' => $i->row_id,
                'installment_number' => (int) $i->installment_number,
                'component' => $i->component,
                'due_date' => $i->due_date?->format('Y-m-d'),
                'principal_due' => (float) $i->principal_due,
                'interest_due' => (float) $i->interest_due,
                'principal_paid' => (float) $i->principal_paid,
                'interest_paid' => (float) $i->interest_paid,
                'status' => $i->status,
                'paid_at' => $i->paid_at?->format('Y-m-d H:i'),
            ])->values()->all(),
            'payments' => $loan->payments->map(fn ($p): array => [
                'row_id' => $p->row_id,
                'paid_at' => $p->paid_at?->format('Y-m-d'),
                'amount' => (float) $p->amount,
                'principal_paid' => (float) $p->allocations->where('component', 'principal')->sum('amount'),
                'interest_paid' => (float) $p->allocations->where('component', 'interest')->sum('amount'),
                'payment_method' => $p->payment_method,
            ])->values()->all(),
            'status_histories' => $histories,
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function committeeMemberOptions(Loan $loan): array
    {
        $fromBeneficiaries = $loan->beneficiaries
            ->filter(fn ($b) => $b->member !== null)
            ->map(fn ($b): array => [
                'value' => (int) $b->member_row_id,
                'label' => ($b->member?->person?->full_name ?? '—').' · '.($b->member?->member_number ?? $b->member?->id ?? ''),
            ])
            ->values()
            ->all();

        if ($fromBeneficiaries !== []) {
            return $fromBeneficiaries;
        }

        $group = $loan->borrower?->group;
        if ($group === null) {
            return [];
        }

        return $group->activeMemberships()
            ->with('member.person')
            ->get()
            ->filter(fn ($m) => $m->member !== null)
            ->map(fn ($m): array => [
                'value' => (int) $m->member_row_id,
                'label' => ($m->member?->person?->full_name ?? '—').' · '.($m->member?->member_number ?? ''),
            ])
            ->values()
            ->all();
    }

    private function committeeEntry(mixed $committee): ?array
    {
        if ($committee === null) {
            return null;
        }

        return [
            'member_row_id' => $committee->member_row_id,
            'name' => $committee->member_name_snapshot,
            'snapshot_at' => $committee->snapshot_at?->format('Y-m-d'),
        ];
    }

    public function exportPdf(Request $request, ReportPdf $pdf): HttpResponse|StreamedResponse
    {
        $tab = (string) $request->query('tab', 'all_active');
        $status = (string) $request->query('status', '');
        $startDate = (string) $request->query('start_date', '');
        $endDate = (string) $request->query('end_date', '');
        $search = trim((string) $request->query('search', ''));

        $query = Loan::query()
            ->with([
                'product:row_id,code,name',
                'borrower.group:row_id,name,address,organization_unit_row_id,leader_name',
                'borrower.group.village:row_id,name',
                'installments',
            ]);

        $statusLabel = 'Semua Pinjaman';
        if ($status !== '') {
            $query->where('status', $status);
            $statusLabel = ucfirst($status);
        } else {
            switch ($tab) {
                case 'proposal':
                    $query->whereIn('status', ['draft', 'proposed']);
                    $statusLabel = 'Proposal (Pengajuan)';
                    break;
                case 'verifikasi':
                    $query->where('status', 'verified');
                    $statusLabel = 'Terverifikasi';
                    break;
                case 'waiting':
                    $query->whereIn('status', ['waiting', 'approved']);
                    $statusLabel = 'Waiting (Menunggu Pencairan)';
                    break;
                case 'aktif':
                    $query->whereIn('status', ['active', 'disbursed']);
                    $statusLabel = 'Aktif (Berjalan)';
                    break;
                case 'lunas':
                    $query->whereIn('status', ['completed', 'written_off', 'rescheduled']);
                    $statusLabel = 'Lunas / Selesai';
                    break;
                case 'all_active':
                    $query->whereIn('status', ['draft', 'proposed', 'verified', 'waiting', 'approved', 'active', 'disbursed']);
                    $statusLabel = 'Pinjaman Terkini (Proposal, Verifikasi, Waiting & Aktif)';
                    break;
                default:
                    $statusLabel = 'Semua Pinjaman';
                    break;
            }
        }

        if ($startDate !== '') {
            $query->whereDate('proposed_at', '>=', $startDate);
        }
        if ($endDate !== '') {
            $query->whereDate('proposed_at', '<=', $endDate);
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('loan_number', 'like', "%{$search}%")
                ->orWhereHas('borrower.group', fn ($g) => $g->where('name', 'like', "%{$search}%"))
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"))
            );
        }

        $loansData = $query->orderBy('proposed_at', 'desc')->get();

        $totals = [
            'principal_amount' => 0.0,
            'principal_paid' => 0.0,
            'principal_remaining' => 0.0,
        ];

        $items = [];
        foreach ($loansData as $loan) {
            $principal = (float) $loan->principal_amount;
            $paid = (float) $loan->installments->sum('principal_paid');
            $remaining = max(0.0, $principal - $paid);

            $totals['principal_amount'] += $principal;
            $totals['principal_paid'] += $paid;
            $totals['principal_remaining'] += $remaining;

            $group = $loan->borrower?->group;
            $items[] = [
                'row_id' => $loan->row_id,
                'loan_number' => $loan->loan_number,
                'group_name' => $group?->name ?? 'Pinjaman Perorangan/Lain',
                'leader_name' => $group?->leader_name ?? '',
                'village_name' => $group?->village?->name ?? '',
                'address' => $group?->address ?? '',
                'proposed_at' => $loan->proposed_at?->toDateString(),
                'principal_amount' => $principal,
                'interest_rate' => (float) $loan->interest_rate,
                'installment_method' => $loan->installment_method ?: 'flat',
                'term_months' => (int) $loan->term_months,
                'principal_paid' => $paid,
                'principal_remaining' => $remaining,
                'status' => $loan->status,
            ];
        }

        $profile = OrganizationProfile::query()->first();
        $identity = [
            'legal_name' => $profile?->legal_name ?? config('app.name'),
            'short_name' => $profile?->short_name ?? config('app.name'),
            'district_name' => $profile?->district_name ?? '',
            'regency_name' => $profile?->regency_name ?? '',
            'address' => $profile?->address ?? '',
            'phone' => $profile?->phone ?? '',
            'registration_number' => $profile?->registration_number ?? '',
            'logo_url' => $profile?->logo_path ? asset('storage/'.ltrim($profile->logo_path, '/')) : null,
        ];

        $signatures = [
            'manager' => $profile?->manager_name ?? '..................................',
            'secretary' => $profile?->secretary_name ?? '..................................',
            'treasurer' => $profile?->treasurer_name ?? '..................................',
            'verifier' => '..................................',
        ];

        $filename = 'daftar-pinjaman-'.($tab ?: 'all').'-'.date('Ymd-His').'.pdf';

        return $pdf->stream(
            'reports.pdf.loan_list',
            [
                'identity' => $identity,
                'status_label' => $statusLabel,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'loans' => $items,
                'totals' => $totals,
                'signatures' => $signatures,
            ],
            $filename,
            'landscape'
        );
    }
}
