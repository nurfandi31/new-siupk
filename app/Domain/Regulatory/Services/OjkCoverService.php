<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;

/**
 * Cover OJK — halaman sampul pelaporan OJK.
 *
 * Laporan regulasi OJK yang menampilkan halaman sampul formal untuk
 * pelaporan Laporan Keuangan ke Otoritas Jasa Keuangan. Halaman ini hanya
 * memuat identitas lembaga (tidak ada data transaksi) sehingga menjadi
 * pembatas visual antar laporan OJK.
 *
 * Sumber data: `organization_profiles` (satu baris per tenant).
 */
final class OJKCoverService
{
    public function buildCover(int $year, ?int $month = null): array
    {
        $month = $month ?? (int) date('n');
        $profile = OrganizationProfile::query()->first();

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $asOfDate = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth();

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => ($monthNames[$month] ?? "Bulan {$month}")." {$year}",
            'date_formatted' => $asOfDate->locale('id')->translatedFormat('d F Y'),
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
                'district_name' => (string) ($profile?->district_name ?? ''),
                'regency_name' => (string) ($profile?->regency_name ?? ''),
                'address' => (string) ($profile?->address ?? ''),
                'phone' => (string) ($profile?->phone ?? ''),
                'email' => (string) ($profile?->email ?? ''),
                'registration_number' => (string) ($profile?->registration_number ?? ''),
                'tax_number' => (string) ($profile?->tax_number ?? ''),
                'logo_url' => $profile?->logo_url ?? null,
                'manager_name' => (string) ($profile?->manager_name ?? ''),
                'secretary_name' => (string) ($profile?->secretary_name ?? ''),
                'treasurer_name' => (string) ($profile?->treasurer_name ?? ''),
                'manager_title' => (string) ($profile?->manager_title ?? 'Ketua'),
                'secretary_title' => (string) ($profile?->secretary_title ?? 'Sekretaris'),
                'treasurer_title' => (string) ($profile?->treasurer_title ?? 'Bendahara'),
            ],
        ];
    }
}
