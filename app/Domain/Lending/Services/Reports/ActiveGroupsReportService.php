<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kelompok Aktif — daftar kelompok yang memiliki pinjaman aktif
 * (sudah dicairkan dan belum lunas) per tanggal tertentu.
 *
 * Filter tambahan: kelompok yang punya transaksi pencairan atau pembayaran
 * pinjaman dalam periode berjalan (tahun & bulan) untuk menampilkan
 * kelompok-kelompok yang masih beroperasi.
 *
 * Catatan: modul simpanan belum tersedia di aplikasi ini, sehingga total
 * simpanan ditampilkan sebagai 0 dan hanya sisi pinjaman yang dihitung.
 */
final class ActiveGroupsReportService
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
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function buildReport(int $year, int $month): array
    {
        $tenantId = $this->context->id();
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $profile = OrganizationProfile::query()->first();

        // Ambil kelompok aktif yang memiliki pinjaman aktif.
        // Kelompok dianggap "aktif" jika:
        // 1) groups.status = 'active'
        // 2) ada loan aktif (legacy_source group_loan) via loan_borrowers.group_row_id
        $groups = DB::connection('tenant')
            ->table('groups as g')
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->where('g.tenant_id', $tenantId)
            ->where('g.status', 'active')
            ->selectRaw('g.row_id, g.code, g.name, g.established_at, v.row_id as village_row_id, v.name as village_name')
            ->orderBy('v.name')
            ->orderBy('g.name')
            ->get();

        $groupRowIds = $groups->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        if ($groupRowIds === []) {
            return $this->emptyResult($year, $month, $profile);
        }

        // Pinjaman aktif per kelompok (via loan_borrowers.group_row_id)
        $activeLoans = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->join('loans as l', function ($j): void {
                $j->on('l.tenant_id', '=', 'b.tenant_id')
                    ->on('l.row_id', '=', 'b.loan_row_id');
            })
            ->where('b.tenant_id', $tenantId)
            ->whereIn('b.group_row_id', $groupRowIds)
            ->whereIn('l.status', self::ACTIVE_LOAN_STATUSES)
            ->selectRaw('b.group_row_id, l.row_id as loan_row_id, l.id as loan_id, l.principal_amount, l.disbursed_at')
            ->get();

        // Group active loans by group
        $activeLoansByGroup = $activeLoans->groupBy('group_row_id');

        // Hitung saldo outstanding per loan (alokasi - realized_pokok)
        $activeLoanIds = $activeLoans->pluck('loan_row_id')->map(fn ($id) => (int) $id)->all();

        $principalRealizedByLoan = $activeLoanIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_payment_allocations as a')
                ->join('loan_payments as p', function ($j): void {
                    $j->on('p.tenant_id', '=', 'a.tenant_id')
                        ->on('p.row_id', '=', 'a.payment_row_id');
                })
                ->where('a.tenant_id', $tenantId)
                ->whereIn('p.loan_row_id', $activeLoanIds)
                ->where('a.component', 'principal')
                ->selectRaw('p.loan_row_id, sum(a.amount) as principal_paid')
                ->groupBy('p.loan_row_id')
                ->pluck('principal_paid', 'loan_row_id');

        // Hitung outstanding per loan
        $outstandingByLoan = [];
        foreach ($activeLoans as $al) {
            $key = (int) $al->loan_row_id;
            $alokasi = (float) $al->principal_amount;
            $realized = (float) ($principalRealizedByLoan[$key] ?? 0);
            $outstandingByLoan[$key] = max(0.0, round($alokasi - $realized, 2));
        }

        // Filter kelompok yang punya pinjaman aktif
        $filteredGroups = $groups->filter(function ($g) use ($activeLoansByGroup) {
            return isset($activeLoansByGroup[$g->row_id]);
        })->values();

        // Ambil data ketua kelompok (position='ketua' di group_officers)
        $ketuaMap = DB::connection('tenant')
            ->table('group_officers as go')
            ->leftJoin('members as m', function ($j): void {
                $j->on('m.tenant_id', '=', 'go.tenant_id')
                    ->on('m.row_id', '=', 'go.member_row_id');
            })
            ->leftJoin('people as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->where('go.tenant_id', $tenantId)
            ->whereIn('go.group_row_id', $filteredGroups->pluck('row_id')->map(fn ($id) => (int) $id)->all())
            ->where('go.position', 'ketua')
            ->whereNull('go.ended_at')
            ->selectRaw('go.group_row_id, p.full_name as ketua_name')
            ->get()
            ->pluck('ketua_name', 'group_row_id');

        // Hitung jumlah anggota aktif per kelompok
        $activeMemberCounts = DB::connection('tenant')
            ->table('group_members')
            ->where('tenant_id', $tenantId)
            ->whereIn('group_row_id', $filteredGroups->pluck('row_id')->map(fn ($id) => (int) $id)->all())
            ->where('status', 'active')
            ->selectRaw('group_row_id, count(*) as cnt')
            ->groupBy('group_row_id')
            ->pluck('cnt', 'group_row_id');

        // Hitung aktivitas periode: kelompok yang punya pencairan ATAU
        // pembayaran dalam periode berjalan.
        $periodeDisbures = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->join('loans as l', function ($j): void {
                $j->on('l.tenant_id', '=', 'b.tenant_id')
                    ->on('l.row_id', '=', 'b.loan_row_id');
            })
            ->where('b.tenant_id', $tenantId)
            ->whereIn('b.group_row_id', $filteredGroups->pluck('row_id')->map(fn ($id) => (int) $id)->all())
            ->whereBetween('l.disbursed_at', [$startOfMonth, $endOfMonth])
            ->distinct()
            ->pluck('b.group_row_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $periodePayments = DB::connection('tenant')
            ->table('loan_payments as p')
            ->join('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'p.tenant_id')
                    ->on('b.loan_row_id', '=', 'p.loan_row_id');
            })
            ->where('p.tenant_id', $tenantId)
            ->whereIn('b.group_row_id', $filteredGroups->pluck('row_id')->map(fn ($id) => (int) $id)->all())
            ->whereBetween('p.paid_at', [$startOfMonth, $endOfMonth])
            ->distinct()
            ->pluck('b.group_row_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $activeInPeriod = array_unique(array_merge($periodeDisbures, $periodePayments));

        $rows = [];
        $totals = [
            'count' => 0,
            'member_total' => 0,
            'active_loan_total' => 0,
            'outstanding_total' => 0.0,
        ];

        foreach ($filteredGroups as $g) {
            $groupKey = (int) $g->row_id;
            $loans = $activeLoansByGroup->get($g->row_id) ?? collect();

            $outstanding = 0.0;
            foreach ($loans as $loan) {
                $outstanding += $outstandingByLoan[(int) $loan->loan_row_id] ?? 0.0;
            }

            $rows[] = [
                'group_row_id' => $groupKey,
                'group_code' => (string) ($g->code ?? ''),
                'group_name' => (string) ($g->name ?? ''),
                'village_name' => $g->village_name,
                'ketua_name' => $ketuaMap[$groupKey] ?? null,
                'member_count' => (int) ($activeMemberCounts[$groupKey] ?? 0),
                'active_loan_count' => $loans->count(),
                'savings_total' => 0.0,
                'outstanding_total' => round($outstanding, 2),
                'has_period_activity' => in_array($groupKey, $activeInPeriod, true),
            ];

            $totals['count']++;
            $totals['member_total'] += (int) ($activeMemberCounts[$groupKey] ?? 0);
            $totals['active_loan_total'] += $loans->count();
            $totals['outstanding_total'] += $outstanding;
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
            'rows' => $rows,
            'totals' => [
                'count' => $totals['count'],
                'member_total' => $totals['member_total'],
                'active_loan_total' => $totals['active_loan_total'],
                'outstanding_total' => round($totals['outstanding_total'], 2),
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
     *   rows: list<array<string, mixed>>,
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
            'rows' => [],
            'totals' => [
                'count' => 0,
                'member_total' => 0,
                'active_loan_total' => 0,
                'outstanding_total' => 0.0,
            ],
        ];
    }
}
