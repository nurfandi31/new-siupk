<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pemanfaat Aktif KELOMPOK — daftar anggota (pemanfaat) yang sedang
 * terdaftar pada pinjaman kelompok aktif per periode tertentu.
 *
 * Mirror SIUPK original: `perkembangan_piutang/pemanfaat_aktif.blade.php` —
 * mengelompokkan per kelompok per desa, dengan kolom alokasi, tunggakan,
 * outstanding.
 */
final class ActiveBeneficiariesReportService
{
    private const ACTIVE_LOAN_STATUSES = ['active', 'disbursed'];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   period_label: string,
     *   identity: array{legal_name: string, short_name: ?string},
     *   groups: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function buildReport(int $year, int $month): array
    {
        $tenantId = $this->context->id();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        $profile = OrganizationProfile::query()->first();

        // Ambil semua loan aktif kelompok (legacy_source != member_loan).
        $activeLoans = DB::connection('tenant')
            ->table('loans as l')
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE_LOAN_STATUSES)
            ->where('l.legacy_source', '!=', 'member_loan')
            ->selectRaw('l.row_id as loan_row_id, l.id as loan_id, l.loan_number, l.disbursed_at, l.principal_amount')
            ->get();

        if ($activeLoans->isEmpty()) {
            return $this->emptyResult($year, $month, $profile);
        }

        $activeLoanIds = $activeLoans->pluck('loan_row_id')->map(fn ($id) => (int) $id)->all();

        // Ambil beneficiaries (anggota yang terdaftar di loan) + join kelompok & desa.
        $beneficiaries = DB::connection('tenant')
            ->table('loan_beneficiaries as lb')
            ->join('members as m', function ($j): void {
                $j->on('m.tenant_id', '=', 'lb.tenant_id')
                    ->on('m.row_id', '=', 'lb.member_row_id');
            })
            ->join('people as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
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
            ->where('lb.tenant_id', $tenantId)
            ->whereIn('lb.loan_row_id', $activeLoanIds)
            ->whereNull('lb.deleted_at')
            ->where('gm.status', 'active')
            ->whereNull('gm.left_at')
            ->orderBy('v.name')
            ->orderBy('g.name')
            ->orderBy('p.full_name')
            ->selectRaw('lb.loan_row_id, lb.member_row_id, lb.allocated_amount, m.member_number, p.full_name as member_name, p.national_identity_number as nik, g.row_id as group_row_id, g.code as group_code, g.name as group_name, v.row_id as village_row_id, v.name as village_name')
            ->get();

        if ($beneficiaries->isEmpty()) {
            return $this->emptyResult($year, $month, $profile);
        }

        // Hitung tunggakan per loan: angsuran jatuh tempo s.d. end-of-month
        // yang belum dibayar.
        $installments = DB::connection('tenant')
            ->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->whereIn('loan_row_id', $activeLoanIds)
            ->where('due_date', '<=', $endOfMonth)
            ->selectRaw('loan_row_id, sum(principal_due - principal_paid) as tunggakan_pokok, sum(interest_due - interest_paid) as tunggakan_jasa')
            ->groupBy('loan_row_id')
            ->get()
            ->keyBy('loan_row_id');

        $loanMap = $activeLoans->keyBy('loan_row_id');

        // Group per kelompok
        $groups = [];
        foreach ($beneficiaries as $b) {
            $gKey = (int) ($b->group_row_id ?? 0);
            if ($gKey === 0) {
                continue;
            }
            if (! isset($groups[$gKey])) {
                $groups[$gKey] = [
                    'group_row_id' => $gKey,
                    'group_code' => $b->group_code,
                    'group_name' => $b->group_name,
                    'village_name' => $b->village_name,
                    'village_row_id' => $b->village_row_id !== null ? (int) $b->village_row_id : null,
                    'members' => [],
                    'subtotal_alokasi' => 0.0,
                    'subtotal_tunggakan_pokok' => 0.0,
                    'subtotal_tunggakan_jasa' => 0.0,
                ];
            }

            $inst = $installments->get((int) $b->loan_row_id);
            $loan = $loanMap->get((int) $b->loan_row_id);
            $groups[$gKey]['members'][] = [
                'member_row_id' => (int) $b->member_row_id,
                'member_number' => $b->member_number,
                'member_name' => (string) ($b->member_name ?? '—'),
                'nik' => $b->nik,
                'allocated_amount' => (float) $b->allocated_amount,
                'tunggakan_pokok' => $inst ? max(0.0, round((float) $inst->tunggakan_pokok, 2)) : 0.0,
                'tunggakan_jasa' => $inst ? max(0.0, round((float) $inst->tunggakan_jasa, 2)) : 0.0,
                'loan_number' => $loan?->loan_number,
                'disbursed_at' => $loan?->disbursed_at,
            ];

            $groups[$gKey]['subtotal_alokasi'] += (float) $b->allocated_amount;
            $groups[$gKey]['subtotal_tunggakan_pokok'] += $inst ? max(0.0, round((float) $inst->tunggakan_pokok, 2)) : 0.0;
            $groups[$gKey]['subtotal_tunggakan_jasa'] += $inst ? max(0.0, round((float) $inst->tunggakan_jasa, 2)) : 0.0;
        }

        // Grand total
        $grandTotals = [
            'group_count' => count($groups),
            'member_count' => 0,
            'alokasi_total' => 0.0,
            'tunggakan_pokok_total' => 0.0,
            'tunggakan_jasa_total' => 0.0,
        ];
        foreach ($groups as $g) {
            $grandTotals['member_count'] += count($g['members']);
            $grandTotals['alokasi_total'] += $g['subtotal_alokasi'];
            $grandTotals['tunggakan_pokok_total'] += $g['subtotal_tunggakan_pokok'];
            $grandTotals['tunggakan_jasa_total'] += $g['subtotal_tunggakan_jasa'];
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
            'groups' => array_values($groups),
            'totals' => [
                'group_count' => $grandTotals['group_count'],
                'member_count' => $grandTotals['member_count'],
                'alokasi_total' => round($grandTotals['alokasi_total'], 2),
                'tunggakan_pokok_total' => round($grandTotals['tunggakan_pokok_total'], 2),
                'tunggakan_jasa_total' => round($grandTotals['tunggakan_jasa_total'], 2),
            ],
        ];
    }

    /**
     * @param  mixed  $profile
     * @return array{
     *   year: int,
     *   month: int,
     *   period_label: string,
     *   identity: array{legal_name: string, short_name: ?string},
     *   groups: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    private function emptyResult(int $year, int $month, $profile): array
    {
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
            'groups' => [],
            'totals' => [
                'group_count' => 0,
                'member_count' => 0,
                'alokasi_total' => 0.0,
                'tunggakan_pokok_total' => 0.0,
                'tunggakan_jasa_total' => 0.0,
            ],
        ];
    }
}
