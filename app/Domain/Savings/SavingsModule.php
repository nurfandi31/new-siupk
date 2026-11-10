<?php

declare(strict_types=1);

namespace App\Domain\Savings;

use App\Tenancy\TenantContext;
use InvalidArgumentException;

/**
 * Konstanta & penamaan tabel modul simpanan.
 *
 * Setiap tenant (lokasi) yang mengaktifkan modul mendapat empat tabel fisik
 * terpisah dengan penanda khas `simp_` + sufiks `_t{tenant_id}`:
 *
 *   simp_products_t1        → master jenis simpanan
 *   simp_accounts_t1        → rekening simpanan anggota
 *   simp_transactions_t1    → mutasi (setoran/penarikan/bunga/pajak/admin)
 *   simp_interest_runs_t1   → riwayat proses bunga per periode
 *
 * Penamaan ini sengaja berbeda dari tabel inti (members, organization_units,
 * accounts, dst.) agar mudah dikenali di database, aman untuk drop, dan tidak
 * Bentrok dengan tabel tenant lain pada shard yang sama.
 */
final class SavingsModule
{
    public const CODE = 'simpanan';

    public const TABLE_PREFIX = 'simp_';

    /**
     * Kunci tabel + label yang dipakai untuk laporan status modul.
     *
     * @var array<string, string>
     */
    public const TABLE_LABELS = [
        'products' => 'Jenis Simpanan',
        'accounts' => 'Rekening Simpanan',
        'transactions' => 'Mutasi Simpanan',
        'interest_runs' => 'Riwayat Bunga',
    ];

    /** @var list<string> */
    public const TABLE_KEYS = ['products', 'accounts', 'transactions', 'interest_runs'];

    /**
     * @return array<string, string>
     */
    public static function tables(int $tenantId): array
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant id must be a positive integer.');
        }

        $suffix = '_t'.$tenantId;
        $tables = [];

        foreach (self::TABLE_KEYS as $key) {
            $tables[$key] = self::TABLE_PREFIX.$key.$suffix;
        }

        return $tables;
    }

    public static function table(string $key, int $tenantId): string
    {
        $tables = self::tables($tenantId);

        return $tables[$key] ?? throw new InvalidArgumentException("Unknown savings table [{$key}].");
    }

    public static function currentTenantId(): int
    {
        return app(TenantContext::class)->id();
    }
}
