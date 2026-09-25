<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Engine hitung kolektabilitas untuk pinjaman KELOMPOK & INDIVIDU.
 *
 * Hasil: 1 baris pinjaman → beberapa nilai turunan:
 *   - alokasi_pokok
 *   - total_target_pokok (s.d. end-of-month)
 *   - total_target_jasa
 *   - total_paid_pokok (s.d. end-of-month)
 *   - total_paid_jasa
 *   - saldo_pokok (= alokasi - paid_pokok, maks 0)
 *   - tunggakan_pokok (= max(0, target_pokok - paid_pokok))
 *   - tunggakan_jasa
 *   - kolek_bulan (float; memakai `round()` utk kelompok, `ceil()` utk individu
 *                  sesuai SIUPK original)
 *   - level_index (0..4) → hasil CollectibilityConfigService::resolveLevelIndex()
 *   - level_nama, ckpn_percentage
 *
 * Penggunaan:
 *   $results = $service->evaluateForDate($year, $month);
 *   // ['group' => [...], 'individual' => [...], 'levels' => [...]]
 *
 * Filter pinjaman yang dipakai sama dengan SIUPK (status A / L / R / H
 * yang overlap dengan tahun laporan). Angsuran mingguan (`principal_frequency`
 * = 'weekly') tidak masuk kolek reguler.
 */
