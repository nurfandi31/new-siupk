<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Models\LoanPayment;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Utils\Keuangan;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bukti bayar angsuran.
 *
 * Menerima:
 * - source_type=loan_installment (input Next)
 * - legacy_transaksi dengan deskripsi Angs. + legacy_loan_id (data migrasi)
 */
final class InstallmentReceiptService
{
    public function __construct(
        private readonly Keuangan $keuangan,
    ) {}

    /**
     * @return array{
     *   identity: array{legal_name: string, short_name: ?string, address: ?string},
     *   entry: array<string, mixed>,
     *   loan: array<string, mixed>|null,
     *   payer: array{name: string}|null,
     *   amounts: array{principal: float, interest: float, penalty: float, total: float},
     *   schedule: array<string, mixed>,
     *   lines: list<array{account_code: ?string, account_name: ?string, debit: float, credit: float, description: ?string}>
     * }
     */
    public function build(JournalEntry $entry): array
    {
        $entry->loadMissing(['lines.account']);

        if ($entry->status !== 'posted') {
            throw new DomainException('Hanya jurnal posted yang dapat dicetak sebagai bukti.');
        }

        if (! $this->isInstallmentJournal($entry)) {
            throw new DomainException('Bukti ini hanya untuk jurnal angsuran.');
        }

        $kind = $this->installmentKind($entry); // principal|interest|penalty|mixed
        $principal = 0.0;
        $interest = 0.0;
        $penalty = 0.0;
        $totalDebit = 0.0;
        $lines = [];

        foreach ($entry->lines as $line) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $totalDebit = round($totalDebit + $debit, 2);
            $desc = strtolower((string) ($line->description ?? ''));
            $code = (string) ($line->account?->code ?? '');
            $type = (string) ($line->account?->account_type ?? '');

            if ($credit > 0) {
                if ($kind === 'principal') {
                    $principal = round($principal + $credit, 2);
                } elseif ($kind === 'interest') {
                    $interest = round($interest + $credit, 2);
                } elseif ($kind === 'penalty') {
                    $penalty = round($penalty + $credit, 2);
                } elseif (str_contains($desc, 'denda') || str_contains($code, '4.1.01.04') || str_contains($code, 'denda')) {
                    $penalty = round($penalty + $credit, 2);
                } elseif (
                    str_contains($desc, 'jasa')
                    || str_contains($desc, 'pendapatan')
                    || $type === 'revenue'
                    || str_starts_with($code, '4.')
                ) {
                    $interest = round($interest + $credit, 2);
                } else {
                    $principal = round($principal + $credit, 2);
                }
            }

            $lines[] = [
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line->description,
            ];
        }

        $loanModel = $this->resolveLoanModel($entry);
        $loanPayload = $loanModel !== null ? $this->loanPayload($loanModel) : null;
        $payer = $this->resolvePayer($entry, $loanPayload);
        $schedule = $loanModel !== null
            ? $this->buildSchedule($loanModel, $entry)
            : $this->emptySchedule($totalDebit > 0 ? $totalDebit : $principal + $interest + $penalty);

        $profile = OrganizationProfile::query()->first();

