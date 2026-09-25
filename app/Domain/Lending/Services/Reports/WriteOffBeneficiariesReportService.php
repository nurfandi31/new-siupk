<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pinjaman Dihapusbukukan — Anggota KELOMPOK (per pemanfaat/beneficiary).
 *
 * Menampilkan daftar anggota kelompok yang dihapusbukukan secara parsial
 * dari pinjaman kelompok yang masih aktif, berdasarkan tabel
 * `loan_beneficiary_write_offs`. Mirror SIUPK original
 * `pinjaman_anggota_hapus.blade.php` untuk variant KELOMPOK.
 */
final class WriteOffBeneficiariesReportService
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   period_label: string,
     *   identity: array{legal_name: string, short_name: ?string},
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function buildReport(int $year, int $month, ?string $productCode = null): array
    {
        $tenantId = $this->context->id();
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $profile = OrganizationProfile::query()->first();

        $rows = DB::connection('tenant')
            ->table('loan_beneficiary_write_offs as w')
            ->join('loans as l', function ($j): void {
                $j->on('l.tenant_id', '=', 'w.tenant_id')
                    ->on('l.row_id', '=', 'w.loan_row_id');
            })
            ->join('members as m', function ($j): void {
                $j->on('m.tenant_id', '=', 'w.tenant_id')
                    ->on('m.row_id', '=', 'w.member_row_id');
            })
            ->join('people as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->leftJoin('loan_beneficiaries as lb', function ($j): void {
                $j->on('lb.tenant_id', '=', 'w.tenant_id')
                    ->on('lb.loan_row_id', '=', 'w.loan_row_id')
                    ->on('lb.member_row_id', '=', 'w.member_row_id');
            })
            ->leftJoin('group_members as gm', function ($j): void {
                $j->on('gm.tenant_id', '=', 'm.tenant_id')
                    ->on('gm.member_row_id', '=', 'm.row_id');
            })
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'gm.tenant_id')
                    ->on('g.row_id', '=', 'gm.group_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->leftJoin('loan_products as prod', function ($j): void {
                $j->on('prod.tenant_id', '=', 'l.tenant_id')
                    ->on('prod.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('w.tenant_id', $tenantId)
            ->whereBetween(DB::raw('DATE(w.written_off_at)'), [$startOfMonth, $endOfMonth])
            ->when(
                $productCode !== null && $productCode !== 'all',
                fn ($q) => $q->where('prod.code', $productCode),
            )
            ->orderBy('v.name')
            ->orderBy('g.name')
            ->orderBy('p.full_name')
            ->selectRaw('
                w.row_id,
                w.written_off_at,
                w.principal_balance,
                w.reason,
                w.member_row_id,
                lb.allocated_amount,
                m.member_number,
                p.full_name as member_name,
                p.national_identity_number as nik,
                g.code as group_code,
                g.name as group_name,
                v.name as village_name,
                l.row_id as loan_row_id,
                l.loan_number,
                prod.code as product_code,
                prod.name as product_name
            ')
            ->get();

        $reportRows = [];
        $totals = [
            'count' => 0,
            'saldo_total' => 0.0,
        ];
        foreach ($rows as $r) {
            $reportRows[] = [
                'written_off_at' => (string) $r->written_off_at,
                'member_number' => $r->member_number,
                'member_name' => (string) ($r->member_name ?? '—'),
                'nik' => $r->nik,
                'group_name' => $r->group_name,
                'group_code' => $r->group_code,
                'village_name' => $r->village_name,
                'loan_number' => $r->loan_number,
                'product_code' => $r->product_code,
                'product_name' => $r->product_name,
                'allocated_amount' => (float) $r->allocated_amount,
                'principal_balance' => (float) $r->principal_balance,
                'reason' => $r->reason,
            ];
            $totals['count']++;
            $totals['saldo_total'] += (float) $r->principal_balance;
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
            ],
            'rows' => $reportRows,
            'totals' => [
                'count' => $totals['count'],
                'saldo_total' => round($totals['saldo_total'], 2),
            ],
        ];
    }
}
