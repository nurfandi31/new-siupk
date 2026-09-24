<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * DRPY — Rincian Pinjaman Diterima (OJK).
 *
 * Laporan regulasi OJK yang menampilkan daftar pinjaman dari pihak ketiga
 * (bank, lembaga keuangan, donor, dll.) yang diterima oleh BUMDesma. Setiap
 * baris memuat kreditur, nomor kontrak, pokok, sisa pokok, suku bunga,
 * jangka waktu, tanggal jatuh tempo, dan status.
 *
 * Filter:
 *   - Periode (year, month) → berdasarkan contract_date pada bulan tersebut
 *   - status (all/active/paid/written_off)
 *   - creditor_type (all/bank/lembaga_keuangan/donor/lainnya)
 *
 * Sumber data: tabel `loans_received` (DRPY = Daftar Rincian Pinjaman Yang
 * Diterima). Multi-tenant safe (filter by tenant_id).
 */
final class LoansReceivedService
{
    public const STATUSES = ['active', 'paid', 'written_off', 'restructured'];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   month: int|null,
     *   period_label: string,
     *   as_of: string,
     *   identity: array{legal_name: string, short_name: ?string},
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>,
     *   filters: array<string, mixed>,
     *   status: string,
     *   creditor_type: string
     * }
     */
    public function buildReport(
        int $year,
        ?int $month,
        string $status = 'all',
        string $creditorType = 'all',
    ): array {
        $tenantId = $this->context->id();

        $asOfDate = $month !== null
            ? CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()
            : CarbonImmutable::createFromDate($year, 12, 31);
        $asOfStr = $asOfDate->toDateString();

        $startDate = CarbonImmutable::createFromDate($year, $month ?? 1, 1)->startOfMonth()->toDateString();
        $endDate = $month !== null
            ? CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString()
            : CarbonImmutable::createFromDate($year, 12, 31)->toDateString();

        if (! in_array($status, ['all', ...self::STATUSES], true)) {
            $status = 'all';
        }
        if (! in_array($creditorType, ['all', 'bank', 'lembaga_keuangan', 'donor', 'pemerintah', 'lainnya'], true)) {
            $creditorType = 'all';
        }

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

        $query = DB::connection('tenant')
            ->table('loans_received')
            ->where('tenant_id', $tenantId);

        // Filter status
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Filter creditor type
        if ($creditorType !== 'all') {
            $query->where('creditor_type', $creditorType);
        }

        // Filter periode (contract_date in range)
        $query->where(function ($q) use ($startDate, $endDate): void {
            $q->whereBetween('contract_date', [$startDate, $endDate])
                ->orWhereNull('contract_date');
        });

        $rows = $query
            ->orderBy('contract_date')
            ->orderBy('id')
            ->get([
                'row_id',
                'id',
                'creditor_name',
                'creditor_type',
                'contract_number',
                'contract_date',
                'principal_amount',
                'principal_remaining',
                'interest_rate',
                'interest_type',
                'tenor_months',
                'tenor_remaining_months',
                'start_date',
                'due_date',
                'purpose',
                'status',
                'notes',
            ]);

        $totals = [
            'count' => 0,
            'principal_total' => 0.0,
            'principal_remaining_total' => 0.0,
            'active_count' => 0,
            'paid_count' => 0,
            'written_off_count' => 0,
        ];

        $data = [];
        foreach ($rows as $r) {
            $statusCode = (string) $r->status;
            $principal = (float) $r->principal_amount;
            $principalRemaining = (float) $r->principal_remaining;

            $data[] = [
                'row_id' => (int) $r->row_id,
                'id' => (int) $r->id,
                'creditor_name' => (string) ($r->creditor_name ?? '—'),
                'creditor_type' => (string) ($r->creditor_type ?? ''),
                'creditor_type_label' => $this->creditorTypeLabel((string) ($r->creditor_type ?? '')),
                'contract_number' => (string) ($r->contract_number ?? '—'),
                'contract_date' => $r->contract_date ? substr((string) $r->contract_date, 0, 10) : null,
                'principal_amount' => round($principal, 2),
                'principal_remaining' => round($principalRemaining, 2),
                'interest_rate' => round((float) $r->interest_rate, 4),
                'interest_type' => (string) ($r->interest_type ?? 'flat'),
                'tenor_months' => (int) $r->tenor_months,
                'tenor_remaining_months' => (int) ($r->tenor_remaining_months ?? 0),
                'start_date' => $r->start_date ? substr((string) $r->start_date, 0, 10) : null,
                'due_date' => $r->due_date ? substr((string) $r->due_date, 0, 10) : null,
                'purpose' => (string) ($r->purpose ?? ''),
                'status' => $statusCode,
                'status_label' => $this->statusLabel($statusCode),
                'status_tone' => $this->statusTone($statusCode),
                'notes' => (string) ($r->notes ?? ''),
            ];

            $totals['count']++;
            $totals['principal_total'] = round($totals['principal_total'] + $principal, 2);
            $totals['principal_remaining_total'] = round($totals['principal_remaining_total'] + $principalRemaining, 2);
            if ($statusCode === 'active') {
                $totals['active_count']++;
            } elseif ($statusCode === 'paid') {
                $totals['paid_count']++;
            } elseif ($statusCode === 'written_off') {
                $totals['written_off_count']++;
            }
        }

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = $month !== null
            ? ($monthNames[$month] ?? "Bulan {$month}")." {$year}"
            : "Tahun {$year}";

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => $periodLabel,
            'as_of' => $asOfStr,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
            ],
            'rows' => $data,
            'totals' => $totals,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'status' => $status,
                'creditor_type' => $creditorType,
            ],
            'status' => $status,
            'creditor_type' => $creditorType,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'paid' => 'Lunas',
            'written_off' => 'Dihapusbukukan',
            'restructured' => 'Restrukturisasi',
            default => '—',
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'paid' => 'neutral',
            'written_off' => 'error',
            'restructured' => 'warning',
            default => 'neutral',
        };
    }

    private function creditorTypeLabel(string $type): string
    {
        return match ($type) {
            'bank' => 'Bank Umum',
            'lembaga_keuangan' => 'Lembaga Keuangan',
            'donor' => 'Donor / Hibah',
            'pemerintah' => 'Pemerintah',
            'lainnya' => 'Lainnya',
            default => $type !== '' ? $type : '—',
        };
    }
}
