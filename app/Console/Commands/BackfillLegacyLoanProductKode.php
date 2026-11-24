<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Lending\Support\LegacyProductKode;
use App\Domain\Migration\Support\LegacyConnection;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Isi `loans.legacy_product_kode` dari snapshot migrasi
 * (`legacy_record_mappings.source_snapshot`). Kode produk legacy menentukan
 * akun pacuan 1.1.03.xx / 4.1.01.xx / 4.1.02.xx untuk pinjaman hasil migrasi.
 */
final class BackfillLegacyLoanProductKode extends Command
{
    protected $signature = 'legacy:backfill-loan-product-kode
        {tenant : Tenant row ID or code (e.g. 5)}
        {suffix? : Legacy lokasi id (e.g. 1); wajib untuk mengisi kode akun eksplisit}
        {--dry-run : Tampilkan perubahan tanpa menulis}';

    protected $description = 'Backfill kode produk & kode akun legacy pada tabel loans dari snapshot migrasi dan chart legacy.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        LegacyConnection $legacy,
    ): int {
        $tenant = $this->resolveTenant((string) $this->argument('tenant'));
        $placement = $tenant->placement;
        $shard = $placement?->shard;
        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $suffix = trim((string) $this->argument('suffix'));
        if ($suffix !== '' && ! preg_match('/^\d+$/', $suffix)) {
            throw new RuntimeException('Suffix harus berupa id lokasi legacy (angka).');
        }

        $chart = $this->legacyChart($legacy, $suffix);
        $syncAccounts = $suffix !== '';

        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        $dryRun = (bool) $this->option('dry-run');
        $db = DB::connection('tenant');

        try {
            $mappings = $db->table('legacy_record_mappings')
                ->where('tenant_id', $context->id())
                ->where('target_table', 'loans')
                ->orderBy('target_row_id')
                ->get(['target_row_id', 'source_table', 'source_id', 'source_snapshot']);

            $updated = 0;
            $unchanged = 0;
            $withoutKode = 0;
            $missingLoan = 0;
            $distribution = [];
            $accountDistribution = [];

            foreach ($mappings as $mapping) {
                $snapshot = $mapping->source_snapshot;
                $decoded = is_string($snapshot) && $snapshot !== ''
                    ? json_decode($snapshot, true)
                    : null;
                if (! is_array($decoded)) {
                    $withoutKode++;

                    continue;
                }

                $kode = LegacyProductKode::normalize($decoded['jpp_kode'] ?? null)
                    ?? LegacyProductKode::fromJenisPp($decoded['jenis_pp'] ?? null);
                if ($kode === null) {
                    $withoutKode++;

                    continue;
                }

                $distribution[$kode] = ($distribution[$kode] ?? 0) + 1;

                $accountCodes = $this->accountCodes($kode, $chart);
                $accountKey = $accountCodes === null
                    ? 'tanpa akun'
                    : implode('|', array_values($accountCodes));
                $accountDistribution[$accountKey] = ($accountDistribution[$accountKey] ?? 0) + 1;

                $loan = $db->table('loans')
                    ->where('tenant_id', $context->id())
                    ->where('row_id', (int) $mapping->target_row_id)
                    ->first(['row_id', 'legacy_product_kode', 'legacy_account_codes']);
                if ($loan === null) {
                    $missingLoan++;

                    continue;
                }

                $update = [];
                if ((string) ($loan->legacy_product_kode ?? '') !== $kode) {
                    $update['legacy_product_kode'] = $kode;
                }

                if ($syncAccounts) {
                    $existing = $loan->legacy_account_codes;
                    if (is_string($existing) && $existing !== '') {
                        $existing = json_decode($existing, true);
                    }
                    if (! is_array($existing)) {
                        $existing = null;
                    } elseif ($existing === []) {
                        $existing = null;
                    }

                    if ($existing != $accountCodes) {
                        $update['legacy_account_codes'] = $accountCodes === null
                            ? null
                            : json_encode($accountCodes, JSON_THROW_ON_ERROR);
                    }
                }

                if ($update === []) {
                    $unchanged++;

                    continue;
                }

                if (! $dryRun) {
                    $db->table('loans')
                        ->where('tenant_id', $context->id())
                        ->where('row_id', (int) $loan->row_id)
                        ->update($update);
                }
                $updated++;
            }
        } finally {
            $context->clear();
            $connections->disconnect();
        }

        ksort($distribution);
        $this->table(
            ['Kode legacy', 'Pinjaman'],
            array_map(
                fn ($kode, int $count): array => [(string) $kode, $count],
                array_keys($distribution),
                array_values($distribution),
            ),
        );

        if ($syncAccounts) {
            ksort($accountDistribution);
            $this->table(
                ['Akun legacy (pokok|jasa|denda)', 'Pinjaman'],
                array_map(
                    fn ($codes, int $count): array => [(string) $codes, $count],
                    array_keys($accountDistribution),
                    array_values($accountDistribution),
                ),
            );
        }

        $this->line(sprintf(
            '%s: %d mapping, %d %s, %d sudah sesuai, %d tanpa kode, %d loan hilang.%s',
            $dryRun ? 'DRY RUN' : 'SELESAI',
            $mappings->count(),
            $updated,
            $dryRun ? 'akan diubah' : 'diubah',
            $unchanged,
            $withoutKode,
            $missingLoan,
            $syncAccounts ? '' : ' Kode akun tidak diisi (suffix kosong).',
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function legacyChart(LegacyConnection $legacy, string $suffix): array
    {
        if ($suffix === '') {
            return [];
        }

        $table = 'rekening_'.$suffix;
        if (! $legacy->tableExists($table)) {
            throw new RuntimeException("Tabel chart legacy {$table} tidak ditemukan; suffix kemungkinan salah.");
        }

        $chart = [];
        foreach ($legacy->select("SELECT kode_akun FROM `{$table}`") as $row) {
            $code = trim((string) ($row->kode_akun ?? ''));
            if ($code !== '') {
                $chart[$code] = true;
            }
        }

        return $chart;
    }

    /**
     * Hanya akun yang benar-benar ada di chart legacy. Kode produk tanpa akun
     * legacy (mis. kode 10 di lokasi tertentu) tidak boleh memakai akun Next
     * yang kebetulan sama kodenya.
     *
     * @param  array<string, true>  $chart
     * @return array{principal?: string, revenue?: string, penalty?: string}|null
     */
    private function accountCodes(string $kode, array $chart): ?array
    {
        $codes = [];
        foreach (['principal' => '1.1.03.', 'revenue' => '4.1.01.', 'penalty' => '4.1.02.'] as $kind => $prefix) {
            $code = $prefix.$kode;
            if (isset($chart[$code])) {
                $codes[$kind] = $code;
            }
        }

        return $codes === [] ? null : $codes;
    }

    private function resolveTenant(string $value): Tenant
    {
        return Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit($value),
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->where('code', $value),
            )
            ->firstOrFail();
    }
}
