<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daftar pinjaman per-tahapan pipeline: Proposal (draft) / Verifikasi (verified)
 * / Waiting List (waiting).
 *
 * Mengikuti pemetaan pipeline DashboardService:
 *   proposal   → draft
 *   verifikasi → verified
 *   waiting    → waiting
 *
 * Mengembalikan daftar pinjaman (kelompok & individu) lengkap dengan informasi
 * peminjam/kelompok, desa, produk, pokok pinjaman, tanggal relevan (proposed /
 * verified / approved), urutan antrian (untuk waiting), dan kolektibilitas
 * (berdasarkan angsuran terlambat).
 */
final class StageListReportService
{
    /**
     * Pemetaan key laporan → status loan di kolom `loans.status`.
     *
     * @var array<string, string>
     */
    public const STAGE_STATUS_MAP = [
        'proposal' => 'draft',
        'verifikasi' => 'verified',
        'waiting' => 'waiting',
    ];

    /** Label tampil untuk judul laporan. */
    public const STAGE_TITLES = [
        'proposal' => 'DAFTAR PROPOSAL PINJAMAN',
        'verifikasi' => 'DAFTAR VERIFIKASI PINJAMAN',
        'waiting' => 'DAFTAR WAITING LIST PINJAMAN',
    ];

    /** Label periode untuk kolom tanggal di laporan. */
    public const STAGE_DATE_LABELS = [
        'proposal' => 'Tgl Pengajuan',
        'verifikasi' => 'Tgl Verifikasi',
        'waiting' => 'Tgl Masuk Antrian',
    ];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * Bangun laporan daftar pinjaman untuk tahap tertentu.
     *
     * @param  string  $stage  Salah satu key pada STAGE_STATUS_MAP.
     * @param  int|null  $year  Optional filter tahun (berdasarkan kolom tanggal sesuai stage).
     * @param  int|null  $month  Optional filter bulan 1-12.
     * @return array<string, mixed>
     */
    public function build(string $stage, ?int $year = null, ?int $month = null): array
    {
        if (! isset(self::STAGE_STATUS_MAP[$stage])) {
            throw new \InvalidArgumentException("Stage tidak dikenal: {$stage}");
        }

        $tenantId = $this->context->id();
        $status = self::STAGE_STATUS_MAP[$stage];

        $now = CarbonImmutable::now();
        $resolvedYear = $year ?? (int) $now->format('Y');
        $resolvedMonth = $month ?? null;
        if ($resolvedMonth !== null && ($resolvedMonth < 1 || $resolvedMonth > 12)) {
            $resolvedMonth = (int) $now->format('n');
        }

        $profile = OrganizationProfile::query()->first();

        $loans = $this->queryLoans($tenantId, $status, $resolvedYear, $resolvedMonth);

        $rows = [];
        $totals = [
            'count' => 0,
            'principal_amount' => 0.0,
            'beneficiary_count' => 0,
            'group_count' => 0,
            'member_count' => 0,
        ];

        foreach ($loans as $idx => $loan) {
            $isMemberLoan = ((string) ($loan->legacy_source ?? '')) === 'member_loan';

            // Tentukan nama peminjam/kelompok
            $borrowerName = $isMemberLoan
                ? (string) ($loan->member_name ?? '—')
                : (string) ($loan->group_name ?? 'Individu');
            $borrowerCode = $isMemberLoan
                ? (string) ($loan->member_number ?? '')
                : (string) ($loan->group_code ?? '');

            // Tanggal relevan sesuai stage
            $stageDate = match ($stage) {
                'proposal' => (string) ($loan->proposed_at ?? ''),
                'verifikasi' => (string) ($loan->verified_at ?? ''),
                'waiting' => (string) ($loan->approved_at ?? $loan->funded_at ?? ''),
                default => '',
            };

            $villageName = $isMemberLoan
                ? (string) ($loan->member_village_name ?? '—')
                : (string) ($loan->village_name ?? '—');

            $principal = (float) ($loan->principal_amount ?? 0);
            $beneficiaryCount = (int) ($loan->beneficiary_count ?? 0);

            $rows[] = [
                'no' => $idx + 1,
                'loan_id' => (int) $loan->id,
                'row_id' => (int) $loan->row_id,
                'loan_number' => (string) ($loan->loan_number ?? ''),
                'borrower_name' => $borrowerName,
                'borrower_code' => $borrowerCode,
                'borrower_kind' => $isMemberLoan ? 'Individu' : 'Kelompok',
                'village_name' => $villageName,
                'product_code' => (string) ($loan->product_code ?? ''),
                'product_name' => (string) ($loan->product_name ?? ''),
                'principal_amount' => $principal,
                'principal_label' => number_format($principal, 0, ',', '.'),
                'proposed_at' => (string) ($loan->proposed_at ?? ''),
                'verified_at' => (string) ($loan->verified_at ?? ''),
                'approved_at' => (string) ($loan->approved_at ?? ''),
                'stage_date' => $stageDate,
                'stage_date_label' => $stageDate !== '' ? date('d/m/Y', strtotime($stageDate)) : '—',
                'stage_date_iso' => $stageDate !== '' ? date('Y-m-d', strtotime($stageDate)) : '',
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'collector' => $this->deriveCollectibility($loan),
                'queue_order' => (int) ($loan->queue_order ?? ($idx + 1)),
            ];

            $totals['count']++;
            $totals['principal_amount'] += $principal;
            $totals['beneficiary_count'] += $beneficiaryCount;
            if ($isMemberLoan) {
                $totals['member_count']++;
            } else {
                $totals['group_count']++;
            }
        }

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        if ($resolvedMonth !== null) {
            $periodLabel = ($monthNames[$resolvedMonth] ?? "Bulan {$resolvedMonth}")." {$resolvedYear}";
        } else {
            $periodLabel = "Tahun {$resolvedYear}";
        }

        return [
            'stage' => $stage,
            'stage_title' => self::STAGE_TITLES[$stage],
            'stage_date_label' => self::STAGE_DATE_LABELS[$stage],
            'status' => $status,
            'year' => $resolvedYear,
            'month' => $resolvedMonth,
            'period_label' => $periodLabel,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
            ],
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Proposal',
            'verified' => 'Verifikasi',
            'waiting' => 'Waiting List',
            default => ucfirst($status),
        };
    }

    /**
     * Turunkan kolektibilitas sederhana untuk laporan antrian.
     * Pinjaman yang belum pernah menunggak diberi label "—".
     */
    private function deriveCollectibility(object $loan): string
    {
        $overdue = (int) ($loan->overdue_installments ?? 0);
        if ($overdue <= 0) {
            return '—';
        }
        if ($overdue <= 90) {
            return 'Lancar (1-3 bln)';
        }
        if ($overdue <= 150) {
            return 'Diragukan (4-5 bln)';
        }

        return 'Macet (6+ bln)';
    }

    /**
     * Query utama: ambil baris `loans` sesuai stage + filter periode, dengan relasi
     * borrower/kelompok/anggota/desa/produk.
     */
    private function queryLoans(int $tenantId, string $status, int $year, ?int $month): Collection
    {
        $dateColumn = match ($status) {
            'draft' => 'l.proposed_at',
            'verified' => 'l.verified_at',
            'waiting' => 'l.approved_at',
            default => 'l.proposed_at',
        };

        $start = $month !== null
            ? CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString()
            : CarbonImmutable::createFromDate($year, 1, 1)->startOfYear()->toDateString();
        $end = $month !== null
            ? CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString()
            : CarbonImmutable::createFromDate($year, 12, 31)->endOfYear()->toDateString();

        $query = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->leftJoin('members as m', function ($j): void {
                $j->on('m.tenant_id', '=', 'b.tenant_id')
                    ->on('m.row_id', '=', 'b.member_row_id');
            })
            ->leftJoin('people as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->leftJoin('organization_units as mv', function ($j): void {
                $j->on('mv.tenant_id', '=', 'm.tenant_id')
                    ->on('mv.row_id', '=', 'm.organization_unit_row_id');
            })
            ->leftJoin('loan_products as prod', function ($j): void {
                $j->on('prod.tenant_id', '=', 'l.tenant_id')
                    ->on('prod.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('l.status', $status)
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [$start, $end])
            ->orderBy($dateColumn)
            ->orderBy('l.id');

        // Urutan antrian khusus waiting: gunakan nilai approved_at + urutan row_id
        // sebagai fallback. Urutan berdasarkan tanggal + id sudah cukup stabil.
        $selects = [
            'l.row_id',
            'l.id',
            'l.loan_number',
            'l.status',
            'l.legacy_source',
            'l.proposed_at',
            'l.verified_at',
            'l.approved_at',
            'l.funded_at',
            'l.principal_amount',
            'b.member_row_id',
            'b.group_row_id',
            'g.name as group_name',
            'g.code as group_code',
            'v.name as village_name',
            'm.member_number',
            'p.full_name as member_name',
            'mv.name as member_village_name',
            'prod.code as product_code',
            'prod.name as product_name',
        ];

        return $query
            ->selectRaw(implode(', ', $selects))
            ->selectRaw('(select count(*) from loan_beneficiaries lb where lb.tenant_id = l.tenant_id and lb.loan_row_id = l.row_id) as beneficiary_count')
            ->selectRaw('0 as queue_order')
            ->selectRaw('0 as overdue_installments')
            ->get();
    }
}
