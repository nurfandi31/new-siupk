<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Documents\Services\SignatureImageService;
use App\Domain\Documents\Services\SignatureTemplateService;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Savings\Models\SavingsProduct;
use App\Domain\Savings\Services\SavingsModuleManager;
use App\Domain\Savings\Services\SavingsProductService;
use App\Http\Requests\Settings\IdentityRequest;
use App\Http\Requests\Settings\LendingSystemRequest;
use App\Http\Requests\Settings\LogoUploadRequest;
use App\Http\Requests\Settings\OfflineAccessRequest;
use App\Http\Requests\Settings\SignatureImageUploadRequest;
use App\Http\Requests\Settings\SignaturesRequest;
use App\Http\Requests\Settings\WhatsappRequest;
use App\Services\OfflineAccessService;
use App\Services\TenantSettingService;
use App\Services\WhatsappGatewayService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SettingsController
{
    public function index(
        Request $request,
        TenantContext $context,
        TenantSettingService $settings,
        WhatsappGatewayService $gateway,
        SignatureTemplateService $signatures,
        SignatureImageService $images,
        OfflineAccessService $offlineAccess,
        SavingsModuleManager $savingsModules,
        SavingsProductService $savingsProducts,
        PermissionChecker $permissions,
    ): Response {
        $profile = OrganizationProfile::query()->first() ?? new OrganizationProfile([
            'legal_name' => '',
            'timezone' => 'Asia/Jakarta',
        ]);

        $columns = ['row_id', 'id', 'code', 'name', 'default_interest_rate', 'default_term_months', 'is_active'];
        if (DB::connection('tenant')->getSchemaBuilder()->hasColumn('loan_products', 'rounding_method')) {
            $columns[] = 'rounding_method';
        }

        $products = DB::connection('tenant')
            ->table('loan_products')
            ->orderBy('code')
            ->get($columns)
            ->map(fn ($p) => [
                'row_id' => (int) $p->row_id,
                'id' => (int) $p->id,
                'code' => (string) $p->code,
                'name' => (string) $p->name,
                'default_interest_rate' => (float) $p->default_interest_rate,
                'default_term_months' => (int) $p->default_term_months,
                'rounding_method' => (string) ($p->rounding_method ?? 'decimal_2'),
                'is_active' => (bool) $p->is_active,
            ])
            ->values()
            ->all();

        $logoUrl = $profile->logo_path ? asset('storage/'.$profile->logo_path) : null;
        $templates = $signatures->all();
        $signatureImages = $images->urls();
        $hasAny = collect($templates)->contains(fn (string $html) => trim($html) !== '');
        if (! $hasAny) {
            $templates['default'] = SignatureTemplateService::starterHtml();
        }

        return Inertia::render('Settings/Index', [
            'signatureImages' => $signatureImages,
            'identity' => [
                'legal_name' => (string) $profile->legal_name,
                'short_name' => $profile->short_name,
                'registration_number' => $profile->registration_number,
                'tax_number' => $profile->tax_number,
                'address' => $profile->address,
                'district_name' => $profile->district_name,
                'regency_name' => $profile->regency_name,
                'phone' => $profile->phone,
                'email' => $profile->email,
                'website' => $profile->website,
                'timezone' => (string) ($profile->timezone ?: 'Asia/Jakarta'),
                'operational_start_date' => $profile->operational_start_date?->toDateString(),
            ],
            'products' => $products,
            'logoUrl' => $logoUrl,
            'whatsapp' => $this->whatsappPayload($settings, $gateway),
            'offline' => [
                'is_enabled' => $offlineAccess->isEnabled($context->id()),
                'user_id' => $offlineAccess->userId($context->id()),
                'users' => $offlineAccess->selectableUsers($context->id()),
            ],
            'signatures' => [
                'templates' => $templates,
                'reportTypes' => $signatures->reportTypes(),
            ],
            'savings' => $this->savingsPayload($request, $context, $savingsModules, $savingsProducts, $permissions),
        ]);
    }

    /**
     * Payload pengaturan simpanan untuk tab "Sistem Simpanan".
     *
     * Tab hanya ditampilkan bila modul aktif dan pengguna berwenang mengelola
     * simpanan. Dengan begitu halaman Pengaturan tetap aman untuk tenant yang
     * tidak memakai modul opsional ini.
     *
     * @return array<string, mixed>
     */
    private function savingsPayload(
        Request $request,
        TenantContext $context,
        SavingsModuleManager $modules,
        SavingsProductService $products,
        PermissionChecker $permissions,
    ): array {
        try {
            if (! $modules->isEnabled($context->id())
                || ! $permissions->allows($request->user(), 'simpanan.manage')) {
                return ['enabled' => false];
            }

            return [
                'enabled' => true,
                'settings' => $modules->settings(),
                'products' => $products->list(),
                'methods' => [
                    ['value' => 1, 'label' => 'Saldo terakhir'],
                    ['value' => 2, 'label' => 'Saldo terendah'],
                    ['value' => 3, 'label' => 'Saldo rata-rata'],
                ],
                'categories' => SavingsProduct::CATEGORIES,
                'formTypes' => SavingsProduct::FORM_TYPES,
            ];
        } catch (Throwable) {
            return ['enabled' => false];
        }
    }

    public function updateIdentity(IdentityRequest $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validated();
        $tenantId = $context->id();

        OrganizationProfile::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'legal_name' => $data['legal_name'],
                'short_name' => $data['short_name'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'address' => $data['address'] ?? null,
                'district_name' => $data['district_name'] ?? null,
                'regency_name' => $data['regency_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'website' => $data['website'] ?? null,
                'timezone' => $data['timezone'],
                'operational_start_date' => isset($data['operational_start_date'])
                    ? CarbonImmutable::parse($data['operational_start_date'])->toDateString()
                    : null,
            ]
        );

        return $this->flashRedirect('Identitas lembaga berhasil diperbarui.', 'identity');
    }

    public function updateLendingSystem(LendingSystemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $rows = $data['products'] ?? [];

        foreach ($rows as $row) {
            $update = [
                'default_interest_rate' => $row['default_interest_rate'],
                'default_term_months' => $row['default_term_months'],
                'is_active' => (bool) ($row['is_active'] ?? true),
                'updated_at' => now(),
            ];

            if (DB::connection('tenant')->getSchemaBuilder()->hasColumn('loan_products', 'rounding_method')) {
                $update['rounding_method'] = $row['rounding_method'] ?? 'decimal_2';
            }

            DB::connection('tenant')->table('loan_products')
                ->where('row_id', $row['row_id'])
                ->update($update);
        }

        return $this->flashRedirect('Pengaturan sistem pinjaman berhasil diperbarui.', 'lending-system');
    }

    public function syncRounding(LoanService $loanService): JsonResponse
    {
        $count = $loanService->syncRoundingFromProducts();

        return response()->json([
            'success' => true,
            'message' => "Pembulatan berhasil disinkronkan ke {$count} pinjaman.",
        ]);
    }

    public function updateLogo(LogoUploadRequest $request, TenantContext $context): RedirectResponse
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
                'legal_name' => $profile->legal_name ?? '',
                'timezone' => $profile->timezone ?? 'Asia/Jakarta',
                'logo_path' => $path,
            ]
        );

        return $this->flashRedirect('Logo berhasil diperbarui.', 'logo');
    }

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

        return $this->flashRedirect('Logo berhasil dihapus.', 'logo');
    }

    public function updateWhatsapp(WhatsappRequest $request, TenantSettingService $settings, WhatsappGatewayService $gateway): RedirectResponse
    {
        $data = $request->validated();

        $settings->set('whatsapp.template_billing', $data['template_billing'] ?? '');
        $settings->set('whatsapp.template_installment', $data['template_installment'] ?? '');
        $gateway->setEnabled((bool) ($data['is_enabled'] ?? false));

        return $this->flashRedirect('Pengaturan WhatsApp berhasil disimpan.', 'whatsapp');
    }

    public function createWhatsappInstance(WhatsappGatewayService $gateway): JsonResponse
    {
        return response()->json($gateway->createInstance());
    }

    public function deleteWhatsappInstance(WhatsappGatewayService $gateway): JsonResponse
    {
        return response()->json($gateway->deleteInstance());
    }

    public function updateSignatures(SignaturesRequest $request, SignatureTemplateService $signatures): RedirectResponse
    {
        $data = $request->validated();
        $signatures->save($data['templates'] ?? []);

        return $this->flashRedirect('Template tanda tangan berhasil disimpan.', 'signatures');
    }

    public function storeSignatureImage(SignatureImageUploadRequest $request, SignatureImageService $images): RedirectResponse
    {
        $data = $request->validated();

        try {
            $images->store($data['report_key'], $data['image']);
        } catch (RuntimeException $e) {
            return $this->flashRedirect($e->getMessage(), 'signatures');
        }

        return $this->flashRedirect('Tanda tangan gambar berhasil disimpan.', 'signatures');
    }

    public function destroySignatureImage(Request $request, SignatureImageService $images): RedirectResponse
    {
        $reportKey = (string) $request->input('report_key');

        try {
            $images->delete($reportKey);
        } catch (RuntimeException) {
            abort(422, 'Jenis dokumen tidak dikenal.');
        }

        return $this->flashRedirect('Tanda tangan gambar berhasil dihapus.', 'signatures');
    }

    public function testWhatsapp(Request $request, WhatsappGatewayService $gateway): JsonResponse
    {
        if (! $gateway->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Gateway WA_GATEWAY_BASE / WA_GATEWAY_API_KEY belum dikonfigurasi.',
            ]);
        }

        $phone = $request->input('phone');
        if (is_string($phone) && trim($phone) !== '') {
            return response()->json($gateway->sendText($phone, 'Tes koneksi WhatsApp Gateway siupk.'));
        }

        return response()->json($gateway->connectionState());
    }

    public function instanceState(WhatsappGatewayService $gateway): JsonResponse
    {
        return response()->json($gateway->connectionState());
    }

    /**
     * @return array<string, mixed>
     */
    public function offlineUsers(TenantContext $context, OfflineAccessService $offlineAccess): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $offlineAccess->selectableUsers($context->id()),
        ]);
    }

    public function updateOfflineAccess(
        OfflineAccessRequest $request,
        TenantContext $context,
        OfflineAccessService $offlineAccess,
    ): RedirectResponse {
        try {
            $offlineAccess->save(
                $context->id(),
                $request->boolean('is_enabled'),
                $request->filled('user_id') ? $request->integer('user_id') : null,
            );
        } catch (InvalidArgumentException $e) {
            return $this->flashRedirect($e->getMessage(), 'offline');
        }

        return $this->flashRedirect('Pengaturan akses offline berhasil disimpan.', 'offline');
    }

    private function whatsappPayload(TenantSettingService $settings, WhatsappGatewayService $gateway): array
    {
        $state = $gateway->isConfigured()
            ? $gateway->connectionState()
            : [
                'success' => false,
                'status' => 'unconfigured',
                'state' => null,
                'qr' => null,
                'message' => 'WA_GATEWAY_BASE / WA_GATEWAY_API_KEY belum diisi di environment server.',
                'instance' => $gateway->getInstance(),
            ];

        return [
            'instance' => $gateway->getInstance(),
            'configured' => $gateway->isConfigured(),
            'template_billing' => (string) ($settings->get('whatsapp.template_billing', '') ?: ''),
            'template_installment' => (string) ($settings->get('whatsapp.template_installment', '') ?: ''),
            'is_enabled' => $gateway->isEnabled(),
            'connection' => [
                'status' => $state['status'] ?? 'unknown',
                'state' => $state['state'] ?? null,
                'qr' => $state['qr'] ?? null,
                'message' => $state['message'] ?? null,
            ],
        ];
    }

    private function flashRedirect(string $message, string $tab): RedirectResponse
    {
        session()->flash('success', ['message' => $message, 'tab' => $tab]);

        return redirect()->route('settings.index', ['tab' => $tab]);
    }
}
