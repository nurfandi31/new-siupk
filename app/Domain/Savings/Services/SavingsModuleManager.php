<?php

declare(strict_types=1);

namespace App\Domain\Savings\Services;

use App\Domain\Savings\SavingsModule;
use App\Models\Tenant\TenantModule;
use App\Tenancy\TenantContext;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Pengelola siklus hidup modul simpanan per tenant.
 *
 * - enable()  : membuat tabel `simp_*_t{tenant}` + seeder jenis simpanan.
 * - disable() : menolak penghapusan bila masih ada data, menghapus tabel bila
 *               seluruhnya kosong.
 * - status()  : laporan keadaan modul (dipakai pusat tenant dan halaman
 *               pengaturan simpanan).
 */
final class SavingsModuleManager
{
    /**
     * Nilai awal mengikuti SOP simpanan SIUPK:
     *  - bunga 0,18% per bulan, pajak 1%, admin bulanan 0
     *  - biaya buka buku Rp5.000 (dipotong dari setoran awal)
     *  - tgl_bunga 0 (efektif tanggal 1), metode 3 = saldo rata-rata
     *  - min_bunga/min_pajak 0, saldo minimal penarikan Rp20.000
     */
    public const DEFAULT_SETTINGS = [
        'interest_rate' => 0.18,
        'tax_rate' => 1.0,
        'admin_fee' => 0.0,
        'opening_fee' => 5000.0,
        'interest_day' => 0,
        'minimum_balance' => 0.0,
        'minimum_taxable_interest' => 0.0,
        'interest_method' => 3,
        'allow_negative_balance' => false,
        'minimum_withdrawal_balance' => 20000.0,
        'default_product_code' => '1',
        'account_number_prefix' => 'SMP',
    ];

    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    /** @var array<int, bool> */
    private array $enabledCache = [];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(?int $tenantId = null): array
    {
        $tenantId ??= $this->context->id();

        if (isset($this->cache[$tenantId])) {
            return $this->cache[$tenantId];
        }

        $tables = SavingsModule::tables($tenantId);
        $schema = $this->schema();
        $exists = [];
        $counts = [];

        foreach ($tables as $key => $table) {
            $exists[$key] = $schema->hasTable($table);
            $counts[$key] = $exists[$key] ? $this->rowCount($table) : null;
        }

        $module = $this->module($tenantId);
        $flagEnabled = (bool) ($module?->is_enabled ?? false);
        $missing = array_keys(array_filter($exists, static fn (bool $value): bool => ! $value));
        $totalRows = 0;
        $dataRows = 0;
        foreach ($counts as $key => $value) {
            if (! is_int($value)) {
                continue;
            }

            $totalRows += $value;

            // Baris master (jenis simpanan) adalah seed sistem, bukan data
            // operasional. Penghapusan tabel hanya diblokir oleh data rekening,
            // mutasi, atau riwayat bunga.
            if ($key !== 'products') {
                $dataRows += $value;
            }
        }

        $status = [
            'code' => SavingsModule::CODE,
            'tenant_id' => $tenantId,
            'is_enabled' => $flagEnabled && $missing === [],
            'flag_enabled' => $flagEnabled,
            'tables' => $tables,
            'table_labels' => SavingsModule::TABLE_LABELS,
            'tables_exist' => $exists,
            'missing_tables' => $missing,
            'row_counts' => $counts,
            'total_rows' => $totalRows,
            'data_rows' => $dataRows,
            'has_data' => $dataRows > 0,
            'enabled_at' => $module?->enabled_at?->toIso8601String(),
            'disabled_at' => $module?->disabled_at?->toIso8601String(),
            'settings' => $this->settings($tenantId),
        ];

        $this->enabledCache[$tenantId] = $status['is_enabled'];

        return $this->cache[$tenantId] = $status;
    }

