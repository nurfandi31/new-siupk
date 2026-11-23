<?php

declare(strict_types=1);

namespace App\Domain\Migration\Settings;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Migration\Support\LegacyConnection;
use App\Domain\Savings\Services\SavingsModuleManager;
use App\Tenancy\TenantContext;
use RuntimeException;

/**
 * Impor pengaturan tenant dari tabel legacy `kecamatan`.
 *
 * Ini sumber utama angka pembulatan angsuran di pacuan:
 * `kecamatan.pembulatan` dipakai untuk membulatkan pokok & jasa, dan
 * `kecamatan.batas_angsuran` menggeser jatuh tempo pertama. Tanpa impor ini,
 * tenant hasil migrasi memakai default Next ('5000' = tanpa pembulatan) sehingga
 * dokumen/simulasi menghitung angka berbeda dari jadwal legacy yang tersimpan.
 */
final class LegacyTenantSettingsImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
        private readonly TenantContext $context,
        private readonly SavingsModuleManager $savings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function import(string $suffix, bool $dryRun = false, bool $withSavings = true): array
    {
        if (! preg_match('/^\d+$/', $suffix)) {
            throw new RuntimeException('Suffix harus berupa id lokasi legacy (angka).');
        }

        if (! $this->legacy->tableExists('kecamatan')) {
            throw new RuntimeException('Tabel legacy `kecamatan` tidak ditemukan.');
        }

        $row = $this->legacy->selectOne('SELECT * FROM kecamatan WHERE id = ? LIMIT 1', [$suffix]);
        if ($row === null) {
            throw new RuntimeException("Kecamatan/lokasi legacy id={$suffix} tidak ditemukan.");
        }

        $rounding = $this->normalizeRounding($row->pembulatan ?? null);
        $cutoff = max(0, min(28, (int) ($row->batas_angsuran ?? 0)));

        $profile = OrganizationProfile::query()->first();
        $createdProfile = false;
        if ($profile === null) {
            $profile = new OrganizationProfile;
            $createdProfile = true;
        }

        $profilePatch = [
            'installment_rounding' => $rounding,
            'disbursement_cutoff_day' => $cutoff,
        ];

        if ($createdProfile) {
            $profilePatch += $this->identityFromLegacy($row);
        }

        if (! $dryRun) {
            if (! $profile->exists) {
                $profile->fill($profilePatch)->save();
            } else {
                $profile->forceFill($profilePatch)->save();
            }
        }

        $savingsSettings = null;
        if ($withSavings) {
            $savingsSettings = $this->savingsSettingsFromLegacy($row);
            if (! $dryRun && $this->savings->module($this->context->id()) !== null) {
                $this->savings->saveSettings($this->context->id(), $savingsSettings);
            }
        }

        return [
            'suffix' => $suffix,
            'dry_run' => $dryRun,
            'profile_created' => $createdProfile,
            'installment_rounding' => $rounding,
            'disbursement_cutoff_day' => $cutoff,
            'savings_settings' => $savingsSettings,
            'savings_applied' => $withSavings && ! $dryRun && $this->savings->module($this->context->id()) !== null,
        ];
    }

    private function normalizeRounding(mixed $value): string
    {
        $rounding = trim((string) ($value ?? ''));
        if ($rounding === '' || $rounding === '0') {
            // Legacy memperlakukan '5000' sebagai "tanpa pembulatan".
            return '5000';
        }

        return mb_substr($rounding, 0, 12);
    }

    /**
     * @return array<string, string|null>
     */
    private function identityFromLegacy(object $row): array
    {
        $legalName = trim((string) ($row->nama_lembaga_long ?? ''));
        if ($legalName === '' || $legalName === '0') {
            $legalName = trim((string) ($row->nama_lembaga_sort ?? ''));
        }
        if ($legalName === '' || $legalName === '0') {
            $legalName = trim((string) ($row->nama_kec ?? 'Koperasi'));
        }

        $shortName = trim((string) ($row->nama_bkad_sort ?? ''));
        if ($shortName === '' || $shortName === '0') {
            $shortName = trim((string) ($row->nama_lembaga_sort ?? ''));
        }

        $phone = trim((string) ($row->telpon_kec ?? ''));
        $email = trim((string) ($row->email_kec ?? ''));
        $address = trim((string) ($row->alamat_kec ?? ''));
        $district = trim((string) ($row->nama_kec ?? ''));

        return [
            'legal_name' => $legalName,
            'short_name' => $this->nullIfEmpty($shortName),
            'address' => $this->nullIfEmpty($address),
            'phone' => $this->nullIfEmpty($phone),
            'email' => $this->nullIfEmpty($email),
            'district_name' => $this->nullIfEmpty($district),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function savingsSettingsFromLegacy(object $row): array
    {
        // Nilai minimal penarikan legacy ada di master jenis_simpanan.
        $saldoMinimal = 20000.0;
        try {
            $minimum = $this->legacy->selectOne('SELECT MIN(saldo_minimal) AS m FROM jenis_simpanan WHERE saldo_minimal > 0');
            if ($minimum !== null && $minimum->m !== null) {
                $saldoMinimal = (float) $minimum->m;
            }
        } catch (\Throwable) {
            // Tabel master opsional; biarkan default.
        }

        return [
            'interest_rate' => (float) $this->numeric($row->def_bunga ?? 0),
            'tax_rate' => (float) $this->numeric($row->def_pajak_bunga ?? 0),
            'interest_method' => (int) ($row->hitung_bunga ?? 3),
            'interest_day' => (int) ($row->tgl_bunga ?? 0),
            'minimum_balance' => (float) $this->numeric($row->min_bunga ?? 0),
            'minimum_taxable_interest' => (float) $this->numeric($row->min_pajak ?? 0),
            'minimum_withdrawal_balance' => $saldoMinimal,
        ];
    }

    private function numeric(mixed $value): float
    {
        $value = trim((string) ($value ?? ''));

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);

        return $value === '' || $value === '0' ? null : $value;
    }
}
