<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use App\Domain\Membership\Models\OrganizationProfile;

/**
 * Pengaturan kolektabilitas pinjaman (Tingkat Kesehatan / Kolek).
 *
 * Mirror dari `App\Utils\Keuangan::tingkat_kesehatan()` di SIUPK original.
 *
 * Data bersumber dari kolom JSON `organization_profiles.collectibility_rules`
 * dengan format:
 *
 *   [
 *     {nama: string, prosentase: numeric, durasi: numeric, satuan: 'hari'|'bulan'},
 *     ...
 *     (maks 5 slot; yang kosong/ 'nama' kosong = nonaktif)
 *   ]
 *
 * - `prosentase` dipakai sebagai bobot CKPN per tingkat (Cadangan Penghapusan).
 * - `durasi` + `satuan` dipakai sebagai ambang `kolek_bulan` (tunggakan dalam bulan).
 *   Aturan: `kolek_bulan < durasi` → masuk tingkat tsb (kurang-dari, lihat UI).
 *
 * Default: 3 tingkat (Lancar / Diragukan / Macet) mengikuti standar UPK umum
 * dengan CKPN 0.5% / 50% / 100%, seperti yang dipakai di
 * `CollectibilityReportService::buildCadangan()` versi hardcoded.
 */
final class CollectibilityConfigService
{
    /** Default kolektabilitas 3 tingkat untuk lembaga yang belum konfigurasi. */
    private const DEFAULTS = [
        ['nama' => 'Lancar', 'prosentase' => '0.5', 'durasi' => '3', 'satuan' => 'bulan'],
        ['nama' => 'Diragukan', 'prosentase' => '50', 'durasi' => '6', 'satuan' => 'bulan'],
        ['nama' => 'Macet', 'prosentase' => '100', 'durasi' => '999', 'satuan' => 'bulan'],
        ['nama' => '', 'prosentase' => '', 'durasi' => '', 'satuan' => 'bulan'],
        ['nama' => '', 'prosentase' => '', 'durasi' => '', 'satuan' => 'bulan'],
    ];

    /** Maks 5 tingkat kolek sesuai UI. */
    public const MAX_LEVELS = 5;

    /**
     * @return list<array{nama:string, prosentase:string, durasi:string, satuan:string}>
     */
    public function all(): array
    {
        $raw = OrganizationProfile::query()->value('collectibility_rules');

        if (! is_array($raw) || $raw === []) {
            return $this->normalize(self::DEFAULTS);
        }

        // Pastikan selalu 5 slot (UI form selalu render 5 baris).
        return $this->normalize($raw);
    }

    /**
     * Hanya tingkat yang aktif (nama tidak kosong).
     *
     * @return list<array{nama:string, prosentase:string, durasi:string, satuan:string}>
     */
    public function activeOnly(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (array $r): bool => trim((string) ($r['nama'] ?? '')) !== '',
        ));
    }

    /**
     * Ambil baris kolek pada index tertentu (0-based). Return null jika nonaktif.
     *
     * @return array{nama:string, prosentase:string, durasi:string, satuan:string}|null
     */
    public function getLevel(int $index): ?array
    {
        $rows = $this->all();
        if (! isset($rows[$index])) {
            return null;
        }
        $row = $rows[$index];
        if (trim((string) ($row['nama'] ?? '')) === '') {
            return null;
        }

        return $row;
    }

    /**
     * Tentukan tingkat kolek (0-based index ke array `all()`) untuk nilai
     * `kolek_bulan` (tunggakan dalam bulan). Rumus identik dengan
     * `Keuangan::getTingkatKolek()` di SIUPK:
     *
     *   - Dari tingkat 0 → akhir, cek apakah `kolek_bulan < durasi(dalam bulan)`.
     *   - Yang pertama memenuhi → itulah tingkatnya.
     *   - Jika tidak ada yang memenuhi (tunggakan melebihi semua), masuk ke
     *     tingkat aktif terakhir.
     *
     * @return int Index 0-based; mengembalikan jumlah tingkat aktif terakhir
     *             bila `kolek_bulan` di atas seluruh ambang (kolek terburuk).
     */
    public function resolveLevelIndex(float $kolekBulan): int
    {
        $rows = $this->activeOnly();
        if ($rows === []) {
            return 0;
        }

        foreach ($rows as $idx => $row) {
            $durasiBulan = $this->durationInMonths($row);
            if ($kolekBulan < $durasiBulan) {
                return $idx;
            }
        }

        // Melebihi semua → tingkat tertinggi (index terakhir dari `activeOnly()`).
        return count($rows) - 1;
    }

    /**
     * Hitung bobot CKPN pada tingkat kolek tertentu (dalam %).
     * Mis. tingkat Macet = 100% → return 100.0.
     */
    public function ckpnPercentage(int $levelIndex): float
    {
        $row = $this->getLevel($levelIndex);
        if ($row === null) {
            return 0.0;
        }

        return (float) ($row['prosentase'] ?? 0);
    }

    /**
     * @return list<array{level:int, nama:string, prosentase:float, durasi:float, satuan:string, durasi_bulan:float}>
     */
    public function summaryForUi(): array
    {
        $rows = $this->all();
        $out = [];
        foreach ($rows as $idx => $row) {
            $nama = trim((string) ($row['nama'] ?? ''));
            $durasi = (float) ($row['durasi'] ?? 0);
            $satuan = (string) ($row['satuan'] ?? 'bulan');
            $out[] = [
                'level' => $idx + 1, // UI pakai 1-based
                'nama' => $nama,
                'prosentase' => (float) ($row['prosentase'] ?? 0),
                'durasi' => $durasi,
                'satuan' => $satuan,
                'durasi_bulan' => $satuan === 'hari' ? $durasi / 30 : $durasi,
                'aktif' => $nama !== '',
            ];
        }

        return $out;
    }

    /**
     * Konversi `durasi` + `satuan` ke satuan bulan (1 bulan = 30 hari, sesuai
     * konvensi SIUPK original).
     */
    private function durationInMonths(array $row): float
    {
        $durasi = (float) ($row['durasi'] ?? 0);
        $satuan = (string) ($row['satuan'] ?? 'bulan');

        return $satuan === 'hari' ? $durasi / 30 : $durasi;
    }

    /**
     * Normalisasi payload JSON ke 5 slot associative dengan key lengkap.
     * Mempertahankan nilai user; menambah slot kosong jika kurang dari 5.
     *
     * @param  array<int, mixed>  $raw
     * @return list<array{nama:string, prosentase:string, durasi:string, satuan:string}>
     */
    private function normalize(array $raw): array
    {
        $defaults = self::DEFAULTS;
        $out = [];
        for ($i = 0; $i < self::MAX_LEVELS; $i++) {
            $row = $raw[$i] ?? $defaults[$i] ?? ['nama' => '', 'prosentase' => '', 'durasi' => '', 'satuan' => 'bulan'];
            if (! is_array($row)) {
                $row = $defaults[$i] ?? ['nama' => '', 'prosentase' => '', 'durasi' => '', 'satuan' => 'bulan'];
            }
            $out[] = [
                'nama' => (string) ($row['nama'] ?? ''),
                'prosentase' => (string) ($row['prosentase'] ?? ''),
                'durasi' => (string) ($row['durasi'] ?? ''),
                'satuan' => (string) ($row['satuan'] ?? 'bulan'),
            ];
        }

        return $out;
    }
}