final class CollectibilityCalculatorService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CollectibilityConfigService $config,
    ) {}

    /**
     * @return array{
     *   period_label:string,
     *   end_of_month:string,
     *   levels: list<array{level:int, nama:string, prosentase:float, durasi_bulan:float}>,
     *   group: list<array{
     *     loan_row_id:int, loan_id:int, loan_number:?string, product_code:?string,
     *     principal_amount:float, target_pokok:float, target_jasa:float,
     *     paid_pokok:float, paid_jasa:float, saldo_pokok:float,
     *     tunggakan_pokok:float, tunggakan_jasa:float,
     *     kolek_bulan:float, level:int, level_nama:string, ckpn_percentage:float
     *   }>,
     *   individual: list<array{
     *     loan_row_id:int, loan_id:int, loan_number:?string, product_code:?string,
     *     member_name:?string, member_number:?string, village_name:?string,
     *     principal_amount:float, target_pokok:float, target_jasa:float,
     *     paid_pokok:float, paid_jasa:float, saldo_pokok:float,
     *     tunggakan_pokok:float, tunggakan_jasa:float,
     *     kolek_bulan:float, level:int, level_nama:string, ckpn_percentage:float
     *   }>,
     *   totals: array{
     *     group: array<string, float>,
     *     individual: array<string, float>
     *   }
     * }
     */
    public function evaluateForDate(int $year, int $month, ?string $productCode = null, ?string $borrowerScope = null): array
    {
        $tenantId = $this->context->id();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        $endOfYear = sprintf('%04d-01-01', $year);
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = ($monthNames[$month] ?? "Bulan {$month}")." {$year}";

        $group = $this->evaluateGroup($tenantId, $endOfMonth, $endOfYear, $productCode, $borrowerScope);
        $individual = $this->evaluateIndividual($tenantId, $endOfMonth, $endOfYear, $productCode);

        return [
            'period_label' => $periodLabel,
            'end_of_month' => $endOfMonth,
            'levels' => $this->buildLevelsSummary(),
            'group' => $group['rows'],
            'individual' => $individual['rows'],
            'totals' => [
                'group' => $group['totals'],
                'individual' => $individual['totals'],
            ],
        ];
    }

    // ---------- KELOMPOK ----------

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    private function evaluateGroup(
        int $tenantId,
        string $endOfMonth,
        string $endOfYear,
        ?string $productCode,
        ?string $borrowerScope,
    ): array {
        $rows = $this->loadLoans($tenantId, $endOfMonth, $endOfYear, $productCode, $borrowerScope, 'group_loan');
        $groupData = $this->joinGroupContext($tenantId, $rows);

        $installments = $this->loadInstallments($tenantId, array_keys($groupData));
        $allocations = $this->loadAllocations($tenantId, array_keys($groupData), $endOfMonth);

        $results = [];
        $totals = $this->emptyTotals();

        foreach ($groupData as $rowId => $meta) {
            $loanRowId = (int) $rowId;
            $loanInsts = $installments[$loanRowId] ?? [];
            $loanAllocs = $allocations[$loanRowId] ?? [];

            $computed = $this->computeForLoan($meta, $loanInsts, $loanAllocs, $endOfMonth, 'group');

            $results[] = array_merge($meta, $computed);
            $this->accumulateTotals($totals, $computed);
        }

        return ['rows' => $results, 'totals' => $totals];
    }

    // ---------- INDIVIDU ----------

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    private function evaluateIndividual(
        int $tenantId,
        string $endOfMonth,
        string $endOfYear,
        ?string $productCode,
    ): array {
        $rows = $this->loadLoans($tenantId, $endOfMonth, $endOfYear, $productCode, 'member', 'member_loan');
        $individualData = $this->joinIndividualContext($tenantId, $rows);

        $installments = $this->loadInstallments($tenantId, array_keys($individualData));
        $allocations = $this->loadAllocations($tenantId, array_keys($individualData), $endOfMonth);

        $results = [];
        $totals = $this->emptyTotals();

        foreach ($individualData as $rowId => $meta) {
            $loanRowId = (int) $rowId;
            $loanInsts = $installments[$loanRowId] ?? [];
            $loanAllocs = $allocations[$loanRowId] ?? [];

            $computed = $this->computeForLoan($meta, $loanInsts, $loanAllocs, $endOfMonth, 'individual');

            $results[] = array_merge($meta, $computed);
            $this->accumulateTotals($totals, $computed);
        }

        return ['rows' => $results, 'totals' => $totals];
    }

    // ---------- QUERIES ----------

    /**
     * @return list<object>
     */
    private function loadLoans(
        int $tenantId,
        string $endOfMonth,
        string $endOfYear,
        ?string $productCode,
        ?string $borrowerScope,
        string $legacySource,
    ): array {
        $query = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_products as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'l.tenant_id')
                    ->on('p.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('l.legacy_source', $legacySource)
            ->where(function ($q) use ($endOfMonth, $endOfYear): void {
                // Status aktif (A / disbursed): cair s.d. end-of-month
                $q->where(function ($a) use ($endOfMonth): void {
                    $a->whereIn('l.status', ['active', 'disbursed'])
                        ->whereNotNull('l.disbursed_at')
                        ->where('l.disbursed_at', '<=', $endOfMonth);
                })
                // Status L / R / H di tahun berjalan
                    ->orWhereIn('l.status', ['completed', 'rescheduled', 'written_off'])
                    ->where(function ($b) use ($endOfMonth, $endOfYear): void {
                        $b->whereNotNull('l.completed_at')
                            ->where('l.completed_at', '>=', $endOfYear)
                            ->where('l.completed_at', '<=', $endOfMonth);
                    });
            })
            // Exclude angsuran mingguan (mirror `sistem_angsuran != '12'` di pacuan)
            ->where(function ($w): void {
                $w->whereNull('l.principal_frequency')
                    ->orWhereNotIn('l.principal_frequency', ['weekly']);
            });

        if ($productCode !== null && $productCode !== 'all') {
            $query->where('p.code', $productCode);
        }

        if ($borrowerScope === 'group') {
            $query->where('l.legacy_source', 'group_loan');
        } elseif ($borrowerScope === 'member') {
            $query->where('l.legacy_source', 'member_loan');
        }

        return $query
            ->orderBy('l.id')
            ->get([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.principal_amount',
                'l.interest_rate',
                'l.term_months',
                'l.disbursed_at',
                'l.completed_at',
                'l.status',
                'p.code as product_code',
                'p.name as product_name',
                'p.default_interest_rate',
                'p.default_term_months',
            ])
            ->all();
    }

    /**
     * @param  list<object>  $loans
     * @return array<int, array<string, mixed>> key = loan_row_id
     */
    private function joinGroupContext(int $tenantId, array $loans): array
    {
        if ($loans === []) {
            return [];
        }
        $loanIds = array_map(static fn ($r): int => (int) $r->row_id, $loans);

        $borrowers = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->where('b.tenant_id', $tenantId)
            ->whereIn('b.loan_row_id', $loanIds)
            ->get(['b.loan_row_id', 'g.name as group_name', 'v.name as village_name'])
            ->keyBy('loan_row_id');

        $out = [];
        foreach ($loans as $loan) {
            $b = $borrowers->get($loan->row_id);
            $alokasi = (float) $loan->principal_amount;
            $serviceRate = (float) ($loan->default_interest_rate ?? 0);
            $term = (int) ($loan->term_months ?? 0);

            $out[(int) $loan->row_id] = [
                'loan_row_id' => (int) $loan->row_id,
                'loan_id' => (int) $loan->id,
                'loan_number' => $loan->loan_number,
                'product_code' => $loan->product_code,
                'product_name' => $loan->product_name,
                'principal_amount' => $alokasi,
                'service_rate_total' => $serviceRate,
                'term_months' => $term,
                'disbursed_at' => $loan->disbursed_at,
                'completed_at' => $loan->completed_at,
                'status' => $loan->status,
                'group_name' => $b->group_name ?? null,
                'village_name' => $b->village_name ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<object>  $loans
     * @return array<int, array<string, mixed>>
     */
    private function joinIndividualContext(int $tenantId, array $loans): array
    {
        if ($loans === []) {
            return [];
        }
        $loanIds = array_map(static fn ($r): int => (int) $r->row_id, $loans);

        $borrowers = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->leftJoin('members as m', function ($j): void {
                $j->on('m.tenant_id', '=', 'b.tenant_id')
                    ->on('m.row_id', '=', 'b.member_row_id');
            })
            ->leftJoin('people as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'm.tenant_id')
                    ->on('v.row_id', '=', 'm.organization_unit_row_id');
            })
            ->where('b.tenant_id', $tenantId)
            ->whereIn('b.loan_row_id', $loanIds)
            ->get(['b.loan_row_id', 'p.full_name as member_name', 'm.member_number', 'v.name as village_name'])
            ->keyBy('loan_row_id');

        $out = [];
        foreach ($loans as $loan) {
            $b = $borrowers->get($loan->row_id);
            $alokasi = (float) $loan->principal_amount;
            $serviceRate = (float) ($loan->default_interest_rate ?? 0);
            $term = (int) ($loan->term_months ?? 0);

            $out[(int) $loan->row_id] = [
                'loan_row_id' => (int) $loan->row_id,
                'loan_id' => (int) $loan->id,
                'loan_number' => $loan->loan_number,
                'product_code' => $loan->product_code,
                'product_name' => $loan->product_name,
                'principal_amount' => $alokasi,
                'service_rate_total' => $serviceRate,
                'term_months' => $term,
                'disbursed_at' => $loan->disbursed_at,
                'completed_at' => $loan->completed_at,
                'status' => $loan->status,
                'member_name' => $b->member_name ?? null,
                'member_number' => $b->member_number ?? null,
                'village_name' => $b->village_name ?? null,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, list<object>>
     */
    private function loadInstallments(int $tenantId, array $loanRowIds): array
    {
        if ($loanRowIds === []) {
            return [];
        }

        return DB::connection('tenant')
            ->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->whereIn('loan_row_id', $loanRowIds)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->get(['loan_row_id', 'installment_number', 'due_date', 'principal_due', 'interest_due', 'principal_paid', 'interest_paid'])
            ->groupBy('loan_row_id')
            ->all();
    }

    /**
     * @return array<int, Collection<int, object>>
     */
    private function loadAllocations(int $tenantId, array $loanRowIds, string $endOfMonth): array
    {
        if ($loanRowIds === []) {
            return [];
        }

        return DB::connection('tenant')
            ->table('loan_payment_allocations as a')
            ->join('loan_payments as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'a.tenant_id')
                    ->on('p.row_id', '=', 'a.payment_row_id');
            })
            ->where('a.tenant_id', $tenantId)
            ->whereIn('p.loan_row_id', $loanRowIds)
            ->where('p.paid_at', '<=', $endOfMonth)
            ->get(['p.loan_row_id', 'p.paid_at', 'a.component', 'a.amount'])
            ->groupBy('loan_row_id')
            ->all();
    }

    // ---------- KALKULASI PER PINJAMAN ----------

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<object>  $insts
     * @param  iterable<object>  $allocs
     */
    private function computeForLoan(array $meta, array $insts, iterable $allocs, string $endOfMonth, string $kind): array
    {
        $alokasi = (float) ($meta['principal_amount'] ?? 0);
        $serviceRateTotal = (float) ($meta['service_rate_total'] ?? 0);
        $term = (int) ($meta['term_months'] ?? 0);
        $disbursedAt = $meta['disbursed_at'] ?? null;
        $completedAt = $meta['completed_at'] ?? null;

        // Hitung target_kumulatif_pokok & jasa sampai end-of-month
        $targetPokok = 0.0;
        $targetJasa = 0.0;
        $maxInstallmentNumberDue = 0;
        foreach ($insts as $inst) {
            $dueDate = (string) $inst->due_date;
            if ($dueDate !== '' && $dueDate <= $endOfMonth) {
                $targetPokok += (float) $inst->principal_due;
                $targetJasa += (float) $inst->interest_due;
                $maxInstallmentNumberDue = max($maxInstallmentNumberDue, (int) $inst->installment_number);
            }
        }

        $paidPokok = 0.0;
        $paidJasa = 0.0;
        foreach ($allocs as $alloc) {
            $component = (string) $alloc->component;
            if ($component === 'principal') {
                $paidPokok += (float) $alloc->amount;
            } elseif ($component === 'interest') {
                $paidJasa += (float) $alloc->amount;
            }
        }

        $saldoPokok = max(0.0, round($alokasi - $paidPokok, 2));
        $tunggakanPokok = max(0.0, round($targetPokok - $paidPokok, 2));
        $tunggakanJasa = max(0.0, round($targetJasa - $paidJasa, 2));

        // Angsuran bulanan rata-rata (untuk bagi tunggakan)
        $avgMonthly = $term > 0 ? ($alokasi / $term) : 1.0;
        if ($avgMonthly <= 0) {
            $avgMonthly = 1.0;
        }

        // Selisih bulan s.d. end-of-month (mirror SIUPK)
        $kolekBulan = 0.0;
        if ($disbursedAt && $tunggakanPokok > 0) {
            $tglCair = CarbonImmutable::parse($disbursedAt);
            $tglKondisi = CarbonImmutable::parse($endOfMonth);

            $selisihTahun = ((int) $tglKondisi->format('Y') - (int) $tglCair->format('Y')) * 12;
            $selisihBulan = (int) $tglKondisi->format('n') - (int) $tglCair->format('n');
            $selisih = $selisihBulan + $selisihTahun;

            $bagianTunggakan = $tunggakanPokok / $avgMonthly;

            // Mirror SIUPK: kelompok=round, individu=ceil
            $bukanBagi = (float) $bagianTunggakan + ((float) $selisih - (float) $maxInstallmentNumberDue);
            $kolekBulan = $kind === 'group' ? (float) round($bukanBagi) : (float) ceil($bukanBagi);
            if ($kolekBulan < 0.0) {
                $kolekBulan = 0.0;
            }
        }

        // Tentukan tingkat kolek
        $levelIndex = $this->config->resolveLevelIndex($kolekBulan);
        $row = $this->config->getLevel($levelIndex);
        $levelNama = $row['nama'] ?? ($this->config->activeOnly()[0]['nama'] ?? 'Lancar');
        $ckpnPct = $this->config->ckpnPercentage($levelIndex);

        // Jika sudah lunas/reschedule/hapus sebelum end-of-month → nol-kan tunggakan
        // (mirror `if (tgl_lunas <= tgl_kondisi && in_array(status, ['L','R','H']))` di SIUPK)
        if ($completedAt && $completedAt <= $endOfMonth) {
            $tunggakanPokok = 0.0;
            $tunggakanJasa = 0.0;
            $saldoPokok = 0.0;
            $kolekBulan = 0.0;
            // Kolek → Lancar (index 0)
            $levelIndex = 0;
            $row = $this->config->getLevel(0);
            $levelNama = $row['nama'] ?? 'Lancar';
            $ckpnPct = $this->config->ckpnPercentage(0);
        }

        return [
            'target_pokok' => round($targetPokok, 2),
            'target_jasa' => round($targetJasa, 2),
            'paid_pokok' => round($paidPokok, 2),
            'paid_jasa' => round($paidJasa, 2),
            'saldo_pokok' => round($saldoPokok, 2),
            'tunggakan_pokok' => round($tunggakanPokok, 2),
            'tunggakan_jasa' => round($tunggakanJasa, 2),
            'kolek_bulan' => $kolekBulan,
            'level' => $levelIndex + 1, // UI 1-based
            'level_index' => $levelIndex,
            'level_nama' => $levelNama,
            'ckpn_percentage' => $ckpnPct,
            'ckpn_nominal' => round($saldoPokok * ($ckpnPct / 100), 2),
        ];
    }

    // ---------- HELPERS ----------

    /**
     * @return array<string, float>
     */
    private function emptyTotals(): array
    {
        return [
            'alokasi' => 0.0,
            'target_pokok' => 0.0,
            'target_jasa' => 0.0,
            'paid_pokok' => 0.0,
            'paid_jasa' => 0.0,
            'saldo_pokok' => 0.0,
            'tunggakan_pokok' => 0.0,
            'tunggakan_jasa' => 0.0,
            'ckpn_nominal' => 0.0,
        ];
    }

    /**
     * @param  array<string, float>  $totals
     * @param  array<string, mixed>  $computed
     */
    private function accumulateTotals(array &$totals, array $computed): void
    {
        $map = [
            'alokasi' => 'principal_amount',
            'target_pokok' => 'target_pokok',
            'target_jasa' => 'target_jasa',
            'paid_pokok' => 'paid_pokok',
            'paid_jasa' => 'paid_jasa',
            'saldo_pokok' => 'saldo_pokok',
            'tunggakan_pokok' => 'tunggakan_pokok',
            'tunggakan_jasa' => 'tunggakan_jasa',
            'ckpn_nominal' => 'ckpn_nominal',
        ];
        foreach ($map as $key => $source) {
            $totals[$key] += (float) ($computed[$source] ?? 0);
        }
    }

    /**
     * @return list<array{level:int, nama:string, prosentase:float, durasi_bulan:float}>
     */
    private function buildLevelsSummary(): array
    {
        $rows = $this->config->summaryForUi();
        $out = [];
        foreach ($rows as $row) {
            if (! $row['aktif']) {
                continue;
            }
            $out[] = [
                'level' => $row['level'],
                'nama' => $row['nama'],
                'prosentase' => $row['prosentase'],
                'durasi_bulan' => $row['durasi_bulan'],
            ];
        }

        return $out;
    }
}
