<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetService;
use App\Http\Requests\Assets\AssetRequest;
use App\Models\Tenant\OrganizationUnit;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AssetController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly AssetService $assets,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'assets.view');

        $q = $request->query('q', $request->query('search'));
        $status = $request->query('status');
        $category = $request->query('category');
        $perPage = max(10, min(100, (int) $request->query('per_page', 15)));
        $asOf = (string) $request->query('as_of', '');

        $payload = $this->assets->index(
            q: is_string($q) ? $q : null,
            status: is_string($status) ? $status : null,
            categoryId: is_numeric($category) ? (int) $category : null,
            perPage: $perPage,
            asOf: $asOf,
        );

        return Inertia::render('Assets/Index', $payload);
    }

    public function show(Request $request, Asset $asset): Response
    {
        $this->permissions->denyUnless($request->user(), 'assets.view');

        $asOf = (string) $request->query('as_of', '');

        return Inertia::render('Assets/Show', $this->assets->detail($asset, $asOf !== '' ? $asOf : null));
    }

    public function edit(Request $request, Asset $asset): Response
    {
        $this->permissions->denyUnless($request->user(), 'assets.manage');

        $detail = $this->assets->detail($asset);

        return Inertia::render('Assets/Form', [
            'asset' => $detail['asset'],
            'categories' => $this->assets->categoryOptions(),
            'status_options' => array_values(array_filter(
                $this->assets->statusOptions(),
                fn (array $o): bool => $o['value'] !== 'all',
            )),
            'units' => $this->units(),
        ]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'assets.manage');

        try {
            $this->assets->update($asset, $request->validated(), (int) $request->user()->row_id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('accounting.assets.show', $asset)->with('success', 'Inventaris diperbarui.');
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'assets.manage');

        $this->assets->delete($asset);

        return to_route('accounting.assets.index')->with('success', 'Inventaris dihapus.');
    }

    /**
     * @return list<array{value:int,label:string}>
     */
    private function units(): array
    {
        return OrganizationUnit::query()
            ->where('is_active', true)
            ->whereIn('type', ['village', 'other_institution'])
            ->orderBy('name')
            ->get(['row_id', 'name', 'code', 'type'])
            ->map(fn (OrganizationUnit $u): array => [
                'value' => (int) $u->row_id,
                'label' => $u->name.($u->code ? " ({$u->code})" : ''),
            ])
            ->all();
    }
}
