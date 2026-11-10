<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Savings\Services\SavingsModuleManager;
use App\Models\Platform\Tenant;
use App\Services\Admin\AuditLogger;
use App\Tenancy\Services\TenantWorkbench;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Pengaturan modul simpanan dari pusat tenant.
 *
 * - enable    : buat tabel simp_* + seeder jenis simpanan.
 * - deactivate: sembunyikan modul, tabel & data tetap disimpan.
 * - disable   : hapus tabel, hanya bila seluruhnya kosong.
 */
final class TenantSavingsModuleController
{
    public function __construct(
        private readonly TenantWorkbench $workbench,
        private readonly SavingsModuleManager $modules,
        private readonly AuditLogger $audit,
    ) {}

    public function status(Tenant $tenant): JsonResponse
    {
        try {
            $status = $this->workbench->run(
                $tenant,
                fn (Tenant $model): array => $this->modules->status((int) $model->row_id),
            );
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Status modul simpanan gagal dibaca: '.$exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $status]);
    }

    public function enable(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'interest_rate' => ['nullable', 'numeric', 'between:0,100'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'admin_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'opening_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
        ]);

        $settings = [
            'interest_rate' => (float) ($validated['interest_rate'] ?? SavingsModuleManager::DEFAULT_SETTINGS['interest_rate']),
            'tax_rate' => (float) ($validated['tax_rate'] ?? SavingsModuleManager::DEFAULT_SETTINGS['tax_rate']),
            'admin_fee' => (float) ($validated['admin_fee'] ?? SavingsModuleManager::DEFAULT_SETTINGS['admin_fee']),
            'opening_fee' => (float) ($validated['opening_fee'] ?? SavingsModuleManager::DEFAULT_SETTINGS['opening_fee']),
        ];

        try {
            $result = $this->workbench->run(
                $tenant,
                function (Tenant $model) use ($settings): array {
                    $before = $this->modules->status((int) $model->row_id);
                    $after = $this->modules->enable((int) $model->row_id, $settings);

                    return ['before' => $before, 'after' => $after];
                },
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Modul simpanan gagal diaktifkan: '.$exception->getMessage());
        }

        $status = $result['after'];
        $before = $result['before'];
        $repaired = (bool) ($before['flag_enabled'] ?? false) && ($before['missing_tables'] ?? []) !== [];
        $restored = ! $repaired && (int) ($before['total_rows'] ?? 0) > 0;

        $this->audit->record(
            'tenant.savings.enable',
            $tenant,
            Tenant::class,
            $tenant->row_id,
            "Modul simpanan diaktifkan untuk tenant [{$tenant->code}].",
            ['tables' => $status['tables'], 'repaired' => $repaired],
        );

        if ($repaired) {
            return back()->with('success', 'Tabel simpanan yang hilang berhasil dibuat ulang tanpa menghapus data lain.');
        }

        if ($restored) {
            return back()->with('success', 'Modul simpanan diaktifkan kembali. Tabel dan data lama tetap tersimpan.');
        }

        return back()->with('success', 'Modul simpanan berhasil diaktifkan. Tabel dan jenis simpanan default sudah dibuat.');
    }

    public function deactivate(Tenant $tenant): RedirectResponse
    {
        try {
            $this->workbench->run(
                $tenant,
                fn (Tenant $model): array => $this->modules->deactivate((int) $model->row_id),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Modul simpanan gagal dinonaktifkan: '.$exception->getMessage());
        }

        $this->audit->record(
            'tenant.savings.deactivate',
            $tenant,
            Tenant::class,
            $tenant->row_id,
            "Modul simpanan dinonaktifkan (tanpa hapus data) untuk tenant [{$tenant->code}].",
        );

        return back()->with('success', 'Modul simpanan dinonaktifkan. Data dan tabel tetap tersimpan.');
    }

    public function disable(Tenant $tenant): RedirectResponse
    {
        try {
            $this->workbench->run(
                $tenant,
                fn (Tenant $model): array => $this->modules->disable((int) $model->row_id),
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Tabel simpanan gagal dihapus: '.$exception->getMessage());
        }

        $this->audit->record(
            'tenant.savings.disable',
            $tenant,
            Tenant::class,
            $tenant->row_id,
            "Tabel modul simpanan dihapus untuk tenant [{$tenant->code}].",
        );

        return back()->with('success', 'Tabel modul simpanan berhasil dihapus.');
    }
}
