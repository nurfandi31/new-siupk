<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Invoice / Kuitansi cetak.
 *
 * Mengambil data berdasarkan id (parameter):
 *   - source_type=loan_installment → tagihan angsuran
 *   - source_type=journal_entry   → tagihan dari jurnal entry
 *   - default (jika id tidak ditemukan) → invoice on-the-fly dengan payload minimal
 *
 * Catatan: tabel `invoices` belum ada di SIUPKNext, sehingga data invoice
 * disusun dari loan installment / journal entry. Pola ini memungkinkan unit
 * bisnis tetap bisa mencetak invoice tanpa menunggu migrasi tabel invoices.
 */
final readonly class InvoiceService
{
    public const SOURCE_INSTALLMENT = 'loan_installment';

    public const SOURCE_JOURNAL = 'journal_entry';

    public const SOURCE_ADHOC = 'adhoc';

    /**
     * Build invoice payload.
     *
     * @return array{
     *   invoice: array<string,mixed>,
     *   identity: array<string,mixed>,
     *   payer: array<string,mixed>|null,
     *   items: list<array{qty:float,description:string,unit_price:float,amount:float}>,
     *   totals: array{subtotal:float, ppn:float, grand_total:float, paid:float, due:float},
     *   payment: array<string,mixed>,
     *   notes: string|null
     * }
     */
    public function build(int $id, string $source): array
    {
        $source = $this->resolveSource($source);

        return match ($source) {
            self::SOURCE_INSTALLMENT => $this->fromInstallment($id),
            self::SOURCE_JOURNAL => $this->fromJournalEntry($id),
            default => $this->adhoc($id),
        };
    }

    public function resolveSource(string $source): string
    {
        return match ($source) {
            'installment', self::SOURCE_INSTALLMENT => self::SOURCE_INSTALLMENT,
            'journal', self::SOURCE_JOURNAL => self::SOURCE_JOURNAL,
            self::SOURCE_ADHOC, '' => self::SOURCE_ADHOC,
            default => throw new DomainException("Tipe invoice '{$source}' tidak dikenal."),
        };
    }

    /* ------------------------------------------------------------------ */
    /*                              Sources */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string,mixed>
     */
    private function fromInstallment(int $id): array
    {
        /** @var LoanInstallment|null $installment */
        $installment = LoanInstallment::query()
            ->with(['loan.borrower.group', 'loan.product'])
            ->find($id);
        if ($installment === null) {
            throw new DomainException("Angsuran #{$id} tidak ditemukan.");
        }

        $loan = $installment->loan;
        $group = $loan?->borrower?->group;
        $memberName = $this->resolveInstallmentMemberName($installment);
        $memberPhone = $this->resolveInstallmentMemberPhone($installment);

        $principal = max((float) $installment->principal_due - (float) $installment->principal_paid, 0.0);
        $interest = max((float) $installment->interest_due - (float) $installment->interest_paid, 0.0);
        $penalty = max((float) $installment->penalty_due - (float) $installment->penalty_paid, 0.0);

        $items = [];
        if ($principal > 0) {
            $items[] = [
                'qty' => 1.0,
                'description' => sprintf(
                    'Angsuran Pokok Pinjaman ke-%d (%s)',
                    (int) $installment->installment_number,
                    $loan?->loan_code ?? ('#'.$loan?->row_id),
                ),
                'unit_price' => $principal,
                'amount' => $principal,
            ];
        }
        if ($interest > 0) {
            $items[] = [
                'qty' => 1.0,
                'description' => sprintf('Jasa Pinjaman ke-%d', (int) $installment->installment_number),
                'unit_price' => $interest,
                'amount' => $interest,
            ];
        }
        if ($penalty > 0) {
            $items[] = [
                'qty' => 1.0,
                'description' => 'Denda Keterlambatan',
                'unit_price' => $penalty,
                'amount' => $penalty,
            ];
        }

        $subtotal = round($principal + $interest + $penalty, 2);
        $ppn = 0.0;
        $grand = $subtotal + $ppn;
        $paid = round(
            (float) $installment->principal_paid + (float) $installment->interest_paid + (float) $installment->penalty_paid,
            2,
        );
        $due = round($grand - $paid, 2);

        $payerName = $memberName ?? $group?->name ?? 'Peminjam';
        $payerAddress = $group?->address ?? null;
        $payerIdentity = $group?->code ?? null;

        $invoiceDate = CarbonImmutable::parse((string) $installment->due_date)->subDays(7);
        $dueDate = CarbonImmutable::parse((string) $installment->due_date);

        return [
            'invoice' => [
                'number' => sprintf(
                    'INV/%s/%s/%05d',
                    $invoiceDate->format('Y'),
                    str_pad((string) $installment->installment_number, 3, '0', STR_PAD_LEFT),
                    $installment->row_id,
                ),
                'date' => $invoiceDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'source_type' => self::SOURCE_INSTALLMENT,
                'source_id' => (int) $installment->row_id,
                'subject' => sprintf('Tagihan Angsuran Pinjaman ke-%d', (int) $installment->installment_number),
                'reference' => $loan?->loan_code ?? $loan?->loan_number ?? null,
            ],
            'identity' => $this->identityPayload(),
            'payer' => [
                'name' => (string) $payerName,
                'identity_number' => $payerIdentity !== null ? (string) $payerIdentity : null,
                'address' => $payerAddress !== null ? (string) $payerAddress : null,
                'phone' => $memberPhone,
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => $subtotal,
                'ppn' => $ppn,
                'grand_total' => $grand,
                'paid' => $paid,
                'due' => $due,
            ],
            'payment' => [
                'method' => 'Tunai / Transfer Bank',
                'bank_account' => 'BCA a/n '.$this->identityPayload()['short_name'].' — 123-456-7890',
                'instructions' => 'Pembayaran dapat dilakukan di kantor layanan atau melalui transfer bank.',
            ],
            'notes' => 'Bukti tagihan ini bukan kuitansi pembayaran. Setelah pembayaran diterima, kuitansi akan diterbitkan oleh bendahara.',
        ];
    }

    private function resolveInstallmentMemberName(LoanInstallment $installment): ?string
    {
        try {
            $row = DB::connection('tenant')
                ->table('loan_installment_tracking as t')
                ->join('members as m', function ($j): void {
                    $j->on('m.row_id', '=', 't.member_row_id')
                        ->on('m.tenant_id', '=', 't.tenant_id');
                })
                ->join('people as p', function ($j): void {
                    $j->on('p.row_id', '=', 'm.person_row_id')
                        ->on('p.tenant_id', '=', 'm.tenant_id');
                })
                ->where('t.loan_row_id', (int) $installment->loan_row_id)
                ->where('t.installment_number', (int) $installment->installment_number)
                ->orderByDesc('t.recorded_at')
                ->first(['p.full_name']);
        } catch (\Throwable) {
            return null;
        }

        if ($row !== null && isset($row->full_name) && (string) $row->full_name !== '') {
            return (string) $row->full_name;
        }

        return null;
    }

    private function resolveInstallmentMemberPhone(LoanInstallment $installment): ?string
    {
        try {
            $row = DB::connection('tenant')
                ->table('loan_installment_tracking as t')
                ->join('members as m', function ($j): void {
                    $j->on('m.row_id', '=', 't.member_row_id')
                        ->on('m.tenant_id', '=', 't.tenant_id');
                })
                ->where('t.loan_row_id', (int) $installment->loan_row_id)
                ->where('t.installment_number', (int) $installment->installment_number)
                ->orderByDesc('t.recorded_at')
                ->first(['m.phone']);
        } catch (\Throwable) {
            return null;
        }

        if ($row !== null && isset($row->phone) && (string) $row->phone !== '') {
            return (string) $row->phone;
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function fromJournalEntry(int $id): array
    {
        /** @var JournalEntry|null $entry */
        $entry = JournalEntry::query()->with('lines.account')->find($id);
        if ($entry === null) {
            throw new DomainException("Jurnal #{$id} tidak ditemukan.");
        }

        $entry->loadMissing(['lines.account']);

        $items = [];
        $totalDebit = 0.0;

        foreach ($entry->lines as $line) {
            $amount = (float) $line->debit > 0 ? (float) $line->debit : (float) $line->credit;
            if ($amount <= 0) {
                continue;
            }
            $items[] = [
                'qty' => 1.0,
                'description' => trim(($line->account?->code ?? '').' '.($line->account?->name ?? '').($line->description ? ' — '.$line->description : '')),
                'unit_price' => $amount,
                'amount' => $amount,
            ];
            $totalDebit += $amount;
        }

        $subtotal = round($totalDebit, 2);
        $ppn = 0.0;
        $grand = $subtotal + $ppn;
        $paid = $entry->status === 'posted' ? $grand : 0.0;
        $due = round($grand - $paid, 2);

        $invoiceDate = CarbonImmutable::parse((string) $entry->transaction_date);
        $dueDate = $invoiceDate->addDays(14);

        return [
            'invoice' => [
                'number' => sprintf(
                    'JV/%s/%05d',
                    $invoiceDate->format('Y'),
                    $entry->row_id,
                ),
                'date' => $invoiceDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'source_type' => self::SOURCE_JOURNAL,
                'source_id' => (int) $entry->row_id,
                'subject' => (string) ($entry->description ?? 'Tagihan dari Jurnal'),
                'reference' => $entry->journal_number,
            ],
            'identity' => $this->identityPayload(),
            'payer' => [
                'name' => (string) ($entry->description ?? 'Pihak Ketiga'),
                'identity_number' => null,
                'address' => null,
                'phone' => null,
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => $subtotal,
                'ppn' => $ppn,
                'grand_total' => $grand,
                'paid' => $paid,
                'due' => $due,
            ],
            'payment' => [
                'method' => 'Tunai / Transfer Bank',
                'bank_account' => 'BCA a/n '.$this->identityPayload()['short_name'].' — 123-456-7890',
                'instructions' => 'Pembayaran dapat dilakukan di kantor layanan atau melalui transfer bank.',
            ],
            'notes' => $entry->status !== 'posted'
                ? 'Jurnal ini belum di-posting. Invoice dihasilkan dari jurnal rencana (preview).'
                : null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function adhoc(int $id): array
    {
        // On-the-fly invoice: ketika id tidak cocok dengan loan installment / journal entry,
        // bangun invoice placeholder dengan nomor urut agar template tidak crash.
        $identity = $this->identityPayload();
        $date = CarbonImmutable::now();

        return [
            'invoice' => [
                'number' => sprintf('INV/ADHOC/%s/%05d', $date->format('Y'), $id),
                'date' => $date->toDateString(),
                'due_date' => $date->addDays(14)->toDateString(),
                'source_type' => self::SOURCE_ADHOC,
                'source_id' => $id,
                'subject' => 'Invoice Ad-hoc',
                'reference' => null,
            ],
            'identity' => $identity,
            'payer' => [
                'name' => '—',
                'identity_number' => null,
                'address' => null,
                'phone' => null,
            ],
            'items' => [
                [
                    'qty' => 1.0,
                    'description' => 'Item invoice',
                    'unit_price' => 0.0,
                    'amount' => 0.0,
                ],
            ],
            'totals' => [
                'subtotal' => 0.0,
                'ppn' => 0.0,
                'grand_total' => 0.0,
                'paid' => 0.0,
                'due' => 0.0,
            ],
            'payment' => [
                'method' => 'Tunai / Transfer Bank',
                'bank_account' => 'BCA a/n '.($identity['short_name'] ?? $identity['legal_name']).' — 123-456-7890',
                'instructions' => 'Pembayaran dapat dilakukan di kantor layanan atau melalui transfer bank.',
            ],
            'notes' => 'Invoice dihasilkan secara otomatis karena data sumber (angsuran / jurnal) tidak ditemukan untuk ID ini.',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*                              Helpers */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string,mixed>
     */
    private function identityPayload(): array
    {
        $profile = OrganizationProfile::query()->first();

        return [
            'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
            'short_name' => $profile?->short_name,
            'address' => $profile?->address,
            'registration_number' => $profile?->registration_number,
            'tax_number' => $profile?->tax_number,
            'phone' => $profile?->phone,
            'email' => $profile?->email,
            'logo_url' => $profile?->logo_url,
            'manager_name' => $profile?->manager_name,
            'manager_title' => $profile?->manager_title,
            'treasurer_name' => $profile?->treasurer_name,
            'treasurer_title' => $profile?->treasurer_title,
        ];
    }
}
