<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform\Tenant;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\TenantDataPurifierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantDataPurifierController extends Controller
{
    public function index(Request $request, Tenant $tenant, TenantDataPurifierService $purifier): Response
    {
        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'q' => $request->query('q'),
            'source' => $request->query('source'),
            'category' => $request->query('category', 'all'),
            'page' => (int) $request->query('page', 1),
            'per_page' => (int) $request->query('per_page', 25),
        ];

        $data = $purifier->list($tenant, $filters);

        return Inertia::render('Admin/Tenants/DataPurifier/Index', [
            'tenant' => [
                'row_id' => $tenant->row_id,
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
                'status' => $tenant->status,
                'district_code' => $tenant->district_code,
                'is_training_mode' => $tenant->isTraining(),
                'training_started_at' => $tenant->training_started_at?->toIso8601String(),
                'training_ended_at' => $tenant->training_ended_at?->toIso8601String(),
                'has_completed_training' => $tenant->hasCompletedTraining(),
            ],
            ...$data,
            'sourceOptions' => [
                ['value' => 'all', 'label' => 'Semua sumber'],
                ['value' => 'manual', 'label' => 'Jurnal umum'],
                ['value' => 'loan_installment', 'label' => 'Angsuran'],
                ['value' => 'loan', 'label' => 'Pencairan'],
                ['value' => 'asset_purchase', 'label' => 'Pembelian Aset'],
                ['value' => 'journal_reversal', 'label' => 'Reversal'],
                ['value' => 'loan_write_off', 'label' => 'Penghapusan'],
                ['value' => 'loan_reschedule_close', 'label' => 'Reschedule'],
                ['value' => 'profit_allocation', 'label' => 'Alokasi laba'],
                ['value' => 'legacy_transaksi', 'label' => 'Legacy Migrasi'],
            ],
            'categoryOptions' => [
                ['value' => 'all', 'label' => 'Semua transaksi'],
                ['value' => 'training', 'label' => 'Hanya sesi pelatihan / baru'],
                ['value' => 'legacy', 'label' => 'Hanya data migrasi (legacy)'],
            ],
        ]);
    }

    public function startTraining(Tenant $tenant, TenantDataPurifierService $purifier, AuditLogger $audit): RedirectResponse
    {
        $purifier->startTraining($tenant);

        $audit->record(
            'data_purifier.start_training',
            $tenant,
            Tenant::class,
            $tenant->row_id,
            sprintf('Mode Pelatihan diaktifkan untuk tenant [%s].', $tenant->code),
        );

        return back()->with('success', sprintf('Mode Pelatihan untuk tenant "%s" berhasil diaktifkan. Transaksi yang dicatat mulai sekarang akan ditandai sebagai data pelatihan.', $tenant->name));
    }

    public function endTraining(Request $request, Tenant $tenant, TenantDataPurifierService $purifier, AuditLogger $audit): RedirectResponse
    {
        $purgeData = $request->boolean('purge_data');
        $result = $purifier->endTraining($tenant, $purgeData);

        $audit->record(
            'data_purifier.end_training',
            $tenant,
            Tenant::class,
            $tenant->row_id,
            sprintf('Mode Pelatihan diakhiri untuk tenant [%s].', $tenant->code),
            ['purge_data' => $purgeData, 'deleted_entries' => $result['deleted_entries']],
        );

        $message = sprintf('Sesi pelatihan selesai! Tenant "%s" sekarang beralih ke Mode Live / Produksi.', $tenant->name);
        if ($purgeData && $result['deleted_entries'] > 0) {
            $message .= sprintf(' Sebanyak %d transaksi pelatihan telah dibersihkan.', $result['deleted_entries']);
        }

        return back()->with('success', $message);
    }

    public function purge(Request $request, Tenant $tenant, TenantDataPurifierService $purifier, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['required', 'integer'],
            'include_reversal_pairs' => ['boolean'],
        ]);

        $result = $purifier->purge(
            tenant: $tenant,
            entryRowIds: $data['entry_ids'],
            includeReversalPairs: (bool) ($data['include_reversal_pairs'] ?? true),
        );

        $audit->record(
            'data_purifier.purge',
            $tenant,
            Tenant::class,
            $tenant->row_id,
            sprintf('Penghapusan permanen transaksi pada tenant [%s].', $tenant->code),
            [
                'entry_ids_count' => count($data['entry_ids']),
                ...$result,
            ],
        );

        return back()->with(
            'success',
            sprintf(
                'Data transaksi berhasil dihapus permanen: %d jurnal, %d baris jurnal, %d catatan angsuran.',
                $result['deleted_entries'],
                $result['deleted_lines'],
                $result['deleted_installments'],
            ),
        );
    }

    public function resetTraining(Request $request, Tenant $tenant, TenantDataPurifierService $purifier, AuditLogger $audit): RedirectResponse
    {
        $result = $purifier->resetTrainingTransactions($tenant);

        $audit->record(
            'data_purifier.reset_training',
            $tenant,
            Tenant::class,
            $tenant->row_id,
            sprintf('Reset transaksi pelatihan pada tenant [%s].', $tenant->code),
            ['deleted_entries' => $result['deleted_entries']],
        );

        return back()->with(
            'success',
            sprintf(
                'Reset transaksi pelatihan berhasil! %d jurnal telah dibersihkan. Saldo awal, anggota, kelompok, dan bagan akun tetap utuh.',
                $result['deleted_entries'],
            ),
        );
    }
}
