<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk mengelola saldo awal (opening balance) manual dari onboarding mid-year.
 *
 * Berbeda dengan `LegacyOpeningBalanceLoader` (source='migration') dan
 * `FiscalPeriodCloseService::writeNextYearOpenings` (source='year_close'),
 * service ini menulis dengan `source='manual'` untuk input oleh superadmin
 * melalui `ImportWizard.vue` tab "Saldo Awal per Tahun".
 *
 * Source priority (kalau ada konflik):
 * - 'migration' (legacy) > 'manual' (admin onboarding) > 'year_close' (auto tutup buku)
 *
 * `upsert()` mengangkat 'manual' untuk fiscal_year kosong, throw jika existing
 * row source='migration' (data legacy harus immutable kecuali lewat loader).
 */
final class AccountOpeningBalanceService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantSequenceService $sequences,
    ) {}

    /**
     * Upsert opening balance untuk fiscal_year tertentu.
     *
     * Per baris:
     * - Jika existing row source='migration' → throw DomainException.
     * - Jika existing row source='manual' atau 'year_close' → UPDATE nilai, preserve source.
     * - Jika belum ada row → INSERT baru dengan source='manual'.
     *
     * Idempotent jika nilai input sama dengan nilai existing (no-op save updated_at unchanged).
     *
     * @param  list<array{account_row_id:int, debit:float, credit:float}>  $lines
     * @return int jumlah baris yang berhasil diupsert (write atau update).
     *
     * @throws DomainException jika imbalanced atau konflik dengan source='migration'.
     */
    public function upsert(int $fiscalYear, array $lines, int $userId): int
    {
        if ($fiscalYear < 2000 || $fiscalYear > 2100) {
            throw new DomainException('Tahun fiskal tidak valid.');
        }

        $valid = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            $accountRowId = (int) ($line['account_row_id'] ?? 0);
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($accountRowId <= 0) {
                continue;
            }

            // Skip baris nol-nol (akun tidak relevan di saldo awal ini).
            if ($debit <= 0.0 && $credit <= 0.0) {
                continue;
            }

            $valid[] = [
                'account_row_id' => $accountRowId,
                'debit' => $debit,
                'credit' => $credit,
            ];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if ($valid === []) {
            return 0;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new DomainException(sprintf(
                'Saldo awal tidak imbang. Total Debit: Rp %s vs Total Kredit: Rp %s (selisih Rp %s).',
                number_format($totalDebit, 2, ',', '.'),
                number_format($totalCredit, 2, ',', '.'),
                number_format(abs($totalDebit - $totalCredit), 2, ',', '.'),
            ));
        }

        $tenantId = $this->context->id();
        $now = now()->format('Y-m-d H:i:s');
        $conn = DB::connection((string) config('tenancy.tenant_connection', 'tenant'));
        $affected = 0;

        return $conn->transaction(function (ConnectionInterface $db) use (
            $tenantId,
            $fiscalYear,
            $valid,
            $now,
            &$affected,
        ): int {
            foreach ($valid as $line) {
                $existing = $db->table('account_opening_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('account_row_id', $line['account_row_id'])
                    ->where('fiscal_year', $fiscalYear)
                    ->lockForUpdate()
                    ->first(['row_id', 'source', 'debit', 'credit']);

                if ($existing !== null) {
                    // Tolak overwrite data legacy.
                    if ($existing->source === 'migration') {
                        throw new DomainException(sprintf(
                            'Saldo awal akun #%d tahun %d berasal dari migrasi legacy. Tidak dapat diubah manual.',
                            $line['account_row_id'],
                            $fiscalYear,
                        ));
                    }

                    // Idempotent: lewati jika nilai sama persis.
                    $sameDebit = (float) $existing->debit === $line['debit'];
                    $sameCredit = (float) $existing->credit === $line['credit'];
                    if ($sameDebit && $sameCredit) {
                        continue;
                    }

                    $db->table('account_opening_balances')
                        ->where('tenant_id', $tenantId)
                        ->where('row_id', $existing->row_id)
                        ->update([
                            'debit' => $line['debit'],
                            'credit' => $line['credit'],
                            // source dipertahankan: 'manual' atau 'year_close'
                            'updated_at' => $now,
                        ]);
                    $affected++;

                    continue;
                }

                $db->table('account_opening_balances')->insert([
                    'tenant_id' => $tenantId,
                    'id' => $this->sequences->next('account_opening_balances'),
                    'account_row_id' => $line['account_row_id'],
                    'fiscal_year' => $fiscalYear,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'source' => 'manual',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $affected++;
            }

            return $affected;
        }, 5);
    }

    /**
     * Ambil daftar opening balance existing untuk fiscal_year tertentu.
     *
     * @return list<array{account_row_id:int, debit:float, credit:float, source:string}>
     */
    public function getByYear(int $fiscalYear): array
    {
        $tenantId = $this->context->id();
        $rows = DB::connection((string) config('tenancy.tenant_connection', 'tenant'))
            ->table('account_opening_balances')
            ->where('tenant_id', $tenantId)
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('account_row_id')
            ->get(['account_row_id', 'debit', 'credit', 'source']);

        return $rows->map(fn ($r) => [
            'account_row_id' => (int) $r->account_row_id,
            'debit' => (float) $r->debit,
            'credit' => (float) $r->credit,
            'source' => (string) $r->source,
        ])->all();
    }

    /**
     * Tahun fiskal saat ini (untuk UI default).
     */
    public function currentFiscalYear(): int
    {
        return (int) CarbonImmutable::now()->year;
    }
}
