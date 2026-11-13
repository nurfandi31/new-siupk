<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\User;
use App\Utils\Keuangan;
use App\Utils\Tanggal;
use DomainException;

/**
 * Build payload kuitansi untuk jurnal umum.
 *
 * Sumber datanya sama dengan Bukti Kas (BKM/BKK/BM): satu JournalEntry,
 * satu pasang akun debit/kredit, dan classifier yang sama. Perbedaannya
 * hanya pada format dokumen: kuitansi memakai "Telah Terima Dari",
 * "Uang Sebanyak" (terbilang), dan "Untuk Pembayaran".
 */
final class JournalKuitansiService
{
    public function __construct(
        private readonly DocumentKindClassifier $classifier,
        private readonly Keuangan $keuangan,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(JournalEntry $entry, ?User $user = null): array
    {
        $entry->loadMissing(['lines.account']);

        if (! in_array((string) $entry->status, ['posted', 'draft'], true)) {
            throw new DomainException('Hanya jurnal posted atau draft yang dapat dicetak sebagai kuitansi.');
        }

        if ($entry->lines->count() < 2) {
            throw new DomainException('Jurnal harus memiliki minimal dua baris (debit & kredit) untuk dicetak sebagai kuitansi.');
        }

        $debitLine = $entry->lines->firstWhere('debit', '>', 0);
        $creditLine = $entry->lines->firstWhere('credit', '>', 0);

        if ($debitLine === null || $creditLine === null) {
            throw new DomainException('Kuitansi membutuhkan satu sisi debit dan satu sisi kredit.');
        }

        $debitCode = (string) ($debitLine->account?->code ?? '');
        $creditCode = (string) ($creditLine->account?->code ?? '');
        $kind = $this->classifier->classify($debitCode, $creditCode);

        $amount = (float) $debitLine->debit;
        if ($amount <= 0.0) {
            $amount = (float) $creditLine->credit;
        }

        $profile = OrganizationProfile::query()->first();
        $legalName = (string) ($profile?->legal_name ?: config('app.name'));
        $relation = trim((string) ($entry->legacy_relation ?? ''));
        $officer = trim((string) ($user?->name ?? ''));

        // Mengikuti arah dokumen legacy:
        // - BKM: uang diterima dari relasi, dicatat/diterima petugas.
        // - BKK: uang dibayar oleh petugas, diterima relasi.
        // - BM: memorial, gunakan relasi bila ada.
        $receivedFrom = match ($kind) {
            DocumentKindClassifier::KIND_BKK => $legalName,
            default => $relation !== '' ? $relation : $legalName,
        };
        $paidBy = match ($kind) {
            DocumentKindClassifier::KIND_BKK => $officer !== '' ? $officer : '--',
            default => $relation !== '' ? $relation : '--',
        };
        $receivedBy = match ($kind) {
            DocumentKindClassifier::KIND_BKK => $relation !== '' ? $relation : '--',
            default => $officer !== '' ? $officer : '--',
        };

        $transactionDate = $entry->transaction_date?->toDateString() ?? '';
        $dateLabel = $transactionDate !== '' ? Tanggal::tglLatin($transactionDate) : '-';
        $journalNumber = trim((string) ($entry->journal_number ?? ''));
        $documentNumber = $journalNumber !== '' ? $journalNumber : (string) $entry->id;

        $debitAccName = (string) ($debitLine->account?->name ?? '');
        $creditAccName = (string) ($creditLine->account?->name ?? '');

        return [
            'identity' => [
                'legal_name' => $legalName,
                'short_name' => $profile?->short_name,
                'registration_number' => $profile?->registration_number,
                'address' => $profile?->address,
                'phone' => $profile?->phone,
                'email' => $profile?->email,
                'district_name' => $profile?->district_name,
                'regency_name' => $profile?->regency_name,
                'logo_url' => $profile?->logo_url,
            ],
            'entry' => [
                'row_id' => (int) $entry->row_id,
                'id' => (int) $entry->id,
                'journal_number' => $journalNumber,
                'transaction_date' => $transactionDate,
                'description' => (string) ($entry->description ?? ''),
                'relation' => $relation,
                'source_type' => (string) ($entry->source_type ?? ''),
                'transaction_type' => (string) ($entry->transaction_type ?? ''),
                'status' => (string) $entry->status,
            ],
            'kind' => $kind,
            'document_number' => $documentNumber,
            'document_date' => $transactionDate,
            'document_date_label' => $dateLabel,
            'relation' => $relation,
            'received_from' => $receivedFrom,
            'paid_by' => $paidBy,
            'received_by' => $receivedBy,
            'purpose' => (string) ($entry->description ?? ''),
            'amount' => $amount,
            'amount_label' => 'Rp. '.number_format($amount, 2),
            'amount_terbilang' => $this->keuangan->terbilang($amount).' Rupiah',
            'debit_code' => $debitCode,
            'debit_label' => $debitCode.($debitAccName !== '' ? ' - '.ucwords(strtolower($debitAccName)) : ''),
            'credit_code' => $creditCode,
            'credit_label' => $creditCode.($creditAccName !== '' ? ' - '.ucwords(strtolower($creditAccName)) : ''),
            'document' => [
                'key' => 'kuitansi',
                'label' => 'Kuitansi',
                'kind' => $kind,
            ],
        ];
    }
}