        return [
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
                'address' => $profile?->address,
                'registration_number' => $profile?->registration_number,
                'phone' => $profile?->phone,
                'district_name' => $profile?->district_name,
                'regency_name' => $profile?->regency_name,
                'logo_url' => $profile?->logo_url,
            ],
            'entry' => [
                'row_id' => (int) $entry->row_id,
                'id' => (int) $entry->id,
                'journal_number' => $entry->journal_number,
                'transaction_date' => $entry->transaction_date?->toDateString(),
                'description' => $entry->description,
                'posted_at' => $entry->posted_at?->toDateTimeString(),
                'source_type' => (string) ($entry->source_type ?? ''),
            ],
            'loan' => $loanPayload,
            'payer' => $payer,
            'amounts' => [
                'principal' => $principal,
                'interest' => $interest,
                'penalty' => $penalty,
                'total' => $totalDebit > 0 ? $totalDebit : round($principal + $interest + $penalty, 2),
            ],
            'schedule' => $schedule,
            'lines' => $lines,
        ];
    }

    public function isInstallmentJournal(JournalEntry $entry): bool
    {
        $source = (string) ($entry->source_type ?? '');
        if ($source === 'loan_installment') {
            return true;
        }

        // Migrated legacy angsuran journals
        $desc = (string) ($entry->description ?? '');
        if ($source === 'legacy_transaksi' && preg_match('/\bAngs\.?\b/iu', $desc) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @return 'principal'|'interest'|'penalty'|'mixed'
     */
    private function installmentKind(JournalEntry $entry): string
    {
        $desc = (string) ($entry->description ?? '');
        // Legacy: "Angs. (P) …" / "Angs. (J) …"
        if (preg_match('/Angs\.?\s*\(\s*P\s*\)/iu', $desc) === 1) {
            return 'principal';
        }
        if (preg_match('/Angs\.?\s*\(\s*J\s*\)/iu', $desc) === 1) {
            return 'interest';
        }
        if (preg_match('/Angs\.?\s*\(\s*D\s*\)/iu', $desc) === 1 || preg_match('/\bdenda\b/iu', $desc) === 1) {
            return 'penalty';
        }

        return 'mixed';
    }

    private function resolveLoanModel(JournalEntry $entry): ?Loan
    {
        $loan = null;
        $relations = [
            'product:row_id,code,name',
            'borrower.group:row_id,name,address',
            'installments',
            'payments.allocations',
        ];

        // Next path: source_row_id = loans.row_id
        $sourceRowId = (int) ($entry->source_row_id ?? 0);
        if ((string) $entry->source_type === 'loan_installment' && $sourceRowId > 0) {
            $loan = Loan::query()
                ->with($relations)
                ->where('row_id', $sourceRowId)
                ->first();
        }

        // Legacy path: legacy_loan_id = loans.id (group_loan)
        $legacyLoanId = (int) ($entry->legacy_loan_id ?? 0);
        if ($loan === null && $legacyLoanId > 0) {
            $loan = Loan::query()
                ->with($relations)
                ->where('id', $legacyLoanId)
                ->where(function ($q): void {
                    $q->where('legacy_source', 'group_loan')
                        ->orWhereNull('legacy_source')
                        ->orWhere('legacy_source', '');
                })
                ->first();
        }

        // Parse "(8163)" from description as last resort
        if ($loan === null) {
            $desc = (string) ($entry->description ?? '');
            if (preg_match('/\((\d{3,})\)/', $desc, $m) === 1) {
                $loan = Loan::query()
                    ->with($relations)
                    ->where('id', (int) $m[1])
                    ->first();
            }
        }

        return $loan;
    }

    /**
     * @return array<string, mixed>
     */
    private function loanPayload(Loan $loan): array
    {
        return [
            'row_id' => (int) $loan->row_id,
            'id' => (int) $loan->id,
            'loan_number' => $loan->loan_number,
            'product_code' => $loan->product?->code,
            'product_name' => $loan->product?->name,
            'group_name' => $loan->borrower?->group?->name,
            'group_address' => $loan->borrower?->group?->address,
        ];
    }

    /**
     * Ringkasan jadwal & posisi angsuran untuk format struk legacy.
     *
     * @return array<string, mixed>
     */
    private function buildSchedule(Loan $loan, JournalEntry $entry): array
    {
        $transactionDate = $entry->transaction_date?->toDateString() ?? now()->toDateString();

        /** @var Collection<int, LoanInstallment> $installments */
        $installments = $loan->installments
            ->filter(fn (LoanInstallment $row): bool => (int) $row->installment_number > 0)
            ->sortBy('installment_number')
            ->values();

        $totalPrincipal = round((float) $loan->principal_amount, 2);
        $totalInterest = round((float) $installments->sum(fn (LoanInstallment $row): float => (float) $row->interest_due), 2);

        /** @var Collection<int, LoanPayment> $payments */
        $payments = $loan->payments
            ->filter(fn (LoanPayment $payment): bool => ($payment->paid_at?->toDateString() ?? '') !== ''
                && $payment->paid_at->toDateString() <= $transactionDate)
            ->values();

        $allocations = $payments->flatMap(fn (LoanPayment $payment) => $payment->allocations);
        $paidPrincipal = round((float) $allocations
            ->where('component', 'principal')
            ->sum(fn ($allocation): float => (float) $allocation->amount), 2);
        $paidInterest = round((float) $allocations
            ->where('component', 'interest')
            ->sum(fn ($allocation): float => (float) $allocation->amount), 2);
        $paidPenalty = round((float) $allocations
            ->where('component', 'penalty')
            ->sum(fn ($allocation): float => (float) $allocation->amount), 2);

        // Data lama sebelum sinkronisasi ledger: fallback ke kolom paid jadwal.
        if ($allocations->isEmpty()) {
            $paidPrincipal = round((float) $installments->sum(fn (LoanInstallment $row): float => (float) $row->principal_paid), 2);
            $paidInterest = round((float) $installments->sum(fn (LoanInstallment $row): float => (float) $row->interest_paid), 2);
            $paidPenalty = round((float) $installments->sum(fn (LoanInstallment $row): float => (float) $row->penalty_paid), 2);
        }

        $dueRows = $installments
            ->filter(fn (LoanInstallment $row): bool => ($row->due_date?->toDateString() ?? '') !== ''
                && $row->due_date->toDateString() <= $transactionDate);
        $targetPrincipal = round((float) $dueRows->sum(fn (LoanInstallment $row): float => (float) $row->principal_due), 2);
        $targetInterest = round((float) $dueRows->sum(fn (LoanInstallment $row): float => (float) $row->interest_due), 2);

        $installmentNumbers = $installments
            ->pluck('installment_number')
            ->map(fn ($number): int => (int) $number)
            ->unique()
            ->sort()
            ->values();
        $installmentTotal = $installmentNumbers->count();

        $paidNumber = 0;
        foreach ($allocations as $allocation) {
            $installmentRowId = (int) ($allocation->installment_row_id ?? 0);
            if ($installmentRowId <= 0) {
                continue;
            }
            $number = (int) ($installments->firstWhere('row_id', $installmentRowId)?->installment_number ?? 0);
            $paidNumber = max($paidNumber, $number);
        }
        if ($paidNumber === 0) {
            foreach ($installments as $row) {
                if ((float) $row->principal_paid > 0 || (float) $row->interest_paid > 0) {
                    $paidNumber = max($paidNumber, (int) $row->installment_number);
                }
            }
        }
        if ($paidNumber === 0 && $installmentTotal > 0) {
            $paidNumber = 1;
        }

        $nextRows = $installmentNumbers
            ->first(fn (int $number): bool => $number > $paidNumber);
        $nextRows = $nextRows !== null
            ? $installments->where('installment_number', $nextRows)->values()
            : collect();
        $nextPrincipal = round((float) $nextRows->sum(fn (LoanInstallment $row): float => (float) $row->principal_due), 2);
        $nextInterest = round((float) $nextRows->sum(fn (LoanInstallment $row): float => (float) $row->interest_due), 2);

        $tunggakanPrincipal = round(max(0.0, $targetPrincipal - $paidPrincipal), 2);
        $tunggakanInterest = round(max(0.0, $targetInterest - $paidInterest), 2);
        $saldoPrincipal = round(max(0.0, $totalPrincipal - $paidPrincipal), 2);
        $saldoInterest = round(max(0.0, $totalInterest - $paidInterest), 2);
        $totalTagihan = round($tunggakanPrincipal + $tunggakanInterest + $nextPrincipal + $nextInterest, 2);

        return [
            'installment_number' => $paidNumber > 0 ? $paidNumber : 1,
            'installment_total' => $installmentTotal,
            'loan_amount' => $totalPrincipal,
            'loan_interest' => $totalInterest,
            'paid_principal' => $paidPrincipal,
            'paid_interest' => $paidInterest,
            'paid_penalty' => $paidPenalty,
            'target_principal' => $targetPrincipal,
            'target_interest' => $targetInterest,
            'tunggakan_principal' => $tunggakanPrincipal,
            'tunggakan_interest' => $tunggakanInterest,
            'next_principal' => $nextPrincipal,
            'next_interest' => $nextInterest,
            'saldo_principal' => $saldoPrincipal,
            'saldo_interest' => $saldoInterest,
            'total_tagihan' => $totalTagihan,
            'terbilang' => $this->keuangan->terbilang($entry->lines->sum(fn ($line): float => (float) $line->debit)).' Rupiah',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySchedule(float $amount): array
    {
        return [
            'installment_number' => 1,
            'installment_total' => 0,
            'loan_amount' => 0.0,
            'loan_interest' => 0.0,
            'paid_principal' => 0.0,
            'paid_interest' => 0.0,
            'paid_penalty' => 0.0,
            'target_principal' => 0.0,
            'target_interest' => 0.0,
            'tunggakan_principal' => 0.0,
            'tunggakan_interest' => 0.0,
            'next_principal' => 0.0,
            'next_interest' => 0.0,
            'saldo_principal' => 0.0,
            'saldo_interest' => 0.0,
            'total_tagihan' => 0.0,
            'terbilang' => $this->keuangan->terbilang($amount).' Rupiah',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $loanPayload
     * @return array{name: string}|null
     */
    private function resolvePayer(JournalEntry $entry, ?array $loanPayload): ?array
    {
        $tenantId = (int) $entry->tenant_id;
        $memberRowId = DB::connection('tenant')
            ->table('loan_installment_tracking')
            ->where('tenant_id', $tenantId)
            ->where('journal_entry_row_id', $entry->row_id)
            ->value('member_row_id');

        if ($memberRowId) {
            $name = DB::connection('tenant')
                ->table('members as m')
                ->join('people as p', function ($j) use ($tenantId): void {
                    $j->on('p.row_id', '=', 'm.person_row_id')
                        ->where('p.tenant_id', '=', $tenantId);
                })
                ->where('m.tenant_id', $tenantId)
                ->where('m.row_id', (int) $memberRowId)
                ->value('p.full_name');
            if (is_string($name) && $name !== '') {
                return ['name' => $name];
            }
        }

        $desc = (string) ($entry->description ?? '');
        if (preg_match('/a\/n\s+(.+?)(?:\.|$)/iu', $desc, $m) === 1) {
            return ['name' => trim($m[1])];
        }

        // Legacy "Angs. (P) NAME (id) [desa]" — name between marker and (id)
        if (preg_match('/Angs\.?\s*\([^)]+\)\s+(.+?)\s*\(\d+\)/iu', $desc, $m) === 1) {
            $name = trim($m[1]);
            if ($name !== '') {
                return ['name' => $name];
            }
        }

        if ($loanPayload !== null && ! empty($loanPayload['group_name'])) {
            return ['name' => (string) $loanPayload['group_name']];
        }

        return null;
    }
}
