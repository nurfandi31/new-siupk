<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\OrganizationProfile;
use DomainException;
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
    /**
     * @return array{
     *   identity: array{legal_name: string, short_name: ?string, address: ?string},
     *   entry: array<string, mixed>,
     *   loan: array<string, mixed>|null,
     *   payer: array{name: string}|null,
     *   amounts: array{principal: float, interest: float, penalty: float, total: float},
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

        $loanPayload = $this->resolveLoan($entry);
        $payer = $this->resolvePayer($entry, $loanPayload);

        $profile = OrganizationProfile::query()->first([
            'legal_name', 'short_name', 'address',
        ]);

        return [
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
                'address' => $profile?->address,
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

    /**
     * @return array<string, mixed>|null
     */
    private function resolveLoan(JournalEntry $entry): ?array
    {
        $loan = null;

        // Next path: source_row_id = loans.row_id
        $sourceRowId = (int) ($entry->source_row_id ?? 0);
        if ((string) $entry->source_type === 'loan_installment' && $sourceRowId > 0) {
            $loan = Loan::query()
                ->with(['product:row_id,code,name', 'borrower.group:row_id,name,address'])
                ->where('row_id', $sourceRowId)
                ->first();
        }

        // Legacy path: legacy_loan_id = loans.id (group_loan)
        $legacyLoanId = (int) ($entry->legacy_loan_id ?? 0);
        if ($loan === null && $legacyLoanId > 0) {
            $loan = Loan::query()
                ->with(['product:row_id,code,name', 'borrower.group:row_id,name,address'])
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
                    ->with(['product:row_id,code,name', 'borrower.group:row_id,name,address'])
                    ->where('id', (int) $m[1])
                    ->first();
            }
        }

        if ($loan === null) {
            return null;
        }

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
