<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Accounting\Services\Reports\SimpananReportService;
use App\Tenancy\TenantContext;

/**
 * DRT — Daftar Rincian Tabungan (OJK).
 *
 * Laporan regulasi OJK yang menampilkan rekening simpanan anggota lengkap.
 * Saat ini SIUPKNext belum memiliki modul simpanan penuh, sehingga laporan
 * ini menggunakan data dari tabel `accounts` (chart of accounts) dengan kode
 * akun liability kategori simpanan (2.1.10 — 2.1.13) via SimpananReportService.
 *
 * Untuk setiap akun ditampilkan: kode akun, nama rekening, jenis simpanan,
 * saldo awal, mutasi debit, mutasi kredit, saldo akhir. Laporan juga
 * menyertakan catatan bahwa detail per anggota belum tersedia karena modul
 * simpanan sedang dalam pengembangan.
 */
final class SavingsService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SimpananReportService $simpanan,
    ) {}

    /**
     * @return array{
     *   period: array<string, mixed>,
     *   identity: array<string, string|null>,
     *   rows: list<array<string, mixed>>,
     *   by_kind: array<string, array<string, mixed>>,
     *   totals: array<string, float>,
     *   generated_at: string,
     *   tenant_id: int,
     *   disclaimer: string
     * }
     */
    public function buildReport(int $year, ?int $month): array
    {
        $tenantId = (int) ($this->context->id() ?? 0);
        $payload = $this->simpanan->buildReport($tenantId, $year, $month);

        // Disclaimer OJK: detail per anggota belum tersedia karena modul
        // simpanan sedang dalam pengembangan.
        $payload['disclaimer'] = 'Detail per anggota belum tersedia karena modul simpanan sedang dalam pengembangan. Laporan ini bersumber dari kode akun liability kategori simpanan pada chart of accounts (2.1.10 — 2.1.13).';

        return $payload;
    }
}
