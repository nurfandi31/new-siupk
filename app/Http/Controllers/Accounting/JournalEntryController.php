<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\InstallmentReceiptService;
use App\Domain\Accounting\Services\JournalEntryOptionResolver;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Assets\Services\AssetService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Lending\Services\LoanTrackingService;
use App\Domain\Notifications\Services\WhatsappNotificationService;
use App\Http\Requests\Accounting\JournalEntryRequest;
use App\Http\Requests\Accounting\LoanInstallmentJournalRequest;
use App\Support\ReportPdf;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class JournalEntryController
{
    public function __construct(
        private readonly JournalEntryOptionResolver $resolver,
    ) {}

    public function create(Request $request, TenantContext $context): Response
    {
        $history = $this->resolveHistory($request, $context);
        $types = $this->resolver->getTransactionTypes();
        $allowed = array_column($types, 'value');
        $preset = (string) $request->query('type', '');
        if ($preset !== '' && ! in_array($preset, $allowed, true)) {
            $preset = '';
        }

        return Inertia::render('Accounting/JournalEntries/Create', [
            'transactionTypes' => $types,
            'labels' => $this->resolver->getLabels(),
            'options' => $this->resolver->getOptionsForAllTypes(),
            'accountOptions' => $this->resolver->getAllAccountOptions(),
            'today' => now()->toDateString(),
            'history' => $history,
            'presetType' => $preset !== '' ? $preset : null,
        ]);
    }

    public function store(JournalEntryRequest $request, JournalPostingService $poster): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) $request->user()->row_id;
        $isInventory = JournalEntryOptionResolver::isAssetPurchase($data['transaction_type'] ?? null);

        if ($isInventory) {
            $qty = (int) ($data['asset_quantity'] ?? 0);
            $unit = (float) ($data['asset_unit_cost'] ?? 0);
            $data['amount'] = round($qty * $unit, 2);
            $name = trim((string) ($data['asset_name'] ?? ''));
            $data['description'] = trim((string) ($data['description'] ?? '')) !== ''
                ? (string) $data['description']
                : sprintf('Pembelian inventaris: %s (%d unit)', $name, $qty);
        }

        $entry = DB::connection('tenant')->transaction(function () use ($data, $userId, $isInventory): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $data['transaction_date'],
                'sequence_number' => 0,
                'source_type' => $isInventory ? 'asset_purchase' : 'manual',
                'transaction_type' => $data['transaction_type'],
                'source_row_id' => null,
                'description' => $data['description'],
                'legacy_relation' => $data['reference'] ?? null,
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            $entry->lines()->create([
                'line_number' => 1,
                'account_row_id' => (int) $data['disimpan_ke_row_id'],
                'organization_unit_row_id' => null,
                'description' => $data['description'],
                'debit' => (float) $data['amount'],
                'credit' => 0,
            ]);

            $entry->lines()->create([
                'line_number' => 2,
                'account_row_id' => (int) $data['sumber_dana_row_id'],
                'organization_unit_row_id' => null,
                'description' => $data['description'],
                'debit' => 0,
                'credit' => (float) $data['amount'],
            ]);

            if ($isInventory) {
                // Register inventaris ikut jurnal (sama alur legacy) — daftar di /assets.
                $asset = app(AssetService::class)->create([
                    'name' => (string) $data['asset_name'],
                    'purchased_at' => $data['transaction_date'],
                    'quantity' => (int) $data['asset_quantity'],
                    'unit_cost' => (float) $data['asset_unit_cost'],
                    'useful_life_months' => (int) $data['asset_useful_life_months'],
                    'status' => 'good',
                    'category_code' => JournalEntryOptionResolver::ATB_PURCHASE_TYPES[$data['transaction_type']] ?? null,
                ], $userId);

                $entry->update(['source_row_id' => (int) $asset->row_id]);
            }

            return $entry->fresh(['lines']);
        });

        $posted = $poster->post($entry, $userId);

        session()->flash('success', [
            'message' => 'Jurnal umum berhasil dicatat.',
            'entry' => [
                'id' => $posted->id,
                'public_id' => $posted->public_id,
                'journal_number' => $posted->journal_number,
                'transaction_date' => $posted->transaction_date?->toDateString(),
                'transaction_type' => $posted->transaction_type,
                'description' => $posted->description,
            ],
            'lines' => $posted->lines->map(fn ($l) => [
                'account_code' => $l->account?->code,
                'account_name' => $l->account?->name,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
            ])->all(),
        ]);

        return redirect()->route('accounting.journal-entries.create');
    }

    public function installment(Request $request, TenantContext $context, string $type = 'group'): Response
    {
        $tenantId = $context->id();

        // type: 'group' (default, kelompok) atau 'individual' (perorangan).
        // Filter berdasarkan `loans.legacy_source` — paritas dengan pacuan yang
        // punya route jurnal_angsuran terpisah untuk kelompok vs individu.
        if (! in_array($type, ['group', 'individual'], true)) {
            $type = 'group';
        }

        // Pakai subquery agregat untuk borrower info agar:
        // 1. Tidak menggandakan loan row (loan dengan multi-borrower tidak dobel).
        // 2. Kompatibel dengan MySQL ONLY_FULL_GROUP_BY (semua kolom SELECT non-aggregate
        //    keluar dari subquery atau dari tabel loans/loan_products yang di-join biasa).
        // 3. Untuk member_loan, ambil borrower row pertama (peminjam utama diasumsikan
        //    single-borrower — tidak ada konsep penjamin untuk pinjaman perorangan).
        $borrowerSub = DB::connection('tenant')
            ->table('loan_borrowers')
            ->selectRaw('loan_row_id, MIN(group_row_id) AS group_row_id, MIN(member_row_id) AS member_row_id')
            ->where('tenant_id', $tenantId)
            ->groupBy('loan_row_id');

        $groupNameSub = DB::connection('tenant')
            ->table('groups')
            ->selectRaw('row_id, name')
            ->where('tenant_id', $tenantId);

        $loans = DB::connection('tenant')
            ->table('loans as l')
            ->join('loan_products as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'l.loan_product_row_id')
                    ->where('p.tenant_id', '=', $tenantId);
            })
            ->leftJoinSub($borrowerSub, 'lb', 'lb.loan_row_id', '=', 'l.row_id')
            ->leftJoinSub($groupNameSub, 'g', 'g.row_id', '=', 'lb.group_row_id')
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', ['active', 'disbursed'])
            ->when($type === 'individual', function ($query): void {
                $query->where('l.legacy_source', 'member_loan');
            })
            ->when($type === 'group', function ($query): void {
                $query->where(function ($q): void {
                    $q->whereNull('l.legacy_source')->orWhere('l.legacy_source', '!=', 'member_loan');
                });
            })
            ->orderByDesc('l.disbursed_at')
            ->get([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.principal_amount',
                'l.disbursed_at',
                'p.code as product_code',
                'lb.group_row_id',
                'lb.member_row_id',
                'g.name as group_name',
            ]);

        $loanRowIds = $loans->pluck('row_id')->all();

        $totalsPaid = $loanRowIds === []
            ? []
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->where('component', 'principal')
                ->groupBy('loan_row_id')
                ->selectRaw('loan_row_id, CAST(COALESCE(SUM(principal_paid), 0) AS CHAR) AS total_paid')
                ->pluck('total_paid', 'loan_row_id')
                ->all();

        $loanOptions = $loans->map(function ($loan) use ($totalsPaid, $type, $tenantId): array {
            $paid = (float) ($totalsPaid[$loan->row_id] ?? 0);
            $sisa = round((float) $loan->principal_amount - $paid, 2);

            // Untuk individu, label menampilkan nomor SPK + identitas anggota
            // (LoanService.generate sudah set loan_number untuk member_loan).
            $isIndividual = $type === 'individual';
            $subjectLabel = $isIndividual
                ? $this->resolveIndividualSubject($tenantId, (int) $loan->row_id)
                : (string) ($loan->group_name ?? '-');

            return [
                'value' => (int) $loan->row_id,
                'label' => sprintf(
                    '%s · %s · Sisa Pokok %s',
                    $loan->loan_number ?: 'Loan #'.(int) $loan->id,
                    strtoupper((string) $loan->product_code),
                    'Rp '.number_format($sisa, 0, ',', '.'),
                ),
                'product_code' => (string) $loan->product_code,
                'group_name' => $loan->group_name ? (string) $loan->group_name : null,
                'borrower_member_row_id' => $loan->member_row_id ? (int) $loan->member_row_id : null,
                'subject_label' => $subjectLabel,
                'is_individual' => $isIndividual,
                'sisa_pokok' => $sisa,
                'loan_number' => $loan->loan_number,
            ];
        })->values()->all();

        $installmentRows = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->get([
                    'row_id', 'loan_row_id', 'installment_number', 'due_date',
                    'principal_due', 'interest_due', 'principal_paid', 'interest_paid',
                ]);

        $grouped = [];
        foreach ($installmentRows as $row) {
            $key = $row->loan_row_id.':'.$row->installment_number;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'loan_row_id' => (int) $row->loan_row_id,
                    'installment_number' => (int) $row->installment_number,
                    'due_date' => (string) $row->due_date,
                    'principal_due' => 0.0,
                    'interest_due' => 0.0,
                    'principal_paid' => 0.0,
                    'interest_paid' => 0.0,
                    'principal_row_ids' => [],
                    'interest_row_ids' => [],
                ];
            }
            $principalDue = (float) $row->principal_due;
            $interestDue = (float) $row->interest_due;
            if ($principalDue > 0 || (float) $row->principal_paid > 0) {
                $grouped[$key]['principal_due'] += $principalDue;
                $grouped[$key]['principal_paid'] += (float) $row->principal_paid;
                $grouped[$key]['principal_row_ids'][] = (int) $row->row_id;
            }
            if ($interestDue > 0 || (float) $row->interest_paid > 0) {
                $grouped[$key]['interest_due'] += $interestDue;
                $grouped[$key]['interest_paid'] += (float) $row->interest_paid;
                $grouped[$key]['interest_row_ids'][] = (int) $row->row_id;
            }
        }

        $installmentOptions = [];
        foreach ($grouped as $g) {
            $principalSisa = round($g['principal_due'] - $g['principal_paid'], 2);
            $interestSisa = round($g['interest_due'] - $g['interest_paid'], 2);
            $installmentOptions[] = [
                'loan_row_id' => $g['loan_row_id'],
                'value' => $g['installment_number'],
                'installment_number' => $g['installment_number'],
                'due_date' => $g['due_date'],
                'principal_due' => $g['principal_due'],
                'interest_due' => $g['interest_due'],
                'principal_paid' => $g['principal_paid'],
                'interest_paid' => $g['interest_paid'],
                'principal_sisa' => $principalSisa,
                'interest_sisa' => $interestSisa,
            ];
        }

        $cashAccounts = $this->resolver->getOptionsFor('angsuran')['sumber_dana'];

        $peminjamOptions = $this->buildPeminjamOptions($loanRowIds, $tenantId);

        return Inertia::render($type === 'individual' ? 'Accounting/JournalEntries/InstallmentIndividual' : 'Accounting/JournalEntries/Installment', [
            'loanOptions' => $loanOptions,
            'installmentOptions' => $installmentOptions,
            'cashAccounts' => $cashAccounts,
            'peminjamOptions' => $peminjamOptions,
            'today' => now()->toDateString(),
        ]);
    }

    /**
     * Pintasan ke jurnal angsuran khusus pinjaman perorangan (member_loan).
     * Setara `installment('individual')` — dipisah supaya menu navbar bisa
     * link langsung ke halaman khusus individu (paritas pacuan route
     * /transaksi/jurnal_angsuran_individu).
     */
    public function individualInstallment(Request $request, TenantContext $context): Response
    {
        return $this->installment($request, $context, 'individual');
    }

    /**
     * Ambil label subjek pinjaman perorangan (nama anggota + NIK) untuk
     * ditampilkan di daftar pilihan loan. Dipakai agar operator tahu
     * pinjaman perorangan milik siapa.
     */
    private function resolveIndividualSubject(int $tenantId, int $loanRowId): string
    {
        $row = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->join('members as m', function ($join) use ($tenantId): void {
                $join->on('m.row_id', '=', 'b.member_row_id')
                    ->where('m.tenant_id', '=', $tenantId);
            })
            ->join('people as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'm.person_row_id')
                    ->where('p.tenant_id', '=', $tenantId);
            })
            ->where('b.tenant_id', $tenantId)
            ->where('b.loan_row_id', $loanRowId)
            ->whereNotNull('b.member_row_id')
            ->first(['p.full_name', 'p.national_identity_number']);

        if ($row === null) {
            return '—';
        }

        $nik = trim((string) ($row->national_identity_number ?? ''));

        return $nik !== '' ? sprintf('%s (NIK %s)', (string) $row->full_name, $nik) : (string) $row->full_name;
    }

    /**
     * @param  array<int, int>  $loanRowIds
     * @return array<int, array{loan_row_id:int, value:int, label:string}>
     */
    private function buildPeminjamOptions(array $loanRowIds, int $tenantId): array
    {
        if ($loanRowIds === []) {
            return [];
        }

        $memberIds = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->where('b.tenant_id', $tenantId)
            ->whereIn('b.loan_row_id', $loanRowIds)
            ->whereNotNull('b.member_row_id')
            ->pluck('b.member_row_id')
            ->all();

        $beneficiaryMemberIds = DB::connection('tenant')
            ->table('loan_beneficiaries as lb')
            ->where('lb.tenant_id', $tenantId)
            ->whereIn('lb.loan_row_id', $loanRowIds)
            ->pluck('lb.member_row_id')
            ->all();

        $memberIdSet = array_unique(array_merge($memberIds, $beneficiaryMemberIds));

        if ($memberIdSet === []) {
            return [];
        }

        $members = DB::connection('tenant')
            ->table('members as m')
            ->join('people as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'm.person_row_id')
                    ->where('p.tenant_id', '=', $tenantId);
            })
            ->where('m.tenant_id', $tenantId)
            ->whereIn('m.row_id', $memberIdSet)
            ->where('m.status', 'active')
            ->whereNull('m.deleted_at')
            ->get(['m.row_id', 'p.full_name']);

        $memberById = $members->keyBy('row_id');

        $borrowerRows = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->where('b.tenant_id', $tenantId)
            ->whereIn('b.loan_row_id', $loanRowIds)
            ->whereNotNull('b.member_row_id')
            ->get(['b.loan_row_id', 'b.member_row_id']);

        $beneficiaryRows = DB::connection('tenant')
            ->table('loan_beneficiaries as lb')
            ->where('lb.tenant_id', $tenantId)
            ->whereIn('lb.loan_row_id', $loanRowIds)
            ->get(['lb.loan_row_id', 'lb.member_row_id']);

        $seen = [];
        $options = [];

        foreach (array_merge($borrowerRows->all(), $beneficiaryRows->all()) as $row) {
            $loanRowId = (int) $row->loan_row_id;
            $memberRowId = (int) $row->member_row_id;
            $member = $memberById->get($memberRowId);
            if ($member === null) {
                continue;
            }
            $key = $loanRowId.':'.$memberRowId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $options[] = [
                'loan_row_id' => $loanRowId,
                'value' => $memberRowId,
                'label' => sprintf('#%d · %s', $memberRowId, (string) $member->full_name),
            ];
        }

        return $options;
    }

    public function storeInstallment(
        LoanInstallmentJournalRequest $request,
        LoanService $loanService,
        WhatsappNotificationService $notices,
    ): RedirectResponse {
        return $this->processInstallmentSubmission(
            $request,
            $loanService,
            $notices,
            redirectRoute: 'accounting.journal-entries.installment',
        );
    }

    /**
     * Submit jurnal angsuran khusus pinjaman perorangan (member_loan).
     * Paritas pacuan /transaksi/jurnal_angsuran_individu_process — handler
     * terpisah supaya redirect kembali ke halaman individual.
     */
    public function storeIndividualInstallment(
        LoanInstallmentJournalRequest $request,
        LoanService $loanService,
        WhatsappNotificationService $notices,
    ): RedirectResponse {
        return $this->processInstallmentSubmission(
            $request,
            $loanService,
            $notices,
            redirectRoute: 'accounting.journal-entries.installment-individual',
        );
    }

    /**
     * Shared submission handler untuk jurnal angsuran kelompok & individu.
     */
    private function processInstallmentSubmission(
        LoanInstallmentJournalRequest $request,
        LoanService $loanService,
        WhatsappNotificationService $notices,
        string $redirectRoute,
    ): RedirectResponse {
        $data = $request->validated();

        // Cross-route guard sudah ditangani oleh FormRequest (lihat
        // LoanInstallmentJournalRequest::expectedLoanScope). Di sini cukup pakai
        // hasil validasi — loan_id pasti sudah sesuai dengan scope route.

        $loanRowId = (int) $data['loan_id'];
        $isMemberLoan = $redirectRoute === 'accounting.journal-entries.installment-individual';

        $userId = (int) $request->user()->row_id;

        // Untuk pinjaman perorangan, bila frontend tidak mengirim member_allocations
        // (karena 1 peminjam), auto-construct dari borrower_member_row_id + reference
        // dan update loan_installments.principal_paid/interest_paid agar Sisa Pokok
        // di dropdown akurat untuk pembayaran berikutnya. LoanService tidak menyentuh
        // loan_installments — pre-existing behavior untuk kelompok yang manual.
        if ($isMemberLoan && empty($data['member_allocations'])) {
            $borrowerMemberRowId = DB::connection('tenant')
                ->table('loan_borrowers')
                ->where('loan_row_id', $loanRowId)
                ->whereNotNull('member_row_id')
                ->orderBy('row_id')
                ->value('member_row_id');
            if ($borrowerMemberRowId !== null) {
                $data['member_allocations'] = [[
                    'member_row_id' => (int) $borrowerMemberRowId,
                    'principal_paid' => (float) ($data['principal_amount'] ?? 0),
                    'interest_paid' => (float) ($data['interest_amount'] ?? 0),
                    'penalty_paid' => (float) ($data['penalty_amount'] ?? 0),
                ]];
                // Frontend mengirim `reference` (= borrower_member_row_id) untuk field
                // penyetor. Pastikan konsisten dengan auto-constructed allocation.
                $data['reference'] = (int) $borrowerMemberRowId;
            }
        }

        $posted = $loanService->recordInstallmentPayment($data, $userId);

        // Untuk individu: setelah recordInstallmentPayment (yang insert loan_installment_tracking),
        // update loan_installments agar Sisa Pokok di dropdown akurat.
        if ($isMemberLoan && ! empty($data['member_allocations']) && isset($data['installment_number'])) {
            $principalAmount = (float) ($data['principal_amount'] ?? 0);
            $interestAmount = (float) ($data['interest_amount'] ?? 0);
            $penaltyAmount = (float) ($data['penalty_amount'] ?? 0);
            DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', app(TenantContext::class)->id())
                ->where('loan_row_id', $loanRowId)
                ->where('installment_number', (int) $data['installment_number'])
                ->where('component', 'principal')
                ->update([
                    'principal_paid' => DB::raw('principal_paid + '.$principalAmount),
                    'updated_at' => now(),
                ]);
            DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', app(TenantContext::class)->id())
                ->where('loan_row_id', $loanRowId)
                ->where('installment_number', (int) $data['installment_number'])
                ->where('component', 'interest')
                ->update([
                    'interest_paid' => DB::raw('interest_paid + '.$interestAmount),
                    'penalty_paid' => DB::raw('penalty_paid + '.$penaltyAmount),
                    'updated_at' => now(),
                ]);
        }

        $waMessage = null;
        try {
            $loan = Loan::query()
                ->with(['borrower.group', 'product'])
                ->where('row_id', (int) $data['loan_id'])
                ->first();
            if ($loan !== null) {
                $notify = $notices->notifyInstallmentPayment(
                    loan: $loan,
                    payerMemberRowId: (int) $data['reference'],
                    principal: (float) $data['principal_amount'],
                    interest: (float) $data['interest_amount'],
                    penalty: (float) ($data['penalty_amount'] ?? 0),
                    transactionDate: (string) $data['transaction_date'],
                    installmentNumber: isset($data['installment_number']) ? (int) $data['installment_number'] : null,
                );
                if (is_array($notify)) {
                    $waMessage = $notify['sent']
                        ? ' WhatsApp terkirim ke '.$notify['phone'].'.'
                        : ' WhatsApp: '.$notify['message'];
                }
            }
        } catch (\Throwable) {
            $waMessage = ' WhatsApp gagal dikirim.';
        }

        session()->flash('success', [
            'message' => 'Jurnal angsuran berhasil dicatat.'.($waMessage ?? ''),
            'entry' => [
                'row_id' => $posted->row_id,
                'id' => $posted->id,
                'journal_number' => $posted->journal_number,
                'transaction_date' => $posted->transaction_date?->toDateString(),
                'transaction_type' => $posted->transaction_type,
                'description' => $posted->description,
            ],
            'lines' => $posted->lines->map(fn ($l) => [
                'account_code' => $l->account?->code,
                'account_name' => $l->account?->name,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
            ])->all(),
            'receipt_url' => route('accounting.journal-entries.installment.receipt', ['entry' => $posted->row_id]),
        ]);

        return redirect()->route($redirectRoute);
    }

    public function installmentReceipt(
        JournalEntry $entry,
        InstallmentReceiptService $receipts,
        ReportPdf $pdf,
    ): HttpResponse|StreamedResponse {
        try {
            $data = $receipts->build($entry);
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        $filename = 'bukti-angsuran-'.$entry->id.'.pdf';

        return $pdf->stream('reports.pdf.installment_receipt', $data, $filename);
    }

    /**
     * @return array{
     *     account: array{row_id:int,code:string,name:string,normal_balance:string,account_type:string}|null,
     *     period: string,
     *     date: string|null,
     *     month: string|null,
     *     year: string|null,
     *     range: array{start:string,end:string},
     *     rows: array<int, array<string, mixed>>
     * }|null
     */
    private function resolveHistory(Request $request, TenantContext $context): ?array
    {
        $accountRowId = $request->query('account_row_id');
        if (! is_string($accountRowId) || $accountRowId === '') {
            return null;
        }

        $validated = $request->validate([
            'account_row_id' => ['required', 'integer'],
            'period' => ['nullable', Rule::in(['daily', 'monthly', 'yearly'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'date_format:Y'],
        ]);

        $period = $validated['period'] ?? 'monthly';
        $tenantId = $context->id();

        $account = Account::on('tenant')
            ->where('row_id', (int) $validated['account_row_id'])
            ->where('is_active', true)
            ->first(['row_id', 'code', 'name', 'normal_balance', 'account_type']);

        if ($account === null) {
            return null;
        }

        $today = CarbonImmutable::today();

        $range = match ($period) {
            'daily' => $this->dailyRange($validated['date'] ?? $today->toDateString()),
            'monthly' => $this->monthlyRange($validated['month'] ?? $today->format('Y-m')),
            'yearly' => $this->yearlyRange($validated['year'] ?? (string) $today->year),
        };

        $rows = DB::connection('tenant')
            ->table('journal_lines as l')
            ->join('journal_entries as e', function ($join): void {
                $join->on('e.tenant_id', '=', 'l.tenant_id')
                    ->on('e.row_id', '=', 'l.journal_entry_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('l.account_row_id', $account->row_id)
            ->where('e.status', 'posted')
            ->whereBetween('e.transaction_date', [$range['start'], $range['end']])
            ->orderBy('e.transaction_date')
            ->orderBy('e.sequence_number')
            ->orderBy('l.line_number')
            ->get([
                'e.id as entry_id',
                'e.transaction_date',
                'e.sequence_number',
                'e.journal_number',
                'e.public_id as entry_public_id',
                'e.description as entry_description',
                'e.transaction_type',
                'l.debit',
                'l.credit',
                'l.description as line_description',
            ]);

        $normal = (string) $account->normal_balance;
        $running = 0.0;
        $rendered = [];
        $rowCounter = 0;
        foreach ($rows as $row) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $running += $normal === 'D' ? ($debit - $credit) : ($credit - $debit);
            $rowCounter++;
            $rendered[] = [
                'entry_id' => (int) $row->entry_id,
                'transaction_date' => (string) $row->transaction_date,
                'row_sequence' => $rowCounter,
                'sequence_number' => (int) $row->sequence_number,
                'journal_number' => $row->journal_number,
                'entry_public_id' => $row->entry_public_id,
                'transaction_type' => $row->transaction_type,
                'description' => $row->line_description ?: $row->entry_description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $running,
            ];
        }

        return [
            'account' => [
                'row_id' => (int) $account->row_id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'normal_balance' => $normal,
                'account_type' => (string) $account->account_type,
            ],
            'period' => $period,
            'date' => $validated['date'] ?? null,
            'month' => $validated['month'] ?? null,
            'year' => $validated['year'] ?? null,
            'range' => $range,
            'rows' => $rendered,
        ];
    }

    /**
     * @return array{start:string,end:string}
     */
    private function dailyRange(string $date): array
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date) ?? CarbonImmutable::today();

        return [
            'start' => $day->toDateString(),
            'end' => $day->toDateString(),
        ];
    }

    /**
     * @return array{start:string,end:string}
     */
    private function monthlyRange(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m', $month) ?? CarbonImmutable::now()->startOfMonth();

        return [
            'start' => $start->startOfMonth()->toDateString(),
            'end' => $start->endOfMonth()->toDateString(),
        ];
    }

    /**
     * @return array{start:string,end:string}
     */
    private function yearlyRange(string $year): array
    {
        $start = CarbonImmutable::createFromFormat('Y', $year) ?? CarbonImmutable::now()->startOfYear();

        return [
            'start' => $start->startOfYear()->toDateString(),
            'end' => $start->endOfYear()->toDateString(),
        ];
    }

    public function loanGroupDetail(Request $request, int $loanId, TenantContext $context, LoanTrackingService $tracking): JsonResponse
    {
        $tenantId = $context->id();
        $loan = Loan::query()->where('row_id', $loanId)->firstOrFail();

        $borrower = DB::connection('tenant')
            ->table('loan_borrowers as lb')
            ->leftJoin('groups as g', function ($join) use ($tenantId): void {
                $join->on('g.row_id', '=', 'lb.group_row_id')
                    ->where('g.tenant_id', '=', $tenantId);
            })
            ->where('lb.tenant_id', $tenantId)
            ->where('lb.loan_row_id', $loanId)
            ->select('g.row_id as group_row_id', 'g.code as group_code', 'g.name as group_name', 'g.address as group_address', 'g.phone as group_phone', 'g.established_at')
            ->first();

        $members = $tracking->getGroupMembers($loanId);

        return response()->json([
            'loan' => [
                'row_id' => (int) $loan->row_id,
                'id' => (int) $loan->id,
                'loan_number' => (string) $loan->loan_number,
                'principal_amount' => (float) $loan->principal_amount,
            ],
            'group' => $borrower === null ? null : [
                'row_id' => (int) $borrower->group_row_id,
                'code' => $borrower->group_code,
                'name' => $borrower->group_name,
                'address' => $borrower->group_address,
                'phone' => $borrower->group_phone,
                'established_at' => $borrower->established_at,
                'member_count' => count($members),
            ],
            'members' => $members,
        ]);
    }

    public function loanInstallmentHistory(Request $request, int $loanId, TenantContext $context, LoanTrackingService $tracking): JsonResponse
    {
        $entries = DB::connection('tenant')
            ->table('journal_entries as e')
            ->where('e.tenant_id', $context->id())
            ->where('e.source_type', 'loan_installment')
            ->where('e.source_row_id', $loanId)
            ->where('e.status', 'posted')
            ->orderByDesc('e.transaction_date')
            ->orderByDesc('e.row_id')
            ->get(['e.row_id', 'e.id', 'e.journal_number', 'e.transaction_date', 'e.description', 'e.transaction_type']);

        $trackingByJournal = [];
        $trackingRows = DB::connection('tenant')
            ->table('loan_installment_tracking as t')
            ->join('members as m', function ($join) use ($context): void {
                $join->on('m.row_id', '=', 't.member_row_id')
                    ->where('m.tenant_id', '=', $context->id());
            })
            ->join('people as p', function ($join) use ($context): void {
                $join->on('p.row_id', '=', 'm.person_row_id')
                    ->where('p.tenant_id', '=', $context->id());
            })
            ->where('t.tenant_id', $context->id())
            ->where('t.loan_row_id', $loanId)
            ->orderBy('t.installment_number')
            ->orderBy('p.full_name')
            ->get(['t.journal_entry_row_id', 't.installment_number', 't.member_row_id', 'p.full_name', 't.principal_paid', 't.interest_paid', 't.penalty_paid']);

        foreach ($trackingRows as $r) {
            $trackingByJournal[(int) $r->journal_entry_row_id][] = [
                'installment_number' => (int) $r->installment_number,
                'member_row_id' => (int) $r->member_row_id,
                'full_name' => (string) $r->full_name,
                'principal_paid' => (float) $r->principal_paid,
                'interest_paid' => (float) $r->interest_paid,
                'penalty_paid' => (float) $r->penalty_paid,
            ];
        }

        $rows = $entries->map(function ($e) use ($trackingByJournal): array {
            $journalId = (int) $e->row_id;
            $tracking = $trackingByJournal[$journalId] ?? [];

            return [
                'id' => (int) $e->id,
                'journal_number' => $e->journal_number,
                'transaction_date' => (string) $e->transaction_date,
                'description' => $e->description,
                'transaction_type' => $e->transaction_type,
                'tracking' => $tracking,
                'tracking_count' => count($tracking),
            ];
        })->values()->all();

        return response()->json(['rows' => $rows]);
    }

    public function groupMemberOptions(Request $request, int $loanId, LoanTrackingService $tracking): JsonResponse
    {
        $members = $tracking->getGroupMembers($loanId);

        return response()->json([
            'members' => array_map(fn ($m) => [
                'value' => $m['row_id'],
                'label' => sprintf('#%d · %s', $m['row_id'], $m['full_name']),
                'status' => $m['status'],
                'allocated_amount' => $m['allocated_amount'] ?? 0.0,
            ], $members),
        ]);
    }
}
