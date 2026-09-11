<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

/**
 * Klasifikasi Bukti Kas dari sepasang akun debit/kredit sebuah jurnal.
 *
 * | Dokumen | Heuristik debit × kredit                                                                          |
 * |---------|---------------------------------------------------------------------------------------------------|
 * | BKM     | debit mulai `1.1.01` & kredit bukan mulai `1.1.01` — ATAU — debit mulai `1.1.02` & kredit bukan `1.1.01/1.1.02` |
 * | BKK     | debit bukan mulai `1.1.01` & kredit mulai `1.1.01`                                                |
 * | BM      | kombinasi lain (kas↔kas, atau selain `1.1.01`/`1.1.02`)                                          |
 *
 * Mengikuti standar akunting siupk untuk penomoran bukti kas (BKM/BKK/BM).
 */
final class DocumentKindClassifier
{
    public const KIND_BKM = 'BKM';

    public const KIND_BKK = 'BKK';

    public const KIND_BM = 'BM';

    /**
     * @param  string|null  $debitCode  Kode akun sisi debit (mis. "1.1.01.01") atau null bila jurnal tidak simetris
     * @param  string|null  $creditCode  Kode akun sisi kredit (mis. "1.1.02.03") atau null bila jurnal tidak simetris
     */
    public function classify(?string $debitCode, ?string $creditCode): string
    {
        $debit = (string) $debitCode;
        $credit = (string) $creditCode;
        $debitHas = $this->startsWith($debit);
        $creditHas = $this->startsWith($credit);

        $debitCash = $debitHas('1.1.01');
        $creditCash = $creditHas('1.1.01');
        $debitBank = $debitHas('1.1.02');
        $creditBank = $creditHas('1.1.02');

        // BKM: kas/bank masuk → sisi debit adalah kas, kredit non-kas
        if ($debitCash && ! $creditCash) {
            return self::KIND_BKM;
        }
        if ($debitBank && ! $creditCash && ! $creditBank) {
            return self::KIND_BKM;
        }

        // BKK: kas keluar → sisi kredit adalah kas, debit non-kas
        if ($creditCash && ! $debitCash) {
            return self::KIND_BKK;
        }

        // BM: memorial — kas↔kas, atau antar bank, atau non-kas↔non-kas
        return self::KIND_BM;
    }

    /**
     * Apakah jurnal ini merupakan bukti kas (BKM/BKK) — bisa dicetak dari
     * endpoint bukti kas. Bukti Memorial (BM) juga tercetak tapi tanpa field "Terima Dari"/"Dibayar Kepada".
     */
    public function isCashEvidence(string $kind): bool
    {
        return in_array($kind, [self::KIND_BKM, self::KIND_BKK, self::KIND_BM], true);
    }

    /**
     * @return \Closure(string): bool
     */
    private function startsWith(string $code): \Closure
    {
        return static fn (string $prefix): bool => $code !== '' && str_starts_with($code, $prefix);
    }
}
