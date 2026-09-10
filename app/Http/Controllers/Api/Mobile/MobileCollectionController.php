<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Notifications\Services\WhatsappNotificationService;
use App\Models\User;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class MobileCollectionController
{
    public function index(Request $request): JsonResponse
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
                'installments',
            ])
            ->whereIn('status', ['active', 'disbursed']);

        // Scope to user's assigned village if restricted
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
                    ->orWhereHas('borrower.member.person', fn ($p) => $p->where('full_name', 'like', "%{$search}%")
                        ->orWhere('national_identity_number', 'like', "%{$search}%"));
            });
        }

        $loans = $query->orderBy('row_id', 'desc')->paginate($perPage);

        $items = collect($loans->items())->map(function (Loan $loan): array {
            $borrower = $loan->borrower;
            $group = $borrower?->group;
            $member = $borrower?->member;

            $borrowerName = $group !== null ? $group->name : ($member?->person?->full_name ?? 'Nasabah');
            $borrowerType = $group !== null ? 'Kelompok' : 'Individu';
            $villageName = $group?->village?->name ?? $member?->village?->name ?? '-';

            // Outstanding calculation
            $totalPrincipal = (float) $loan->principal_amount;
            $paidPrincipal = (float) DB::connection('tenant')
                ->table('journal_lines as jl')
                ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
                ->where('je.source_type', 'loan_installment')
                ->where('je.source_row_id', $loan->row_id)
                ->where('je.status', 'posted')
                ->where('jl.credit', '>', 0)
                ->where('jl.line_number', 2)
                ->sum('jl.credit');

            $remainingPrincipal = max(0.0, $totalPrincipal - $paidPrincipal);

            $totalInterest = (float) $loan->installments->sum('interest_due');
            $paidInterest = (float) DB::connection('tenant')
                ->table('journal_lines as jl')
                ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
                ->where('je.source_type', 'loan_installment')
                ->where('je.source_row_id', $loan->row_id)
                ->where('je.status', 'posted')
                ->where('jl.credit', '>', 0)
                ->where('jl.line_number', 3)
                ->sum('jl.credit');

            $remainingInterest = max(0.0, $totalInterest - $paidInterest);

            // Next due installment
            $nextInstallment = $loan->installments
                ->where('status', '!=', 'paid')
                ->sortBy('installment_number')
                ->first();

            $monthlyDue = $nextInstallment !== null
                ? (float) ($nextInstallment->principal_due + $nextInstallment->interest_due)
                : 0.0;

            return [
                'id' => $loan->row_id,
                'loan_number' => $loan->loan_number ?? "PINJ-{$loan->id}",
                'borrower_name' => $borrowerName,
                'borrower_type' => $borrowerType,
                'village_name' => $villageName,
                'product_name' => $loan->product?->name ?? 'Pinjaman Reguler',
                'principal_amount' => $totalPrincipal,
                'remaining_principal' => $remainingPrincipal,
                'remaining_interest' => $remainingInterest,
                'monthly_due' => $monthlyDue,
                'next_due_date' => $nextInstallment?->due_date?->toDateString(),
                'status' => $loan->status,
            ];
        });

        return ApiResponse::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ],
        ], 'Daftar penagihan berhasil dimuat.');
    }

    public function show(Loan $loan): JsonResponse
    {
        $loan->load([
            'product:row_id,code,name',
            'borrower.group:row_id,name,address,organization_unit_row_id',
            'borrower.group.village:row_id,name',
            'borrower.member.person:row_id,full_name,phone',
            'borrower.member.village:row_id,name',
            'beneficiaries.member.person:row_id,full_name,national_identity_number,phone',
            'installments' => fn ($q) => $q->orderBy('installment_number', 'asc'),
        ]);

        $borrower = $loan->borrower;
        $group = $borrower?->group;
        $member = $borrower?->member;

        $borrowerName = $group !== null ? $group->name : ($member?->person?->full_name ?? 'Nasabah');
        $borrowerType = $group !== null ? 'Kelompok' : 'Individu';
        $villageName = $group?->village?->name ?? $member?->village?->name ?? '-';

        $totalPrincipal = (float) $loan->principal_amount;
        $paidPrincipal = (float) DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
            ->where('je.source_type', 'loan_installment')
            ->where('je.source_row_id', $loan->row_id)
            ->where('je.status', 'posted')
            ->where('jl.credit', '>', 0)
            ->where('jl.line_number', 2)
            ->sum('jl.credit');

        $remainingPrincipal = max(0.0, $totalPrincipal - $paidPrincipal);

        $totalInterest = (float) $loan->installments->sum('interest_due');
        $paidInterest = (float) DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
            ->where('je.source_type', 'loan_installment')
            ->where('je.source_row_id', $loan->row_id)
            ->where('je.status', 'posted')
            ->where('jl.credit', '>', 0)
            ->where('jl.line_number', 3)
            ->sum('jl.credit');

        $remainingInterest = max(0.0, $totalInterest - $paidInterest);

        $nextInstallment = $loan->installments
            ->where('status', '!=', 'paid')
            ->sortBy('installment_number')
            ->first();

        // Beneficiary options for payment assignment
        $beneficiaries = $loan->beneficiaries->map(function ($b): array {
            $m = $b->member;
            $p = $m?->person;

            return [
                'id' => $b->member_row_id,
                'name' => $p?->full_name ?? "Anggota #{$b->member_row_id}",
                'nik' => $p?->national_identity_number,
                'phone' => $p?->phone,
                'allocated_amount' => (float) $b->allocated_amount,
            ];
        });

        if ($beneficiaries->isEmpty() && $member !== null) {
            $beneficiaries = collect([[
                'id' => $member->row_id,
                'name' => $member->person?->full_name ?? "Anggota #{$member->row_id}",
                'nik' => $member->person?->national_identity_number,
                'phone' => $member->person?->phone,
                'allocated_amount' => $totalPrincipal,
            ]]);
        }

        // Available cash accounts (default to 1.1.01.xx)
        $cashAccounts = Account::on('tenant')
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', 'like', '1.1.01.%')
            ->orderBy('code')
            ->get(['row_id', 'code', 'name']);

        return ApiResponse::success([
            'id' => $loan->row_id,
            'loan_number' => $loan->loan_number ?? "PINJ-{$loan->id}",
            'borrower_name' => $borrowerName,
            'borrower_type' => $borrowerType,
            'village_name' => $villageName,
            'product_name' => $loan->product?->name ?? 'Pinjaman Reguler',
            'principal_amount' => $totalPrincipal,
            'remaining_principal' => $remainingPrincipal,
            'remaining_interest' => $remainingInterest,
            'suggested_principal' => $nextInstallment !== null ? (float) max(0.0, $nextInstallment->principal_due - $nextInstallment->principal_paid) : $remainingPrincipal,
            'suggested_interest' => $nextInstallment !== null ? (float) max(0.0, $nextInstallment->interest_due - $nextInstallment->interest_paid) : $remainingInterest,
            'next_installment_number' => $nextInstallment?->installment_number ?? 1,
            'next_due_date' => $nextInstallment?->due_date?->toDateString(),
            'beneficiaries' => $beneficiaries,
            'cash_accounts' => $cashAccounts->map(fn ($acc) => [
                'id' => $acc->row_id,
                'code' => $acc->code,
                'name' => $acc->name,
            ]),
        ], 'Detail penagihan berhasil dimuat.');
    }

    public function pay(
        Request $request,
        Loan $loan,
        LoanService $loanService,
        WhatsappNotificationService $notices,
        TenantContext $context,
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'member_id' => ['required', 'integer'],
            'principal_amount' => ['required', 'numeric', 'min:0'],
            'interest_amount' => ['required', 'numeric', 'min:0'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'cash_account_row_id' => ['nullable', 'integer'],
            'transaction_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'member_id.required' => 'Pemanfaat / Pembayar wajib dipilih.',
            'principal_amount.required' => 'Nominal pokok wajib diisi.',
            'interest_amount.required' => 'Nominal jasa wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', 422, $validator->errors()->toArray());
        }

        $principal = (float) $request->input('principal_amount');
        $interest = (float) $request->input('interest_amount');
        $penalty = (float) ($request->input('penalty_amount') ?? 0);
        $totalPaid = $principal + $interest + $penalty;

        if ($totalPaid <= 0) {
            return ApiResponse::error('Total pembayaran harus lebih besar dari Rp 0.', 422);
        }

        $cashAccountId = $request->input('cash_account_row_id');
        if ($cashAccountId === null) {
            $defaultCash = Account::on('tenant')
                ->where('is_active', true)
                ->where('is_postable', true)
                ->where('code', 'like', '1.1.01.%')
                ->orderBy('code')
                ->first();
            $cashAccountId = $defaultCash?->row_id;
        }

        if ($cashAccountId === null) {
            return ApiResponse::error('Akun kas penerima tidak ditemukan dalam COA.', 422);
        }

        $transactionDate = $request->input('transaction_date') ?? CarbonImmutable::now()->toDateString();
        $memberId = (int) $request->input('member_id');
        $description = $request->input('description') ?? "Angsuran pinjaman {$loan->loan_number} oleh pemanfaat #{$memberId}";

        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->row_id;

        $payload = [
            'loan_id' => (int) $loan->row_id,
            'cash_account_row_id' => (int) $cashAccountId,
            'reference' => (int) $memberId,
            'transaction_date' => (string) $transactionDate,
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'penalty_amount' => $penalty,
            'description' => (string) $description,
        ];

        try {
            $entry = $loanService->recordInstallmentPayment($payload, $userId);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        // Payer member & phone
        $payerMember = Member::on('tenant')->with('person')->find($memberId);
        $payerName = $payerMember?->person?->full_name ?? "Anggota #{$memberId}";
        $payerPhone = $payerMember?->person?->phone;

        // Organization Profile
        $orgProfile = OrganizationProfile::query()->first();
        $orgName = $orgProfile?->short_name ?? $orgProfile?->legal_name ?? $context->tenant()->name;

        // Recalculate remaining balances from posted journal lines
        $totalLoanPrincipal = (float) $loan->principal_amount;
        $paidPrincipal = (float) DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
            ->where('je.source_type', 'loan_installment')
            ->where('je.source_row_id', $loan->row_id)
            ->where('je.status', 'posted')
            ->where('jl.credit', '>', 0)
            ->where('jl.line_number', 2)
            ->sum('jl.credit');

        $remainingPrincipal = max(0.0, $totalLoanPrincipal - $paidPrincipal);

        $totalLoanInterest = (float) $loan->installments->sum('interest_due');
        $paidInterest = (float) DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', 'je.row_id', '=', 'jl.journal_entry_row_id')
            ->where('je.source_type', 'loan_installment')
            ->where('je.source_row_id', $loan->row_id)
            ->where('je.status', 'posted')
            ->where('jl.credit', '>', 0)
            ->where('jl.line_number', 3)
            ->sum('jl.credit');

        $remainingInterest = max(0.0, $totalLoanInterest - $paidInterest);

        // Prepare structured receipt data for ESC/POS printing
        $receiptNumber = $entry->journal_number ?? "KWT-{$entry->id}";
        $receiptData = [
            'receipt_number' => $receiptNumber,
            'transaction_date' => CarbonImmutable::parse($transactionDate)->format('d/m/Y H:i'),
            'organization_name' => $orgName,
            'collector_name' => $user->name,
            'loan_number' => $loan->loan_number ?? "PINJ-{$loan->id}",
            'borrower_name' => $loan->borrower?->group?->name ?? $payerName,
            'payer_name' => $payerName,
            'payer_phone' => $payerPhone,
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'penalty_amount' => $penalty,
            'total_paid' => $totalPaid,
            'remaining_principal' => $remainingPrincipal,
            'remaining_interest' => $remainingInterest,
            'whatsapp_message' => "Bukti Pembayaran Angsuran {$orgName}\n"
                ."No. Bukti: {$receiptNumber}\n"
                ."Tanggal: {$transactionDate}\n"
                ."Peminjam: {$payerName}\n"
                .'Pokok: Rp '.number_format($principal, 0, ',', '.')."\n"
                .'Jasa: Rp '.number_format($interest, 0, ',', '.')."\n"
                .($penalty > 0 ? 'Denda: Rp '.number_format($penalty, 0, ',', '.')."\n" : '')
                .'TOTAL BAYAR: Rp '.number_format($totalPaid, 0, ',', '.')."\n"
                .'Sisa Pokok: Rp '.number_format($remainingPrincipal, 0, ',', '.')."\n"
                ."Petugas: {$user->name}\n"
                .'Terima kasih atas pembayaran Anda.',
        ];

        return ApiResponse::success($receiptData, 'Pembayaran angsuran berhasil dicatat.');
    }
}
