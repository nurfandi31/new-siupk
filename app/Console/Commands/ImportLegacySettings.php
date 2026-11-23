<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Settings\LegacyTenantSettingsImporter;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Impor pengaturan pinjaman & simpanan dari `kecamatan` legacy.
 *
 * Wajib dijalankan untuk tenant hasil migrasi agar pembulatan angsuran
 * (kecamatan.pembulatan) dan batas tanggal cair (batas_angsuran) sama dengan
 * pacuan. Tanpa ini, dokumen/simulasi Next bisa menampilkan angka berbeda dari
 * jadwal angsuran legacy yang tersimpan.
 */
final class ImportLegacySettings extends Command
{
    protected $signature = 'legacy:import-settings
        {tenant : Tenant row ID or code (e.g. local)}
        {suffix : Legacy lokasi id (e.g. 3)}
        {--dry-run : Validate only; no writes}
        {--skip-savings : Do not update savings module settings}';

    protected $description = 'Import kecamatan lending/savings settings into Next organization profile & savings module.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        LegacyTenantSettingsImporter $importer,
    ): int {
        $tenant = $this->resolveTenant((string) $this->argument('tenant'));
        $placement = $tenant->placement;
        $shard = $placement?->shard;
        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $result = $importer->import(
                suffix: (string) $this->argument('suffix'),
                dryRun: (bool) $this->option('dry-run'),
                withSavings: ! $this->option('skip-savings'),
            );
        } finally {
            $context->clear();
            $connections->disconnect();
        }

        $this->info(sprintf(
            'Settings imported for tenant=%s suffix=%s%s',
            $tenant->code,
            $result['suffix'],
            $result['dry_run'] ? ' [DRY-RUN]' : '',
        ));

        $this->line('installment_rounding: '.$result['installment_rounding']);
        $this->line('disbursement_cutoff_day: '.$result['disbursement_cutoff_day']);
        $this->line('profile_created: '.($result['profile_created'] ? 'yes' : 'no'));

        if (is_array($result['savings_settings'])) {
            foreach ($result['savings_settings'] as $key => $value) {
                $this->line('savings.'.$key.': '.$value);
            }
            $this->line('savings_applied: '.($result['savings_applied'] ? 'yes' : 'no (module belum aktif)'));
        }

        return self::SUCCESS;
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
