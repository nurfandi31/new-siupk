<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * Build payload Blade untuk cetak Bukti Kas (BKM/BKK/BM) dari JournalEntry modern.
 * Acuan layout: resources/views/transaksi/dokumen/bkm.blade.php pada legacy siupk.
 */
final class CashEvidenceService
{
    public function __construct(
        private readonly DocumentKindClassifier $classifier,
    ) {}

    public function build(JournalEntry $entry): array
    {
        $entry->loadMissing(['lines.account']);

        if (! in_array((string) $entry->status, ['posted', 'draft'], true)) {
            throw new DomainException('Hanya jurnal posted atau draft yang dapat dicetak sebagai bukti.');
        }

        if ($entry->lines->count() < 2) {
            throw new DomainException('Jurnal harus memiliki minimal dua baris (debit & kredit) untuk dicetak sebagai bukti kas.');
        }

        $debitLine = $entry->lines->firstWhere('debit', '>', 0);
        $creditLine = $entry->lines->firstWhere('credit', '>', 0);

        if ($debitLine === null || $creditLine === null) {
            throw new DomainException('Bukti kas membutuhkan satu sisi debit dan satu sisi kredit.');
        }

        $debitCode = (string) ($debitLine->account?->code ?? '');
        $creditCode = (string) ($creditLine->account?->code ?? '');

        $kind = $this->classifier->classify($debitCode, $creditCode);
        $amount = (float) $debitLine->debit;
        if ($amount <= 0.0) {
            $amount = (float) $creditLine->credit;
        }

        $profile = OrganizationProfile::query()->first();

        $entryId = (int) $entry->id;
        $journalNumber = (string) ($entry->journal_number ?? '');
        $documentNumber = $journalNumber !== '' ? $journalNumber : (string) $entryId;

        $transactionDate = $entry->transaction_date?->toDateString() ?? '';
        $documentLabel = match ($kind) {
            DocumentKindClassifier::KIND_BKM => 'Bukti Kas Masuk',
            DocumentKindClassifier::KIND_BKK => 'Bukti Kas Keluar',
            default => 'Bukti Memorial',
        };

        $debitAccName = (string) ($debitLine->account?->name ?? '');
        $creditAccName = (string) ($creditLine->account?->name ?? '');

        return [
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
                'registration_number' => $profile?->registration_number,
                'address' => $profile?->address,
                'phone' => $profile?->phone,
                'email' => $profile?->email,
                'district_name' => $profile?->district_name,
                'regency_name' => $profile?->regency_name,
                'logo_url' => $profile?->logo_url,
                'approver_role' => 'Ketua',
                'verifier_role' => 'Sekretaris',
                'preparer_role' => 'Bendahara',
                'approver_name' => '',
                'verifier_name' => '',
                'preparer_name' => '',
            ],
            'entry' => [
                'row_id' => (int) $entry->row_id,
                'id' => $entryId,
                'journal_number' => $journalNumber,
                'transaction_date' => $transactionDate,
                'description' => (string) ($entry->description ?? ''),
                'relation' => (string) ($entry->legacy_relation ?? ''),
                'source_type' => (string) ($entry->source_type ?? ''),
                'transaction_type' => (string) ($entry->transaction_type ?? ''),
                'status' => (string) $entry->status,
            ],
            'kind' => $kind,
            'document_number' => $documentNumber,
            'document_date' => $transactionDate,
            'document_date_label' => $this->formatDateIndo($transactionDate),
            'relation' => (string) ($entry->legacy_relation ?? ''),
            'description' => (string) ($entry->description ?? ''),
            'amount' => $amount,
            'amount_label' => 'Rp. '.number_format($amount, 2),
            'debit_code' => $debitCode,
            'debit_label' => $debitCode.($debitAccName !== '' ? ' - '.ucwords(strtolower($debitAccName)) : ''),
            'credit_code' => $creditCode,
            'credit_label' => $creditCode.($creditAccName !== '' ? ' - '.ucwords(strtolower($creditAccName)) : ''),
            'document' => [
                'key' => strtolower($kind),
                'label' => $documentLabel,
                'kind' => $kind,
            ],
        ];
    }

    private function formatDateIndo(string $ymd): string
    {
        if ($ymd === '') {
            return '-';
        }
        try {
            return CarbonImmutable::parse($ymd)->format('d/m/Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }
}
