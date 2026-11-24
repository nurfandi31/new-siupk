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
 * Audit kode akun pacuan pada pinjaman hasil migrasi.
 *
 * Memastikan setiap loan yang punya `legacy_product_kode` juga punya
 * `legacy_account_codes` yang:
 *  - konsisten dengan pola 1.1.03.{kode} / 4.1.01.{kode} / 4.1.02.{kode};
 *  - benar-benar ada di chart legacy (jika suffix diberikan);
 *  - menunjuk akun Next yang aktif, sehingga tidak jatuh ke fallback diam-diam.
 */
final class AuditLegacyLoanAccounts extends Command
{
    protected $signature = 'legacy:audit-loan-accounts
        {tenant : Tenant row ID or code (e.g. 5)}
        {suffix? : Legacy lokasi id (e.g. 1) untuk validasi chart}
        {--show=10 : Jumlah contoh masalah yang ditampilkan}';

    protected $description = 'Audit kode akun pacuan pinjaman migrasi (pokok, jasa, denda).';

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

        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $accounts = DB::connection('tenant')->table('accounts')
                ->where('tenant_id', $context->id())
                ->get(['code', 'name', 'is_active'])
                ->keyBy('code');

            $loans = DB::connection('tenant')->table('loans')
                ->where('tenant_id', $context->id())
                ->where(function ($query): void {
                    $query->whereNotNull('legacy_product_kode')
                        ->orWhereNotNull('legacy_account_codes');
                })
                ->orderBy('row_id')
                ->get(['row_id', 'legacy_product_kode', 'legacy_account_codes']);
        } finally {
            $context->clear();
            $connections->disconnect();
        }

        $problems = [];
        $missingCodes = 0;
        $partialCodes = 0;
        $fallbackLoans = 0;
        $ok = 0;
        $byKind = [
            'principal' => 0,
            'revenue' => 0,
            'penalty' => 0,
        ];

        foreach ($loans as $loan) {
            $kode = LegacyProductKode::normalize($loan->legacy_product_kode);
            $codes = $loan->legacy_account_codes;
            if (is_string($codes) && $codes !== '') {
                $codes = json_decode($codes, true);
            }
            if (! is_array($codes)) {
                $missingCodes++;
                $problems[] = [$loan->row_id, $kode, '-', 'legacy_account_codes kosong'];

                continue;
            }

            $loanOk = true;
            $present = 0;
            $expectedInChart = [];
            if ($kode !== null && $suffix !== '') {
                foreach (['1.1.03.', '4.1.01.', '4.1.02.'] as $prefix) {
                    if (isset($chart[$prefix.$kode])) {
                        $expectedInChart[] = $prefix.$kode;
                    }
                }
            }

            foreach (['principal' => '1.1.03.', 'revenue' => '4.1.01.', 'penalty' => '4.1.02.'] as $kind => $prefix) {
                $code = $codes[$kind] ?? null;
                if (! is_string($code) || trim($code) === '') {
                    continue;
                }

                $present++;
                $byKind[$kind]++;

                if ($kode !== null && $code !== $prefix.$kode) {
                    $loanOk = false;
                    $problems[] = [$loan->row_id, $kode, $kind, "kode {$code} != {$prefix}{$kode}"];
                }

                if ($suffix !== '' && ! isset($chart[$code])) {
                    $loanOk = false;
                    $problems[] = [$loan->row_id, $kode, $kind, "tidak ada di chart legacy {$suffix}"];
                }

                $account = $accounts->get($code);
                if ($account === null) {
                    $loanOk = false;
                    $problems[] = [$loan->row_id, $kode, $kind, "akun Next {$code} tidak ada"];
                } elseif (! (bool) $account->is_active) {
                    $loanOk = false;
                    $problems[] = [$loan->row_id, $kode, $kind, "akun Next {$code} tidak aktif"];
                }
            }

            if ($present === 0) {
                if ($suffix !== '' && $kode !== null && $expectedInChart === []) {
                    // Kode produk ini memang tidak punya akun di chart legacy;
                    // service akan fallback ke akun produk Next. Bukan misdata.
                    $fallbackLoans++;
                } else {
                    $loanOk = false;
                    $problems[] = [$loan->row_id, $kode, '-', 'tidak ada kode akun satu pun'];
                }
            } elseif ($present < 3) {
                $partialCodes++;
            }

            if ($loanOk) {
                $ok++;
            }
        }

        $this->table(
            ['Pemeriksaan', 'Jumlah'],
            [
                ['Loan dengan kode produk/akun', $loans->count()],
                ['Loan lolos semua pemeriksaan', $ok],
                ['Loan tanpa legacy_account_codes', $missingCodes],
                ['Loan dengan kode akun parsial', $partialCodes],
                ['Loan fallback (kode tanpa akun legacy)', $fallbackLoans],
                ['Akun pokok terisi', $byKind['principal']],
                ['Akun jasa terisi', $byKind['revenue']],
                ['Akun denda terisi', $byKind['penalty']],
            ],
        );

        if ($problems !== []) {
            $limit = max(1, (int) $this->option('show'));
            $this->table(
                ['Loan', 'Kode', 'Jenis', 'Masalah'],
                array_slice($problems, 0, $limit),
            );
            $this->error(sprintf('%d masalah ditemukan.', count($problems)));

            return self::FAILURE;
        }

        $this->info('OK: semua kode akun pacuan konsisten, ada di chart legacy, dan akun Next aktif.');

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
