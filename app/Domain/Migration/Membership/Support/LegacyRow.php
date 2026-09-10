<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership\Support;

/**
 * Read first non-empty property from an object using alias list.
 */
final class LegacyRow
{
    /**
     * @param  list<string>  $aliases
     */
    public static function str(object $row, array $aliases, ?string $default = null): ?string
    {
        foreach ($aliases as $key) {
            if (! property_exists($row, $key) && ! isset($row->{$key})) {
                continue;
            }
            $v = $row->{$key} ?? null;
            if ($v === null) {
                continue;
            }
            $s = trim((string) $v);
            if ($s === '' || strcasecmp($s, 'null') === 0) {
                continue;
            }

            return $s;
        }

        return $default;
    }

    /**
     * @param  list<string>  $aliases
     */
    public static function int(object $row, array $aliases, ?int $default = null): ?int
    {
        $s = self::str($row, $aliases);
        if ($s === null || ! is_numeric($s)) {
            return $default;
        }

        return (int) $s;
    }

    /**
     * @param  list<string>  $aliases
     */
    public static function date(object $row, array $aliases, ?string $default = null): ?string
    {
        $s = self::str($row, $aliases);
        if ($s === null) {
            return $default;
        }
        // d/m/Y or d-m-Y
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        // timestamp
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]/', $s, $m) === 1) {
            return $m[1];
        }

        return $default;
    }

    public static function isDeleted(object $row): bool
    {
        if (property_exists($row, 'deleted_at') || isset($row->deleted_at)) {
            $v = $row->deleted_at ?? null;
            if ($v !== null && trim((string) $v) !== '' && $v !== '0000-00-00' && $v !== '0000-00-00 00:00:00') {
                return true;
            }
        }
        $status = self::str($row, ['status', 'stat', 'aktif']);
        if ($status !== null) {
            $lower = strtolower($status);
            if (in_array($lower, ['0', 'no', 'n', 'hapus', 'deleted', 'nonaktif', 'inactive', 'keluar'], true)) {
                // "0"/nonaktif still migrates as exited — not deleted skip.
                // Only true soft-delete flag skips.
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(object $row): array
    {
        return get_object_vars($row);
    }
}
