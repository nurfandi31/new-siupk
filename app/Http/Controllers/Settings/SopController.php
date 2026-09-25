<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Lending\Services\CollectibilityConfigService;
use App\Http\Requests\Settings\KolekRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman khusus "SOP Lembaga" seperti `/pengaturan/sop` di SIUPK original.
 *
 * Untuk saat ini konsentrasi pada kolektabilitas. Modul-modul SOP lain
 * (simpanan, asuransi, dll) akan ditambahkan pada fase berikutnya.
 */
final class SopController
{
    public function __construct(
        private readonly CollectibilityConfigService $config,
        private readonly TenantContext $context,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Sop/Index', [
            'kolek' => [
                'levels' => $this->config->summaryForUi(),
                'rows' => $this->config->all(),
            ],
        ]);
    }

    public function updateKolek(KolekRequest $request): RedirectResponse
    {
        $rows = $request->kolekRows();
        $tenantId = $this->context->id();

        DB::connection('tenant')->table('organization_profiles')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'collectibility_rules' => json_encode($rows),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return redirect()
            ->route('settings.sop.index', ['tab' => 'kolek'])
            ->with('success', ['message' => 'Pengaturan kolektabilitas berhasil disimpan.', 'tab' => 'kolek']);
    }

    /**
     * Endpoint JSON untuk inspeksi cepat kolektabilitas (dipakai oleh report).
     */
    public function kolekLevels(): JsonResponse
    {
        return response()->json([
            'levels' => $this->config->summaryForUi(),
        ]);
    }
}
