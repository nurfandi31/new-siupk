<?php

declare(strict_types=1);

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetCategory;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Laporan Rekapitulasi Inventaris / Aset Tetap & Aset Tak Berwujud.
 * Menghitung Harga Perolehan, Akumulasi Penyusutan, dan Nilai Buku per Kategori.
 */
final class AssetReportService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AssetService $assetService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month = null, ?string $categoryType = null): array
    {
        $month = $month ?? (int) date('n');
        $asOfDate = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth();
        $profile = OrganizationProfile::query()->first();

        $categoriesQuery = AssetCategory::query()->orderBy('code');
        if ($categoryType === 'tangible') {
            $categoriesQuery->where('code', '!=', 'ATB');
        } elseif ($categoryType === 'intangible') {
            $categoriesQuery->where('code', 'ATB');
        }

        $categories = $categoriesQuery->get();
        $categoryBlocks = [];

        $grandTotals = [
            'unit' => 0,
            'acquisition' => 0.0,
            'depreciation_year' => 0.0,
            'depreciation_accumulated' => 0.0,
            'book_value' => 0.0,
        ];

        foreach ($categories as $cat) {
            $assets = Asset::query()
                ->where('asset_category_row_id', $cat->row_id)
                ->orderBy('purchased_at')
                ->orderBy('name')
                ->get();

            if ($assets->isEmpty()) {
                continue;
            }

            $catTotals = [
                'unit' => 0,
                'acquisition' => 0.0,
                'depreciation_year' => 0.0,
                'depreciation_accumulated' => 0.0,
                'book_value' => 0.0,
            ];

            $assetRows = [];
            foreach ($assets as $idx => $asset) {
                $calc = $this->assetService->bookValue($asset, $asOfDate);
                $qty = max(1, (int) ($asset->quantity ?? 1));
                $unitCost = (float) ($asset->unit_cost ?? 0);
                $acq = $calc['acquisition'];
                $lifeMonths = (int) ($asset->useful_life_months ?? 0);
                $monthlyDepr = $lifeMonths > 0 ? round($acq / $lifeMonths, 2) : 0.0;

                // Penyusutan tahun berjalan (maks 12 bulan atau s.d. bulan berjalan)
                $purchasedAt = $asset->purchased_at ? CarbonImmutable::parse($asset->purchased_at) : null;
                $monthsThisYear = 0;
                if ($purchasedAt !== null && $lifeMonths > 0) {
                    if ($purchasedAt->year === $year) {
                        $monthsThisYear = min($lifeMonths, max(0, $month - $purchasedAt->month + 1));
                    } elseif ($purchasedAt->year < $year) {
                        $monthsPrior = ($year - $purchasedAt->year - 1) * 12 + (12 - $purchasedAt->month + 1);
                        $remainingLife = max(0, $lifeMonths - $monthsPrior);
                        $monthsThisYear = min($remainingLife, $month);
                    }
                }
                $deprThisYear = round($monthlyDepr * $monthsThisYear, 2);
                $accumDepr = $calc['accumulated_depreciation'];
                $bookVal = $calc['book_value'];

                $row = [
                    'no' => $idx + 1,
                    'asset_code' => (string) ($asset->asset_code ?? "#{$asset->id}"),
                    'name' => (string) $asset->name,
                    'purchased_at' => $asset->purchased_at?->format('d/m/Y') ?? '—',
                    'condition' => (string) ($asset->status ?? 'good'),
                    'unit' => $qty,
                    'unit_cost' => $unitCost,
                    'acquisition' => $acq,
                    'useful_life_months' => $lifeMonths,
                    'monthly_depreciation' => $monthlyDepr,
                    'depreciation_year' => $deprThisYear,
                    'months_this_year' => $monthsThisYear,
                    'accumulated_depreciation' => $accumDepr,
                    'book_value' => $bookVal,
                ];

                $assetRows[] = $row;

                $catTotals['unit'] += $qty;
                $catTotals['acquisition'] += $acq;
                $catTotals['depreciation_year'] += $deprThisYear;
                $catTotals['depreciation_accumulated'] += $accumDepr;
                $catTotals['book_value'] += $bookVal;
            }

            $categoryBlocks[] = [
                'category_code' => (string) $cat->code,
                'category_name' => (string) $cat->name,
                'assets' => $assetRows,
                'totals' => $catTotals,
            ];

            $grandTotals['unit'] += $catTotals['unit'];
            $grandTotals['acquisition'] += $catTotals['acquisition'];
            $grandTotals['depreciation_year'] += $catTotals['depreciation_year'];
            $grandTotals['depreciation_accumulated'] += $catTotals['depreciation_accumulated'];
            $grandTotals['book_value'] += $catTotals['book_value'];
        }

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => ($monthNames[$month] ?? "Bulan {$month}")." {$year}",
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
                'district_name' => $profile?->district_name,
                'regency_name' => $profile?->regency_name,
            ],
            'categories' => $categoryBlocks,
            'totals' => $grandTotals,
        ];
    }
}
