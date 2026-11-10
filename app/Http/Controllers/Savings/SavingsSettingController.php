<?php

declare(strict_types=1);

namespace App\Http\Controllers\Savings;

use App\Domain\Savings\Models\SavingsProduct;
use App\Domain\Savings\Services\SavingsModuleManager;
use App\Domain\Savings\Services\SavingsProductService;
use App\Http\Requests\Savings\SavingsProductRequest;
use App\Http\Requests\Savings\SavingsSettingsRequest;
use App\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan modul simpanan (bunga, pajak, admin, metode saldo) dan master
 * jenis simpanan.
 */
final class SavingsSettingController
{
    public function __construct(
        private readonly SavingsModuleManager $modules,
        private readonly SavingsProductService $products,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Savings/Settings', [
            'settings' => $this->modules->settings(),
            'products' => $this->products->list(),
            'module' => $this->modules->status(),
            'methods' => [
                ['value' => 1, 'label' => 'Saldo terakhir'],
                ['value' => 2, 'label' => 'Saldo terendah'],
                ['value' => 3, 'label' => 'Saldo rata-rata'],
            ],
            'categories' => SavingsProduct::CATEGORIES,
            'formTypes' => SavingsProduct::FORM_TYPES,
        ]);
    }

    public function update(SavingsSettingsRequest $request): RedirectResponse
    {
        $this->modules->saveSettings($this->context->id(), $request->validated());

        return back()->with('success', 'Pengaturan simpanan berhasil disimpan.');
    }

    public function storeProduct(SavingsProductRequest $request): RedirectResponse
    {
        $this->products->create($request->validated());

        return back()->with('success', 'Jenis simpanan berhasil ditambahkan.');
    }

    public function updateProduct(SavingsProductRequest $request, int $product): RedirectResponse
    {
        $model = SavingsProduct::query()->whereKey($product)->firstOrFail();
        $this->products->update($model, $request->validated());

        return back()->with('success', 'Jenis simpanan berhasil diperbarui.');
    }

    public function destroyProduct(int $product): RedirectResponse
    {
        $model = SavingsProduct::query()->whereKey($product)->firstOrFail();

        try {
            $this->products->delete($model);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Jenis simpanan berhasil dihapus.');
    }
}
