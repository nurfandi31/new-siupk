<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use InvalidArgumentException;

/**
 * Parse legacy transaksi.jumlah text into DECIMAL(19,2) magnitude + sign.
 * Negative jumlah = reverse entry (swap debit/credit accounts at load).
 */
final class LegacyAmountParser
{
    /**
     * @return array{amount: string, negative: bool}
     */
    public function parseSigned(mixed $raw): array
    {
        if ($raw === null) {
            throw new InvalidArgumentException('Amount is empty.');
        }

        $s = trim((string) $raw);
        if ($s === '') {
            throw new InvalidArgumentException('Amount is empty.');
        }

        $s = preg_replace('/\s+/u', '', $s) ?? $s;
        $s = preg_replace('/^(rp\.?|idr)/iu', '', $s) ?? $s;
        $s = trim($s);

        if ($s === '' || str_contains(strtolower($s), 'e')) {
            throw new InvalidArgumentException('Unparseable amount ['.$raw.'].');
        }

        if (str_contains($s, '(') || str_contains($s, ')')) {
            throw new InvalidArgumentException('Parenthetical amount not allowed ['.$raw.'].');
        }

        $negative = false;
        if (str_starts_with($s, '-')) {
            $negative = true;
            $s = substr($s, 1);
        } elseif (str_starts_with($s, '+')) {
            $s = substr($s, 1);
        }

        if ($s === '' || preg_match('/[^0-9.,]/', $s) === 1) {
            throw new InvalidArgumentException('Non-numeric amount ['.$raw.'].');
        }

        $normalized = $this->normalizeSeparators($s, (string) $raw);

        if (preg_match('/^\d+(\.\d+)?$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Invalid normalized amount ['.$raw.'] -> ['.$normalized.'].');
        }

        $parts = explode('.', $normalized, 2);
        if (isset($parts[1]) && strlen($parts[1]) > 2) {
            throw new InvalidArgumentException('More than 2 decimal places ['.$raw.'].');
        }

        $amount = bcadd($normalized, '0', 2);

        if (bccomp($amount, '0.00', 2) < 0) {
            throw new InvalidArgumentException('Amount invalid ['.$raw.'].');
        }

        $intPart = explode('.', $amount)[0];
        if (strlen($intPart) > 17) {
            throw new InvalidArgumentException('Amount exceeds DECIMAL(19,2) ['.$raw.'].');
        }

        return ['amount' => $amount, 'negative' => $negative];
    }

    /** Magnitude only (absolute). */
    public function parse(mixed $raw): string
    {
        return $this->parseSigned($raw)['amount'];
    }

    private function normalizeSeparators(string $s, string $raw): string
    {
        $hasDot = str_contains($s, '.');
        $hasComma = str_contains($s, ',');

        if (! $hasDot && ! $hasComma) {
            return $s;
        }

        if ($hasDot && $hasComma) {
            $lastDot = strrpos($s, '.');
            $lastComma = strrpos($s, ',');
            if ($lastComma > $lastDot) {
                return str_replace(',', '.', str_replace('.', '', $s));
            }

            return str_replace(',', '', $s);
        }

        if ($hasComma && ! $hasDot) {
            $parts = explode(',', $s);
            if (count($parts) === 2 && strlen($parts[1]) <= 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
                return $parts[0].'.'.$parts[1];
            }
            if (count($parts) > 1 && $this->allThousandGroups($parts)) {
                return implode('', $parts);
            }
            throw new InvalidArgumentException('Ambiguous comma amount ['.$raw.'].');
        }

        $parts = explode('.', $s);
        if (count($parts) === 2 && $parts[1] === '' && ctype_digit($parts[0])) {
            return $parts[0];
        }
        if (count($parts) === 2 && strlen($parts[1]) <= 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
            return $s;
        }
        if (count($parts) > 1 && $this->allThousandGroups($parts)) {
            return implode('', $parts);
        }

        throw new InvalidArgumentException('Ambiguous dot amount ['.$raw.'].');
    }

    /**
     * @param  list<string>  $parts
     */
    private function allThousandGroups(array $parts): bool
    {
        if ($parts === [] || $parts[0] === '' || ! ctype_digit($parts[0])) {
            return false;
        }
        for ($i = 1, $n = count($parts); $i < $n; $i++) {
            if (! ctype_digit($parts[$i]) || strlen($parts[$i]) !== 3) {
                return false;
            }
        }

        return true;
    }
}
