<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\Tenant\OrganizationUnit;
use Carbon\CarbonImmutable;

/**
 * Profil Kelembagaan OJK — laporan identitas lengkap lembaga.
 *
 * Laporan regulasi OJK yang menampilkan profil kelembagaan lengkap meliputi
 * identitas hukum, NPWP, NIB, nomor izin operasional, pengurus, struktur
 * organisasi, dan informasi umum lainnya.
 *
 * Sumber data:
 *   - `organization_profiles` (satu baris per tenant) — identitas utama
 *   - `organization_units` (desa/koperasi anggota) — daftar unit
 *   - `users` (pengurus dengan role direksi/komisaris) — susunan pengurus
 *     yang ditampilkan pada laporan.
 */
final class OJKProfileService
{
    public function buildProfile(int $year, ?int $month = null): array
    {
        $month = $month ?? (int) date('n');
        $profile = OrganizationProfile::query()->first();
        $units = OrganizationUnit::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'type', 'address']);

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $asOfDate = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth();
        $operationalStart = $profile?->operational_start_date;
        $operationalSinceYear = $operationalStart instanceof CarbonImmutable ? (int) $operationalStart->year : null;

        $pengurus = $this->buildPengurus();

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => ($monthNames[$month] ?? "Bulan {$month}")." {$year}",
            'date_formatted' => $asOfDate->locale('id')->translatedFormat('d F Y'),
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
                'brand_short' => $profile?->brand_short,
                'district_name' => (string) ($profile?->district_name ?? ''),
                'regency_name' => (string) ($profile?->regency_name ?? ''),
                'province_name' => (string) ($profile?->province_name ?? ''),
                'address' => (string) ($profile?->address ?? ''),
                'phone' => (string) ($profile?->phone ?? ''),
                'email' => (string) ($profile?->email ?? ''),
                'contact_email_secondary' => (string) ($profile?->contact_email_secondary ?? ''),
                'website' => (string) ($profile?->website ?? ''),
                'registration_number' => (string) ($profile?->registration_number ?? ''),
                'tax_number' => (string) ($profile?->tax_number ?? ''),
                'logo_url' => $profile?->logo_url ?? null,
                'operational_start_date' => $operationalStart?->toDateString(),
                'operational_since_year' => $operationalSinceYear,
                'manager_name' => (string) ($profile?->manager_name ?? ''),
                'secretary_name' => (string) ($profile?->secretary_name ?? ''),
                'treasurer_name' => (string) ($profile?->treasurer_name ?? ''),
                'verifier_name' => (string) ($profile?->verifier_name ?? ''),
                'manager_title' => (string) ($profile?->manager_title ?? 'Ketua'),
                'secretary_title' => (string) ($profile?->secretary_title ?? 'Sekretaris'),
                'treasurer_title' => (string) ($profile?->treasurer_title ?? 'Bendahara'),
                'verifier_title' => (string) ($profile?->verifier_title ?? 'Verifikator'),
            ],
            'units' => $units->map(fn ($u) => [
                'code' => (string) ($u->code ?? ''),
                'name' => (string) ($u->name ?? ''),
                'type' => (string) ($u->type ?? ''),
                'address' => (string) ($u->address ?? ''),
            ])->all(),
            'units_count' => $units->count(),
            'pengurus' => $pengurus,
            'statistics' => [
                'units_count' => $units->count(),
                'pengurus_count' => count($pengurus),
            ],
        ];
    }

    /**
     * @return list<array{name: string, role: string, email: ?string, phone: ?string}>
     */
    private function buildPengurus(): array
    {
        // Ambil dari profile fields langsung (Ketua/Sekretaris/Bendahara/Verifikator)
        $profile = OrganizationProfile::query()->first();
        if ($profile === null) {
            return [];
        }

        $pengurus = [];

        if (trim((string) $profile->manager_name) !== '') {
            $pengurus[] = [
                'name' => (string) $profile->manager_name,
                'role' => (string) ($profile->manager_title ?? 'Ketua'),
                'email' => null,
                'phone' => null,
            ];
        }
        if (trim((string) $profile->secretary_name) !== '') {
            $pengurus[] = [
                'name' => (string) $profile->secretary_name,
                'role' => (string) ($profile->secretary_title ?? 'Sekretaris'),
                'email' => null,
                'phone' => null,
            ];
        }
        if (trim((string) $profile->treasurer_name) !== '') {
            $pengurus[] = [
                'name' => (string) $profile->treasurer_name,
                'role' => (string) ($profile->treasurer_title ?? 'Bendahara'),
                'email' => null,
                'phone' => null,
            ];
        }
        if (trim((string) $profile->verifier_name) !== '') {
            $pengurus[] = [
                'name' => (string) $profile->verifier_name,
                'role' => (string) ($profile->verifier_title ?? 'Verifikator'),
                'email' => null,
                'phone' => null,
            ];
        }

        return $pengurus;
    }
}
