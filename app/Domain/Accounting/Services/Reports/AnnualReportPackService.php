<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Paket Dokumen Pelaporan Tahunan & Administratif:
 * - Cover Buku Laporan
 * - Surat Pengantar Laporan
 * - Berita Acara Pergantian / Pengesahan Laporan
 * - Naskah Kesepakatan Bersama (MoU Antar Desa)
 */
final class AnnualReportPackService
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month = null, string $type = 'cover'): array
    {
        $month = $month ?? 12;
        $profile = OrganizationProfile::query()->first();
        $villages = OrganizationUnit::query()->orderBy('code')->get();

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
                'district_name' => $profile?->district_name ?? '',
                'regency_name' => $profile?->regency_name ?? '',
                'province_name' => $profile?->province_name ?? '',
                'address' => $profile?->address ?? '',
                'phone' => $profile?->phone ?? '',
                'email' => $profile?->email ?? '',
                'registration_number' => $profile?->registration_number ?? '',
                'director_name' => $profile?->director_name ?? 'Direktur Utama',
                'supervisor_name' => $profile?->supervisor_name ?? 'Ketua Badan Pengawas',
                'advisor_name' => $profile?->advisor_name ?? 'Penasihat',
                'logo_url' => $profile?->logo_url ?? null,
            ],
            'villages' => $villages->map(fn ($v) => [
                'name' => (string) $v->name,
                'code' => (string) $v->code,
                'head_name' => (string) ($v->head_name ?? 'Kepala Desa'),
            ])->all(),
        ];
    }
}