    /**
     * Ringkasan cepat untuk middleware / sidebar.
     */
    public function isEnabled(?int $tenantId = null): bool
    {
        $tenantId ??= $this->context->id();

        if (isset($this->cache[$tenantId])) {
            return (bool) ($this->cache[$tenantId]['is_enabled'] ?? false);
        }

        if (array_key_exists($tenantId, $this->enabledCache)) {
            return $this->enabledCache[$tenantId];
        }

        $module = $this->module($tenantId);

        if ($module === null || ! $module->is_enabled) {
            return $this->enabledCache[$tenantId] = false;
        }

        $schema = $this->schema();

        foreach (SavingsModule::tables($tenantId) as $table) {
            if (! $schema->hasTable($table)) {
                return $this->enabledCache[$tenantId] = false;
            }
        }

        return $this->enabledCache[$tenantId] = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function enable(int $tenantId, array $settings = []): array
    {
        $this->assertContext($tenantId);
        $this->assertTenantExists($tenantId);

        $schema = $this->schema();
        $tables = SavingsModule::tables($tenantId);

        $this->createTables($schema, $tenantId, $tables);
        $this->seedProductsIfEmpty($tenantId, $tables, $this->mergeSettings($settings));

        $module = $this->module($tenantId) ?? new TenantModule;

        $module->forceFill([
            'tenant_id' => $tenantId,
            'module_code' => SavingsModule::CODE,
            'is_enabled' => true,
            'settings' => $this->mergeSettings([...$this->settings($tenantId), ...$settings]),
            'tables' => array_values($tables),
            'enabled_at' => $module->enabled_at ?? now(),
            'disabled_at' => null,
        ])->save();

        unset($this->cache[$tenantId], $this->enabledCache[$tenantId]);

        return $this->status($tenantId);
    }

    /**
     * Hapus tabel modul. Menolak bila ada data dan $force = false.
     *
     * @return array<string, mixed>
     */
    public function disable(int $tenantId, bool $force = false): array
    {
        $this->assertContext($tenantId);

        $status = $this->status($tenantId);

        if ($status['has_data'] && ! $force) {
            $details = [];
            foreach (SavingsModule::TABLE_LABELS as $key => $label) {
                if ($key === 'products') {
                    continue;
                }

                $count = $status['row_counts'][$key] ?? 0;
                if (is_int($count) && $count > 0) {
                    $details[] = "{$label}: {$count} baris";
                }
            }

            throw new DomainException(
                'Modul simpanan tidak dapat dihapus karena masih memiliki data ('
                .implode(', ', $details)
                .'). Kosongkan data terlebih dahulu atau nonaktifkan modul tanpa menghapus tabel.',
            );
        }

        $schema = $this->schema();
        $tables = SavingsModule::tables($tenantId);

        foreach (['transactions', 'interest_runs', 'accounts', 'products'] as $key) {
            $schema->dropIfExists($tables[$key]);
        }

        // Nomor rekening ikut direset agar modul yang diaktifkan kembali mulai
        // dari urutan bersih (tabel rekening sudah dihapus).
        DB::connection((string) config('tenancy.tenant_connection', 'tenant'))
            ->table('tenant_sequences')
            ->where('tenant_id', $tenantId)
            ->where('sequence_name', 'like', 'savings_account:%')
            ->delete();

        $module = $this->module($tenantId);
        if ($module !== null) {
            $module->forceFill([
                'is_enabled' => false,
                'tables' => [],
                'disabled_at' => now(),
            ])->save();
        }

        unset($this->cache[$tenantId], $this->enabledCache[$tenantId]);

        return $this->status($tenantId);
    }

    /**
     * Matikan modul tanpa menghapus tabel (data tetap tersimpan, menu hilang).
     *
     * @return array<string, mixed>
     */
    public function deactivate(int $tenantId): array
    {
        $this->assertContext($tenantId);

        $module = $this->module($tenantId) ?? new TenantModule;
        $module->forceFill([
            'tenant_id' => $tenantId,
            'module_code' => SavingsModule::CODE,
            'is_enabled' => false,
            'disabled_at' => now(),
        ])->save();

        unset($this->cache[$tenantId], $this->enabledCache[$tenantId]);

        return $this->status($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(?int $tenantId = null): array
    {
        $tenantId ??= $this->context->id();
        $module = $this->module($tenantId);

        return $this->mergeSettings(is_array($module?->settings) ? $module->settings : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function saveSettings(int $tenantId, array $settings): array
    {
        $this->assertContext($tenantId);
        $module = $this->module($tenantId) ?? new TenantModule;
        $merged = $this->mergeSettings([...$this->settings($tenantId), ...$settings]);

        $module->forceFill([
            'tenant_id' => $tenantId,
            'module_code' => SavingsModule::CODE,
            'is_enabled' => (bool) ($module->is_enabled ?? true),
            'settings' => $merged,
            'tables' => $module->tables ?? array_values(SavingsModule::tables($tenantId)),
        ])->save();

        unset($this->cache[$tenantId], $this->enabledCache[$tenantId]);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function mergeSettings(array $settings): array
    {
        $merged = [...self::DEFAULT_SETTINGS, ...$settings];

        $merged['interest_rate'] = max(0.0, min(100.0, (float) $merged['interest_rate']));
        $merged['tax_rate'] = max(0.0, min(100.0, (float) $merged['tax_rate']));
        $merged['admin_fee'] = max(0.0, (float) $merged['admin_fee']);
        $merged['opening_fee'] = max(0.0, (float) $merged['opening_fee']);
        // SIUPK mengizinkan tanggal bunga negatif, mis. -1 = akhir bulan.
        $merged['interest_day'] = max(-28, min(28, (int) $merged['interest_day']));
        $merged['minimum_balance'] = max(0.0, (float) $merged['minimum_balance']);
        $merged['minimum_taxable_interest'] = max(0.0, (float) $merged['minimum_taxable_interest']);
        $merged['minimum_withdrawal_balance'] = max(0.0, (float) ($merged['minimum_withdrawal_balance'] ?? 0));
        $merged['interest_method'] = in_array((int) $merged['interest_method'], [1, 2, 3], true)
            ? (int) $merged['interest_method']
            : 3;
        $merged['allow_negative_balance'] = (bool) $merged['allow_negative_balance'];
        $merged['default_product_code'] = (string) ($merged['default_product_code'] ?: '1');
        $merged['account_number_prefix'] = strtoupper((string) ($merged['account_number_prefix'] ?: 'SMP'));

        return $merged;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function module(int $tenantId): ?TenantModule
    {
        return TenantModule::query()
            ->where('tenant_id', $tenantId)
            ->where('module_code', SavingsModule::CODE)
            ->first();
    }

    private function schema(): Builder
    {
        return Schema::connection((string) config('tenancy.tenant_connection', 'tenant'));
    }

    private function rowCount(string $table): int
    {
        return (int) DB::connection((string) config('tenancy.tenant_connection', 'tenant'))
            ->table($table)
            ->count();
    }

    private function assertContext(int $tenantId): void
    {
        if (! $this->context->isInitialized()) {
            throw new RuntimeException('Tenant context belum diinisialisasi untuk mengelola modul simpanan.');
        }

        if ($this->context->id() !== $tenantId) {
            throw new RuntimeException('Tenant context tidak cocok dengan tenant modul simpanan.');
        }
    }

    private function assertTenantExists(int $tenantId): void
    {
        $exists = DB::connection((string) config('tenancy.tenant_connection', 'tenant'))
            ->table('tenant_registry')
            ->where('id', $tenantId)
            ->exists();

        if (! $exists) {
            throw new RuntimeException("Tenant [{$tenantId}] belum tersinkron ke shard.");
        }
    }

    /**
     * @param  array<string, string>  $tables
     */
    private function createTables(Builder $schema, int $tenantId, array $tables): void
    {
        /** @var list<string> $created */
        $created = [];

        try {
            if (! $schema->hasTable($tables['products'])) {
                $schema->create($tables['products'], function (Blueprint $table) use ($tenantId): void {
                    $table->bigIncrements('row_id');
                    $table->unsignedBigInteger('tenant_id');
                    $table->string('code', 12);
                    $table->string('name', 100);
                    $table->string('category', 30)->default('lainnya');
                    $table->unsignedTinyInteger('form_type')->default(1);
                    $table->decimal('interest_rate', 8, 3)->default(0);
                    $table->decimal('tax_rate', 8, 3)->default(0);
                    $table->decimal('admin_fee', 18, 2)->default(0);
                    $table->decimal('opening_fee', 18, 2)->default(0);
                    $table->decimal('minimum_balance', 18, 2)->default(0);
                    $table->boolean('is_active')->default(true);
                    $table->unsignedSmallInteger('sort_order')->default(0);
                    $table->text('description')->nullable();
                    $table->timestamps();

                    $table->unique(['tenant_id', 'row_id'], 'uq_sprod_'.$tenantId.'_tenant_row');
                    $table->unique(['tenant_id', 'code'], 'uq_sprod_'.$tenantId.'_code');
                    $table->foreign('tenant_id', 'fk_sprod_'.$tenantId)
                        ->references('id')->on('tenant_registry')->cascadeOnDelete();
                    $table->comment('Modul Simpanan tenant '.$tenantId.' — master jenis simpanan');
                });
                $created[] = $tables['products'];
            }

            if (! $schema->hasTable($tables['accounts'])) {
                $schema->create($tables['accounts'], function (Blueprint $table) use ($tenantId, $tables): void {
                    $table->bigIncrements('row_id');
                    $table->unsignedBigInteger('tenant_id');
                    $table->string('account_number', 40);
                    $table->unsignedBigInteger('member_row_id');
                    $table->unsignedBigInteger('product_row_id');
                    $table->decimal('balance', 18, 2)->default(0);
                    $table->decimal('interest_rate', 8, 3)->default(0);
                    $table->decimal('tax_rate', 8, 3)->default(0);
                    $table->decimal('admin_fee', 18, 2)->default(0);
                    $table->decimal('opening_fee', 18, 2)->default(0);
                    $table->date('opened_at');
                    $table->date('minimum_closing_date')->nullable();
                    $table->date('closed_at')->nullable();
                    $table->string('status', 20)->default('active');
                    $table->unsignedTinyInteger('authority_type')->default(1);
                    $table->string('institution_name', 255)->nullable();
                    $table->string('institution_position', 100)->nullable();
                    $table->string('guardian_name', 100)->nullable();
                    $table->string('relationship', 50)->nullable();
                    $table->text('notes')->nullable();
                    $table->unsignedBigInteger('created_by_user_id')->nullable();
                    $table->timestamps();

                    $table->unique(['tenant_id', 'row_id'], 'uq_sacc_'.$tenantId.'_tenant_row');
                    $table->unique(['tenant_id', 'account_number'], 'uq_sacc_'.$tenantId.'_number');
                    $table->index(['tenant_id', 'member_row_id'], 'ix_sacc_'.$tenantId.'_member');
                    $table->index(['tenant_id', 'status'], 'ix_sacc_'.$tenantId.'_status');
                    $table->foreign('tenant_id', 'fk_sacc_'.$tenantId)
                        ->references('id')->on('tenant_registry')->cascadeOnDelete();
                    $table->foreign(['tenant_id', 'member_row_id'], 'fk_sacc_'.$tenantId.'_member')
                        ->references(['tenant_id', 'row_id'])->on('members')->restrictOnDelete();
                    $table->foreign(['tenant_id', 'product_row_id'], 'fk_sacc_'.$tenantId.'_product')
                        ->references(['tenant_id', 'row_id'])->on($tables['products'])->restrictOnDelete();
                    $table->comment('Modul Simpanan tenant '.$tenantId.' — rekening anggota');
                });
                $created[] = $tables['accounts'];
            }

            if (! $schema->hasTable($tables['transactions'])) {
                $schema->create($tables['transactions'], function (Blueprint $table) use ($tenantId, $tables): void {
                    $table->bigIncrements('row_id');
                    $table->unsignedBigInteger('tenant_id');
                    $table->unsignedBigInteger('account_row_id');
                    $table->date('transaction_date');
                    $table->string('type', 20);
                    $table->unsignedSmallInteger('code')->default(0);
                    $table->string('direction', 5);
                    $table->decimal('amount', 18, 2)->default(0);
                    $table->decimal('balance_after', 18, 2)->default(0);
                    $table->string('reference', 80)->nullable();
                    $table->string('description', 255)->nullable();
                    $table->unsignedBigInteger('interest_run_row_id')->nullable();
                    $table->unsignedBigInteger('created_by_user_id')->nullable();
                    $table->timestamps();

                    $table->unique(['tenant_id', 'row_id'], 'uq_stx_'.$tenantId.'_tenant_row');
                    $table->index(['tenant_id', 'account_row_id', 'transaction_date', 'row_id'], 'ix_stx_'.$tenantId.'_account');
                    $table->index(['tenant_id', 'transaction_date'], 'ix_stx_'.$tenantId.'_date');
                    $table->index(['tenant_id', 'interest_run_row_id'], 'ix_stx_'.$tenantId.'_run');
                    $table->foreign('tenant_id', 'fk_stx_'.$tenantId)
                        ->references('id')->on('tenant_registry')->cascadeOnDelete();
                    $table->foreign(['tenant_id', 'account_row_id'], 'fk_stx_'.$tenantId.'_account')
                        ->references(['tenant_id', 'row_id'])->on($tables['accounts'])->cascadeOnDelete();
                    $table->comment('Modul Simpanan tenant '.$tenantId.' — mutasi & saldo berjalan');
                });
                $created[] = $tables['transactions'];
            }

            if (! $schema->hasTable($tables['interest_runs'])) {
                $schema->create($tables['interest_runs'], function (Blueprint $table) use ($tenantId): void {
                    $table->bigIncrements('row_id');
                    $table->unsignedBigInteger('tenant_id');
                    $table->char('period', 7);
                    $table->date('period_start')->nullable();
                    $table->date('period_end')->nullable();
                    $table->date('posting_date')->nullable();
                    $table->unsignedTinyInteger('method')->default(3);
                    $table->unsignedInteger('accounts_count')->default(0);
                    $table->decimal('gross_interest', 18, 2)->default(0);
                    $table->decimal('tax_total', 18, 2)->default(0);
                    $table->decimal('admin_total', 18, 2)->default(0);
                    $table->decimal('net_total', 18, 2)->default(0);
                    $table->string('status', 20)->default('posted');
                    $table->dateTime('posted_at')->nullable();
                    $table->unsignedBigInteger('posted_by_user_id')->nullable();
                    $table->text('notes')->nullable();
                    $table->timestamps();

                    $table->unique(['tenant_id', 'row_id'], 'uq_srun_'.$tenantId.'_tenant_row');
                    $table->unique(['tenant_id', 'period'], 'uq_srun_'.$tenantId.'_period');
                    $table->foreign('tenant_id', 'fk_srun_'.$tenantId)
                        ->references('id')->on('tenant_registry')->cascadeOnDelete();
                    $table->comment('Modul Simpanan tenant '.$tenantId.' — riwayat proses bunga');
                });
                $created[] = $tables['interest_runs'];
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($created) as $table) {
                try {
                    $schema->dropIfExists($table);
                } catch (\Throwable) {
                    // Abaikan kegagalan pembersihan agar error asli tetap dilaporkan.
                }
            }

            throw $exception;
        }
    }

    /**
     * Seed awal jenis simpanan. Aman dijalankan berulang (idempotent).
     *
     * @param  array<string, string>  $tables
     * @param  array<string, mixed>  $settings
     */
    private function seedProducts(int $tenantId, array $tables, array $settings): void
    {
        $connection = DB::connection((string) config('tenancy.tenant_connection', 'tenant'));
        $existing = $connection->table($tables['products'])
            ->where('tenant_id', $tenantId)
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->all();

        $minimum = (float) ($settings['minimum_withdrawal_balance'] ?? 20000);

        // Persis jenis simpanan SIUPK: Umum (tanpa jatuh tempo), Deposito,
        // Program, dan Utang. `form_type` mengikuti kolom `file` legacy:
        // 1 = tanpa tanggal minimal tutup, 2/3 = memakai tanggal tersebut.
        $defaults = [
            ['1', 'Simpanan Umum', 'umum', 1, (float) $settings['interest_rate'], (float) $settings['tax_rate'], (float) $settings['admin_fee'], (float) $settings['opening_fee'], $minimum, 1],
            ['2', 'Simpanan Deposito', 'deposito', 2, (float) $settings['interest_rate'], (float) $settings['tax_rate'], (float) $settings['admin_fee'], (float) $settings['opening_fee'], $minimum, 2],
            ['3', 'Simpanan Program', 'program', 3, (float) $settings['interest_rate'], (float) $settings['tax_rate'], (float) $settings['admin_fee'], (float) $settings['opening_fee'], $minimum, 3],
            ['4', 'Utang', 'utang', 3, 0.0, 0.0, 0.0, 0.0, $minimum, 4],
        ];

        $now = now();

        foreach ($defaults as [$code, $name, $category, $formType, $rate, $tax, $admin, $opening, $minimum, $sort]) {
            if (in_array($code, $existing, true)) {
                continue;
            }

            $connection->table($tables['products'])->insert([
                'tenant_id' => $tenantId,
                'code' => $code,
                'name' => $name,
                'category' => $category,
                'form_type' => $formType,
                'interest_rate' => $rate,
                'tax_rate' => $tax,
                'admin_fee' => $admin,
                'opening_fee' => $opening,
                'minimum_balance' => $minimum,
                'is_active' => true,
                'sort_order' => $sort,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $tables
     * @param  array<string, mixed>  $settings
     */
    private function seedProductsIfEmpty(int $tenantId, array $tables, array $settings): void
    {
        $connection = DB::connection((string) config('tenancy.tenant_connection', 'tenant'));
        $count = $connection->table($tables['products'])->where('tenant_id', $tenantId)->count();

        if ($count === 0) {
            $this->seedProducts($tenantId, $tables, $settings);
        }
    }
}
