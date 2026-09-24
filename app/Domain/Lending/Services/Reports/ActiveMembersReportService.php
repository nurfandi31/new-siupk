<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pemanfaat Aktif — daftar anggota (peminjam individu) yang memiliki pinjaman
 * aktif per tanggal tertentu. Tampilan per individu (legacy_source = 'member_loan')
 * untuk menghindari duplikasi dengan laporan kelompok.
 *
 * Catatan: modul simpanan belum tersedia di aplikasi ini, sehingga total
 * simpanan ditampilkan sebagai 0 dan hanya sisi pinjaman yang dihitung.
 */
final class ActiveMembersReportService
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

        // Ambil anggota aktif yang punya pinjaman aktif (legacy_source = member_loan)
        $members = DB::connection('tenant')
            ->table('members as m')
            ->join('people as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'm.tenant_id')
                    ->on('v.row_id', '=', 'm.organization_unit_row_id');
            })
            ->where('m.tenant_id', $tenantId)
            ->where('m.status', 'active')
            ->selectRaw('m.row_id, m.member_number, m.status, p.full_name, p.national_identity_number as nik, v.row_id as village_row_id, v.name as village_name')
            ->orderBy('v.name')
            ->orderBy('p.full_name')
            ->get();

        if ($members->isEmpty()) {
            return $this->emptyResult($year, $month, $profile);
        }

        $memberRowIds = $members->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        // Pinjaman INDIVIDU aktif per member (via loan_borrowers.member_row_id)
        $activeLoans = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->join('loans as l', function ($j): void {
                $j->on('l.tenant_id', '=', 'b.tenant_id')
                    ->on('l.row_id', '=', 'b.loan_row_id');
            })
            ->where('b.tenant_id', $tenantId)
            ->whereIn('b.member_row_id', $memberRowIds)
            ->whereIn('l.status', self::ACTIVE_LOAN_STATUSES)
            ->where('l.legacy_source', 'member_loan')
            ->selectRaw('b.member_row_id, l.row_id as loan_row_id, l.id as loan_id, l.principal_amount, l.disbursed_at, l.installment_method, l.term_months')
            ->get();

        // Hanya anggota yang punya pinjaman aktif
        $activeMemberRowIds = $activeLoans->pluck('member_row_id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($activeMemberRowIds->isEmpty()) {
            return $this->emptyResult($year, $month, $profile);
        }

        $members = $members->filter(fn ($m) => $activeMemberRowIds->contains((int) $m->row_id))->values();

        $activeLoansByMember = $activeLoans->groupBy('member_row_id');

        // Hitung saldo outstanding per loan (alokasi - realized_pokok)
        $activeLoanIds = $activeLoans->pluck('loan_row_id')->map(fn ($id) => (int) $id)->all();

        $principalRealizedByLoan = DB::connection('tenant')
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

        $outstandingByLoan = [];
        foreach ($activeLoans as $al) {
            $key = (int) $al->loan_row_id;
            $alokasi = (float) $al->principal_amount;
            $realized = (float) ($principalRealizedByLoan[$key] ?? 0);
            $outstandingByLoan[$key] = max(0.0, round($alokasi - $realized, 2));
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

        $tunggakanByLoan = [];
        foreach ($installments as $row) {
            $tunggakanByLoan[(int) $row->loan_row_id] = [
                'pokok' => max(0.0, round((float) $row->tunggakan_pokok, 2)),
                'jasa' => max(0.0, round((float) $row->tunggakan_jasa, 2)),
            ];
        }

        // Cari kelompok asal anggota (jika anggota terdaftar di kelompok tertentu)
        $groupByMember = DB::connection('tenant')
            ->table('group_members as gm')
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'gm.tenant_id')
                    ->on('g.row_id', '=', 'gm.group_row_id');
            })
            ->where('gm.tenant_id', $tenantId)
            ->whereIn('gm.member_row_id', $activeMemberRowIds->all())
            ->where('gm.status', 'active')
            ->whereNull('gm.left_at')
            ->selectRaw('gm.member_row_id, g.row_id as group_row_id, g.code as group_code, g.name as group_name')
            ->get()
            ->groupBy('member_row_id');

        $rows = [];
        $totals = [
            'count' => 0,
            'loan_total' => 0,
            'outstanding_total' => 0.0,
            'tunggakan_pokok_total' => 0.0,
            'tunggakan_jasa_total' => 0.0,
        ];

        foreach ($members as $m) {
            $memberKey = (int) $m->row_id;
            $loans = $activeLoansByMember->get($memberKey) ?? collect();

            $outstanding = 0.0;
            $tunggakanPokok = 0.0;
            $tunggakanJasa = 0.0;
            foreach ($loans as $loan) {
                $loanKey = (int) $loan->loan_row_id;
                $outstanding += $outstandingByLoan[$loanKey] ?? 0.0;
                $tunggakanPokok += $tunggakanByLoan[$loanKey]['pokok'] ?? 0.0;
                $tunggakanJasa += $tunggakanByLoan[$loanKey]['jasa'] ?? 0.0;
            }

            // Ambil kelompok pertama jika ada
            $groupMembership = $groupByMember->get($memberKey);
            $firstGroup = $groupMembership?->first();

            $rows[] = [
                'member_row_id' => $memberKey,
                'nik' => $m->nik ?? null,
                'member_number' => $m->member_number,
                'member_name' => (string) ($m->full_name ?? '—'),
                'village_name' => $m->village_name,
                'group_name' => $firstGroup ? (string) ($firstGroup->group_name ?? null) : null,
                'group_code' => $firstGroup ? ($firstGroup->group_code ?? null) : null,
                'status' => (string) ($m->status ?? 'active'),
                'loan_count' => $loans->count(),
                'savings_total' => 0.0,
                'outstanding_total' => round($outstanding, 2),
                'tunggakan_pokok' => round($tunggakanPokok, 2),
                'tunggakan_jasa' => round($tunggakanJasa, 2),
            ];

            $totals['count']++;
            $totals['loan_total'] += $loans->count();
            $totals['outstanding_total'] += $outstanding;
            $totals['tunggakan_pokok_total'] += $tunggakanPokok;
            $totals['tunggakan_jasa_total'] += $tunggakanJasa;
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
                'loan_total' => $totals['loan_total'],
                'outstanding_total' => round($totals['outstanding_total'], 2),
                'tunggakan_pokok_total' => round($totals['tunggakan_pokok_total'], 2),
                'tunggakan_jasa_total' => round($totals['tunggakan_jasa_total'], 2),
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
                'loan_total' => 0,
                'outstanding_total' => 0.0,
                'tunggakan_pokok_total' => 0.0,
                'tunggakan_jasa_total' => 0.0,
            ],
        ];
    }
}
