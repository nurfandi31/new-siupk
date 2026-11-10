<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Lending\Services\CollectibilityConfigService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Http\Requests\Settings\KolekRequest;
use App\Http\Requests\Settings\SopLogoUploadRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $profile = OrganizationProfile::query()->first();

        return Inertia::render('Settings/Sop/Index', [
            'kolek' => [
                'levels' => $this->config->summaryForUi(),
                'rows' => $this->config->all(),
            ],
            'logoUrl' => $profile?->logo_url,
        ]);
    }

    /**
     * Simpan logo lembaga dari halaman SOP.
     *
     * Sumber data sama dengan Pengaturan > Logo sehingga perubahan di sini
     * langsung dipakai oleh kop dokumen dan seluruh pelaporan PDF.
     */
    public function updateLogo(SopLogoUploadRequest $request, TenantContext $context): RedirectResponse
    {
        $tenantId = $context->id();
        $file = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');

        $profile = OrganizationProfile::query()->first();
        if ($profile !== null && $profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        $path = $file->storeAs("tenants/{$tenantId}", 'logo.'.$extension, 'public');

        OrganizationProfile::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'legal_name' => $profile?->legal_name ?? '',
                'timezone' => $profile?->timezone ?? 'Asia/Jakarta',
                'logo_path' => $path,
            ]
        );

        return redirect()
            ->route('settings.sop.index', ['tab' => 'logo'])
            ->with('success', ['message' => 'Logo lembaga berhasil diperbarui.', 'tab' => 'logo']);
    }

    /**
     * Hapus logo lembaga. Kop dokumen/pelaporan otomatis tidak menampilkan logo.
     */
    public function destroyLogo(TenantContext $context): RedirectResponse
    {
        $tenantId = $context->id();
        $profile = OrganizationProfile::query()->first();

        if ($profile !== null && $profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
            DB::connection('tenant')->table('organization_profiles')
                ->where('tenant_id', $tenantId)
                ->update(['logo_path' => null, 'updated_at' => now()]);
        }

        return redirect()
            ->route('settings.sop.index', ['tab' => 'logo'])
            ->with('success', ['message' => 'Logo lembaga berhasil dihapus.', 'tab' => 'logo']);
    }

    public function updateKolek(KolekRequest $request): RedirectResponse
    {
        $rows = $request->kolekRows();
        $tenantId = $this->context->id();
        $profile = OrganizationProfile::query()->first();

        OrganizationProfile::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'legal_name' => $profile?->legal_name ?? '',
                'collectibility_rules' => $rows,
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
