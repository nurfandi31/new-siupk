<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Lending\Services\CollectibilityConfigService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Kolektibilitas & Cadangan Kerugian Penurunan Nilai (CKPN).
 *
 * VERSI REFACTOR: pakai `CollectibilityConfigService` agar kolek 3 atau 5
 * tingkat berasal dari konfigurasi lembaga (`organization_profiles.collectibility_rules`).
 * Sebelum refactor: kolek hardcoded 3 tingkat (overdue <= 3 = Lancar, <= 5 = Diragukan, else Macet).
 */
final class CollectibilityReportService
{
    private const ACTIVE = ['active', 'disbursed'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly CollectibilityConfigService $config,
    ) {}

    /**
     * Laporan Kolektabilitas per Desa (mencakup pinjaman kelompok + individu).
     *
     * @return array{
     *   year:int, month:int, period_label:string,
     *   identity:array{legal_name:string, short_name:?string},
     *   levels:list<array{level:int, nama:string, prosentase:float, bucket_key:string}>,
     *   products:list<array<string, mixed>>,
     *   totals:array<string, float>
     * }
     */
    public function buildDesa(int $year, int $month, ?string $productCode = null, ?string $borrowerScope = null): array
    {
        $tenantId = $this->context->id();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        $profile = OrganizationProfile::query()->first();

        $productsQuery = DB::connection('tenant')
            ->table('loan_products')
            ->where('tenant_id', $tenantId)
            ->orderBy('code');

        if ($productCode !== null && $productCode !== 'all') {
            $productsQuery->where('code', $productCode);
        }

        $products = $productsQuery->get(['row_id', 'code', 'name']);

        $loans = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('members as m', function ($j): void {
                $j->on('m.tenant_id', '=', 'b.tenant_id')
                    ->on('m.row_id', '=', 'b.member_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->leftJoin('organization_units as mv', function ($j): void {
                $j->on('mv.tenant_id', '=', 'm.tenant_id')
                    ->on('mv.row_id', '=', 'm.organization_unit_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->when($borrowerScope === 'group', function ($q): void {
                $q->where(function ($w): void {
                    $w->whereNull('l.legacy_source')
                        ->orWhere('l.legacy_source', 'group_loan');
                });
            })
            ->when($borrowerScope === 'member', function ($q): void {
                $q->where('l.legacy_source', 'member_loan');
            })
            ->orderBy('v.name')
            ->orderBy('l.id')
            ->get([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.loan_product_row_id',
                'l.disbursed_at',
                'l.principal_amount',
                'l.legacy_source',
                'v.name as village_name',
                'mv.name as member_village_name',
            ]);

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->get(['loan_row_id', 'installment_number', 'due_date', 'principal_due', 'interest_due']);

        $allocations = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_payment_allocations as a')
                ->join('loan_payments as p', function ($j): void {
                    $j->on('p.tenant_id', '=', 'a.tenant_id')
                        ->on('p.row_id', '=', 'a.payment_row_id');
                })
                ->where('a.tenant_id', $tenantId)
                ->whereIn('p.loan_row_id', $loanRowIds)
                ->where('p.paid_at', '<=', $endOfMonth)
                ->get(['p.loan_row_id', 'a.component', 'a.amount']);

        $instByLoan = $installments->groupBy('loan_row_id');
        $allocByLoan = $allocations->groupBy('loan_row_id');

        $activeLevels = $this->config->activeOnly();
        if ($activeLevels === []) {
            $activeLevels = [['nama' => 'Lancar', 'prosentase' => '0.5', 'durasi' => '999', 'satuan' => 'bulan']];
        }
        $levelKeyByIdx = $this->buildLevelBucketKeys($activeLevels);
        $levelsPayload = array_map(
            static fn (array $row, int $idx): array => [
                'level' => $idx + 1,
                'nama' => (string) ($row['nama'] ?? ''),
                'prosentase' => (float) ($row['prosentase'] ?? 0),
                'bucket_key' => $levelKeyByIdx[$idx],
            ],
            $activeLevels,
            array_keys($activeLevels),
        );

        $productBlocks = [];
        $grandTotals = $this->buildEmptyTotals($activeLevels);

        foreach ($products as $prod) {
            $prodLoans = $loans->where('loan_product_row_id', $prod->row_id);
            if ($prodLoans->isEmpty()) {
                continue;
            }

            $villagesMap = [];
            $prodTotals = $this->buildEmptyTotals($activeLevels);

            foreach ($prodLoans as $loan) {
                $effectiveVillage = ($loan->legacy_source ?? null) === 'member_loan'
                    ? ($loan->member_village_name ?? null)
                    : ($loan->village_name ?? null);
                $vKey = (string) ($effectiveVillage ?? 'Lain-lain');
                if (! isset($villagesMap[$vKey])) {
                    $villagesMap[$vKey] = array_merge(
                        ['village_name' => $vKey],
                        $this->buildEmptyTotals($activeLevels),
                    );
                }

                $alokasi = (float) $loan->principal_amount;
                $loanInsts = $instByLoan->get($loan->row_id) ?? collect();
                $loanAllocs = $allocByLoan->get($loan->row_id) ?? collect();

                [$targetPokok, $targetJasa, $maxInstNumber] = $this->sumTargetsDue($loanInsts, $endOfMonth);
                [$paidPokok, $paidJasa] = $this->sumPaidComponents($loanAllocs);

                $saldoPokok = max(0.0, round($alokasi - $paidPokok, 2));
                $tunggakanPokok = max(0.0, round($targetPokok - $paidPokok, 2));
                $tunggakanJasa = max(0.0, round($targetJasa - $paidJasa, 2));

                $levelIndex = $this->resolveKolekLevel(
                    $tunggakanPokok,
                    $alokasi,
                    $loan->disbursed_at,
                    null,
                    $endOfMonth,
                    $maxInstNumber,
                    $activeLevels,
                );
                $bucket = $levelKeyByIdx[$levelIndex];

                $villagesMap[$vKey]['alokasi'] += $alokasi;
                $villagesMap[$vKey]['saldo'] += $saldoPokok;
                $villagesMap[$vKey]['tunggakan_pokok'] += $tunggakanPokok;
                $villagesMap[$vKey]['tunggakan_jasa'] += $tunggakanJasa;
                $villagesMap[$vKey][$bucket] += $saldoPokok;

                $prodTotals['alokasi'] += $alokasi;
                $prodTotals['saldo'] += $saldoPokok;
                $prodTotals['tunggakan_pokok'] += $tunggakanPokok;
                $prodTotals['tunggakan_jasa'] += $tunggakanJasa;
                $prodTotals[$bucket] += $saldoPokok;
            }

            ksort($villagesMap);

            $productBlocks[] = [
                'product_code' => (string) $prod->code,
                'product_name' => (string) $prod->name,
                'villages' => array_values($villagesMap),
                'totals' => $prodTotals,
            ];

            foreach ($prodTotals as $k => $val) {
                $grandTotals[$k] = ($grandTotals[$k] ?? 0) + (is_numeric($val) ? (float) $val : 0);
            }
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
            'levels' => $levelsPayload,
            'products' => $productBlocks,
            'totals' => $grandTotals,
        ];
    }

    /**
     * Cadangan Penghapusan Piutang (CKPN). Bobot persentase CKPN mengikuti
     * `collectibility_rules.prosentase` per tingkat dari konfigurasi lembaga.
     *
     * @return array{
     *   year:int, month:int, period_label:string,
     *   identity:array{legal_name:string, short_name:?string},
     *   levels:list<array<string, mixed>>,
     *   products:list<array<string, mixed>>,
     *   totals:array<string, float>
     * }
     */
    public function buildCadangan(int $year, int $month, ?string $productCode = null, ?string $borrowerScope = null): array
    {
        $data = $this->buildDesa($year, $month, $productCode, $borrowerScope);

        $levels = $data['levels'] ?? [];
        $totalCkpn = 0.0;

        foreach ($levels as $level) {
            $bucket = $level['bucket_key'];
            $pct = (float) $level['prosentase'];

            $ckpn = round(((float) ($data['totals'][$bucket] ?? 0)) * ($pct / 100), 2);
            $data['totals']["ckpn_{$level['level']}"] = $ckpn;
            $totalCkpn += $ckpn;

            foreach ($data['products'] as &$prod) {
                $prod['totals']["ckpn_{$level['level']}"] = round(((float) ($prod['totals'][$bucket] ?? 0)) * ($pct / 100), 2);
            }
            unset($prod);
        }
        $data['totals']['total_ckpn'] = round($totalCkpn, 2);

        foreach ($data['products'] as &$prod) {
            $prodCkpnTotal = 0.0;
            foreach ($levels as $level) {
                $prodCkpnTotal += (float) ($prod['totals']["ckpn_{$level['level']}"] ?? 0);
            }
            $prod['totals']['total_ckpn'] = round($prodCkpnTotal, 2);

            foreach ($prod['villages'] as &$village) {
                $villageCkpn = 0.0;
                foreach ($levels as $level) {
                    $pct = (float) $level['prosentase'];
                    $ckpn = round(((float) ($village[$level['bucket_key']] ?? 0)) * ($pct / 100), 2);
                    $village["ckpn_{$level['level']}"] = $ckpn;
                    $villageCkpn += $ckpn;
                }
                $village['total_ckpn'] = round($villageCkpn, 2);
            }
            unset($village);
        }
        unset($prod);

        return $data;
    }

    /**
     * Laporan Kolektabilitas khusus pinjaman INDIVIDU (per pinjaman, dikelompokkan per desa anggota).
     *
     * @return array{
     *   year:int, month:int, period_label:string,
     *   identity:array{legal_name:string, short_name:?string},
     *   levels:list<array<string, mixed>>,
     *   products:list<array<string, mixed>>,
     *   totals:array<string, float>
     * }
     */
    public function buildIndividu(int $year, int $month, ?string $productCode = null): array
    {
        $tenantId = $this->context->id();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        $profile = OrganizationProfile::query()->first();

        $productsQuery = DB::connection('tenant')
            ->table('loan_products')
            ->where('tenant_id', $tenantId)
            ->orderBy('code');

        if ($productCode !== null && $productCode !== 'all') {
            $productsQuery->where('code', $productCode);
        }

        $products = $productsQuery->get(['row_id', 'code', 'name']);

        $loans = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
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
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->where('l.legacy_source', 'member_loan')
            ->orderBy('mv.name')
            ->orderBy('p.full_name')
            ->orderBy('l.id')
            ->get([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.loan_product_row_id',
                'l.disbursed_at',
                'l.principal_amount',
                'm.row_id as member_row_id',
                'm.member_number',
                'p.full_name as member_name',
                'p.national_identity_number as nik',
                'mv.row_id as village_row_id',
                'mv.name as village_name',
            ]);

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->get(['loan_row_id', 'installment_number', 'due_date', 'principal_due', 'interest_due']);

        $payments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_payment_allocations as a')
                ->join('loan_payments as p', function ($j): void {
                    $j->on('p.tenant_id', '=', 'a.tenant_id')
                        ->on('p.row_id', '=', 'a.payment_row_id');
                })
                ->where('a.tenant_id', $tenantId)
                ->whereIn('p.loan_row_id', $loanRowIds)
                ->get(['p.loan_row_id', 'p.paid_at', 'a.component', 'a.amount']);

        $instByLoan = $installments->groupBy('loan_row_id');
        $payByLoan = $payments->groupBy('loan_row_id');

        $activeLevels = $this->config->activeOnly();
        if ($activeLevels === []) {
            $activeLevels = [['nama' => 'Lancar', 'prosentase' => '0.5', 'durasi' => '999', 'satuan' => 'bulan']];
        }
        $levelKeyByIdx = $this->buildLevelBucketKeys($activeLevels);
        $levelsPayload = array_map(
            static fn (array $row, int $idx): array => [
                'level' => $idx + 1,
                'nama' => (string) ($row['nama'] ?? ''),
                'prosentase' => (float) ($row['prosentase'] ?? 0),
                'bucket_key' => $levelKeyByIdx[$idx],
            ],
            $activeLevels,
            array_keys($activeLevels),
        );

        $productBlocks = [];
        $grandTotals = $this->buildEmptyTotals($activeLevels);

        foreach ($products as $prod) {
            $prodLoans = $loans->where('loan_product_row_id', $prod->row_id);
            if ($prodLoans->isEmpty()) {
                continue;
            }

            $villagesMap = [];
            $prodTotals = $this->buildEmptyTotals($activeLevels);

            foreach ($prodLoans as $loan) {
                $vKey = (string) ($loan->village_name ?? 'Lain-lain');
                if (! isset($villagesMap[$vKey])) {
                    $villagesMap[$vKey] = [
                        'village_name' => $vKey,
                        'loans' => [],
                        'subtotal' => $this->buildEmptyTotals($activeLevels),
                    ];
                }

                $alokasi = (float) $loan->principal_amount;
                $loanInsts = $instByLoan->get($loan->row_id) ?? collect();
                $loanPays = $payByLoan->get($loan->row_id) ?? collect();

                $sumPokok = 0.0;
                $sumJasa = 0.0;
                $totalPokokDue = 0.0;
                $totalJasaDue = 0.0;
                $maxInstNumber = 0;

                foreach ($loanInsts as $inst) {
                    $totalPokokDue += (float) $inst->principal_due;
                    $totalJasaDue += (float) $inst->interest_due;
                    $maxInstNumber = max($maxInstNumber, (int) $inst->installment_number);
                    $dueDate = (string) $inst->due_date;
                    if ($dueDate > $endOfMonth) {
                        continue;
                    }
                    $sumPokok += (float) $inst->principal_due;
                    $sumJasa += (float) $inst->interest_due;
                }

                $paidPokok = 0.0;
                $paidJasa = 0.0;
                foreach ($loanPays as $pay) {
                    $paidAt = (string) $pay->paid_at;
                    if ($paidAt > $endOfMonth) {
                        continue;
                    }
                    if ((string) $pay->component === 'principal') {
                        $paidPokok += (float) $pay->amount;
                    } elseif ((string) $pay->component === 'interest') {
                        $paidJasa += (float) $pay->amount;
                    }
                }

                $saldo = max(0.0, round($totalPokokDue - $paidPokok, 2));
                $tunggakanPokok = max(0.0, round($sumPokok - $paidPokok, 2));
                $tunggakanJasa = max(0.0, round($sumJasa - $paidJasa, 2));

                $levelIndex = $this->resolveKolekLevel(
                    $tunggakanPokok,
                    $alokasi,
                    $loan->disbursed_at,
                    null,
                    $endOfMonth,
                    $maxInstNumber,
                    $activeLevels,
                );
                $bucket = $levelKeyByIdx[$levelIndex];
                $kolekNama = (string) ($activeLevels[$levelIndex]['nama'] ?? 'Lancar');

                $loanData = [
                    'loan_id' => (int) $loan->id,
                    'loan_number' => (string) $loan->loan_number,
                    'member_name' => (string) ($loan->member_name ?? '—'),
                    'member_number' => $loan->member_number,
                    'nik' => $loan->nik,
                    'disbursed_at' => (string) $loan->disbursed_at,
                    'alokasi' => $alokasi,
                    'saldo' => $saldo,
                    'tunggakan_pokok' => $tunggakanPokok,
                    'tunggakan_jasa' => $tunggakanJasa,
                    'kolek' => $levelIndex + 1,
                    'kolek_nama' => $kolekNama,
                ];

                $villagesMap[$vKey]['loans'][] = $loanData;

                $sub = &$villagesMap[$vKey]['subtotal'];
                $sub['alokasi'] += $alokasi;
                $sub['saldo'] += $saldo;
                $sub['tunggakan_pokok'] += $tunggakanPokok;
                $sub['tunggakan_jasa'] += $tunggakanJasa;
                $sub[$bucket] = ($sub[$bucket] ?? 0) + $saldo;
                $sub['peminjam_count'] = ($sub['peminjam_count'] ?? 0) + 1;
                unset($sub);

                $prodTotals['alokasi'] += $alokasi;
                $prodTotals['saldo'] += $saldo;
                $prodTotals['tunggakan_pokok'] += $tunggakanPokok;
                $prodTotals['tunggakan_jasa'] += $tunggakanJasa;
                $prodTotals[$bucket] += $saldo;
                $prodTotals['peminjam_count'] = ($prodTotals['peminjam_count'] ?? 0) + 1;
            }

            ksort($villagesMap);

            $productBlocks[] = [
                'product_code' => (string) $prod->code,
                'product_name' => (string) $prod->name,
                'villages' => array_values($villagesMap),
                'totals' => $prodTotals,
            ];

            foreach ($prodTotals as $k => $val) {
                $grandTotals[$k] = ($grandTotals[$k] ?? 0) + (is_numeric($val) ? (float) $val : 0);
            }
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
            'levels' => $levelsPayload,
            'products' => $productBlocks,
            'totals' => $grandTotals,
        ];
    }

    /**
     * Laporan Kolektabilitas per Desa KHUSUS pinjaman INDIVIDU.
     * Rekap per desa (tanpa breakdown per anggota) seperti `kolek_desa_individu.blade.php`
     * di SIUPK original. Hanya `legacy_source='member_loan'`.
     *
     * @return array{
     *   year:int, month:int, period_label:string,
     *   identity:array{legal_name:string, short_name:?string},
     *   levels:list<array{level:int, nama:string, prosentase:float, bucket_key:string}>,
     *   products:list<array<string, mixed>>,
     *   totals:array<string, float>
     * }
     */
    public function buildDesaIndividu(int $year, int $month, ?string $productCode = null): array
    {
        $tenantId = $this->context->id();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        $profile = OrganizationProfile::query()->first();

        $productsQuery = DB::connection('tenant')
            ->table('loan_products')
            ->where('tenant_id', $tenantId)
            ->orderBy('code');

        if ($productCode !== null && $productCode !== 'all') {
            $productsQuery->where('code', $productCode);
        }

        $products = $productsQuery->get(['row_id', 'code', 'name']);

        $loans = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('members as m', function ($j): void {
                $j->on('m.tenant_id', '=', 'b.tenant_id')
                    ->on('m.row_id', '=', 'b.member_row_id');
            })
            ->leftJoin('organization_units as mv', function ($j): void {
                $j->on('mv.tenant_id', '=', 'm.tenant_id')
                    ->on('mv.row_id', '=', 'm.organization_unit_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->where('l.legacy_source', 'member_loan')
            ->orderBy('mv.name')
            ->orderBy('l.id')
            ->get([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.loan_product_row_id',
                'l.disbursed_at',
                'l.principal_amount',
                'mv.row_id as village_row_id',
                'mv.name as village_name',
            ]);

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->get(['loan_row_id', 'installment_number', 'due_date', 'principal_due', 'interest_due']);

        $payments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_payment_allocations as a')
                ->join('loan_payments as p', function ($j): void {
                    $j->on('p.tenant_id', '=', 'a.tenant_id')
                        ->on('p.row_id', '=', 'a.payment_row_id');
                })
                ->where('a.tenant_id', $tenantId)
                ->whereIn('p.loan_row_id', $loanRowIds)
                ->get(['p.loan_row_id', 'p.paid_at', 'a.component', 'a.amount']);

        $instByLoan = $installments->groupBy('loan_row_id');
        $payByLoan = $payments->groupBy('loan_row_id');

        $activeLevels = $this->config->activeOnly();
        if ($activeLevels === []) {
            $activeLevels = [['nama' => 'Lancar', 'prosentase' => '0.5', 'durasi' => '999', 'satuan' => 'bulan']];
        }
        $levelKeyByIdx = $this->buildLevelBucketKeys($activeLevels);
        $levelsPayload = array_map(
            static fn (array $row, int $idx): array => [
                'level' => $idx + 1,
                'nama' => (string) ($row['nama'] ?? ''),
                'prosentase' => (float) ($row['prosentase'] ?? 0),
                'bucket_key' => $levelKeyByIdx[$idx],
            ],
            $activeLevels,
            array_keys($activeLevels),
        );

        $productBlocks = [];
        $grandTotals = $this->buildEmptyTotals($activeLevels);

        foreach ($products as $prod) {
            $prodLoans = $loans->where('loan_product_row_id', $prod->row_id);
            if ($prodLoans->isEmpty()) {
                continue;
            }

            $villagesMap = [];
            $prodTotals = $this->buildEmptyTotals($activeLevels);

            foreach ($prodLoans as $loan) {
                $vKey = (string) ($loan->village_name ?? 'Lain-lain');
                if (! isset($villagesMap[$vKey])) {
                    $villagesMap[$vKey] = [
                        'village_name' => $vKey,
                        'village_row_id' => $loan->village_row_id !== null ? (int) $loan->village_row_id : null,
                        'subtotal' => $this->buildEmptyTotals($activeLevels),
                    ];
                }

                $alokasi = (float) $loan->principal_amount;
                $loanInsts = $instByLoan->get($loan->row_id) ?? collect();
                $loanPays = $payByLoan->get($loan->row_id) ?? collect();

                [$sumPokok, $sumJasa, $maxInstNumber] = $this->sumTargetsDue($loanInsts, $endOfMonth);
                [$paidPokok, $paidJasa] = $this->sumPaidComponents($loanPays, $endOfMonth);

                $saldo = max(0.0, round($alokasi - $paidPokok, 2));
                $tunggakanPokok = max(0.0, round($sumPokok - $paidPokok, 2));
                $tunggakanJasa = max(0.0, round($sumJasa - $paidJasa, 2));

                $levelIndex = $this->resolveKolekLevel(
                    $tunggakanPokok,
                    $alokasi,
                    $loan->disbursed_at,
                    null,
                    $endOfMonth,
                    $maxInstNumber,
                    $activeLevels,
                );
                $bucket = $levelKeyByIdx[$levelIndex];

                $sub = &$villagesMap[$vKey]['subtotal'];
                $sub['alokasi'] += $alokasi;
                $sub['saldo'] += $saldo;
                $sub['tunggakan_pokok'] += $tunggakanPokok;
                $sub['tunggakan_jasa'] += $tunggakanJasa;
                $sub[$bucket] = ($sub[$bucket] ?? 0) + $saldo;
                $sub['peminjam_count'] = ($sub['peminjam_count'] ?? 0) + 1;
                unset($sub);

                $prodTotals['alokasi'] += $alokasi;
                $prodTotals['saldo'] += $saldo;
                $prodTotals['tunggakan_pokok'] += $tunggakanPokok;
                $prodTotals['tunggakan_jasa'] += $tunggakanJasa;
                $prodTotals[$bucket] += $saldo;
                $prodTotals['peminjam_count'] = ($prodTotals['peminjam_count'] ?? 0) + 1;
            }

            ksort($villagesMap);

            $productBlocks[] = [
                'product_code' => (string) $prod->code,
                'product_name' => (string) $prod->name,
                'villages' => array_values($villagesMap),
                'totals' => $prodTotals,
            ];

            foreach ($prodTotals as $k => $val) {
                $grandTotals[$k] = ($grandTotals[$k] ?? 0) + (is_numeric($val) ? (float) $val : 0);
            }
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
            'levels' => $levelsPayload,
            'products' => $productBlocks,
            'totals' => $grandTotals,
        ];
    }

    /**
     * @param  iterable<object>  $insts
     */
    private function sumTargetsDue(iterable $insts, string $endOfMonth): array
    {
        $pokok = 0.0;
        $jasa = 0.0;
        $maxInst = 0;
        foreach ($insts as $inst) {
            if ((string) $inst->due_date <= $endOfMonth) {
                $pokok += (float) $inst->principal_due;
                $jasa += (float) $inst->interest_due;
            }
            $maxInst = max($maxInst, (int) $inst->installment_number);
        }

        return [$pokok, $jasa, $maxInst];
    }

    /**
     * @param  iterable<object>  $allocs
     */
    private function sumPaidComponents(iterable $allocs, ?string $endOfMonth = null): array
    {
        $pokok = 0.0;
        $jasa = 0.0;
        foreach ($allocs as $alloc) {
            if ($endOfMonth !== null && isset($alloc->paid_at) && (string) $alloc->paid_at > $endOfMonth) {
                continue;
            }
            $component = isset($alloc->component) ? (string) $alloc->component : '';
            if ($component === 'principal') {
                $pokok += (float) $alloc->amount;
            } elseif ($component === 'interest') {
                $jasa += (float) $alloc->amount;
            }
        }

        return [$pokok, $jasa];
    }

    /**
     * Tentukan index tingkat kolek (0-based) sesuai config aktif.
     * Rumus mirror SIUPK original: kolek_bulan = ceil(tunggakan_pokok/avgMonthly + (selisih - angsuran_ke)).
     *
     * @param  list<array{nama:string, prosentase:string, durasi:string, satuan:string}>  $activeLevels
     */
    private function resolveKolekLevel(
        float $tunggakanPokok,
        float $alokasi,
        ?string $disbursedAt,
        ?string $completedAt,
        string $endOfMonth,
        int $maxInstNumber,
        array $activeLevels,
    ): int {
        if ($activeLevels === []) {
            return 0;
        }

        // Jika sudah lunas/hapus sebelum end-of-month → kolek = Lancar (index 0).
        if ($completedAt !== null && $completedAt <= $endOfMonth) {
            return 0;
        }

        $kolekBulan = 0.0;
        if ($disbursedAt && $tunggakanPokok > 0.0) {
            $disbursed = CarbonImmutable::parse($disbursedAt);
            $ref = CarbonImmutable::parse($endOfMonth);

            $selisih = (((int) $ref->format('Y') - (int) $disbursed->format('Y')) * 12)
                + ((int) $ref->format('n') - (int) $disbursed->format('n'));

            $avgMonthly = $alokasi > 0 ? ($alokasi / 12.0) : 1.0;
            $bagian = $tunggakanPokok / $avgMonthly;
            $raw = $bagian + ($selisih - $maxInstNumber);
            $kolekBulan = (float) ceil($raw);
            if ($kolekBulan < 0) {
                $kolekBulan = 0.0;
            }
        }

        foreach ($activeLevels as $idx => $row) {
            $durasiBulan = $this->durationInMonths($row);
            if ($kolekBulan < $durasiBulan) {
                return $idx;
            }
        }

        return count($activeLevels) - 1;
    }

    /**
     * @param  array{nama:string, prosentase:string, durasi:string, satuan:string}  $row
     */
    private function durationInMonths(array $row): float
    {
        $durasi = (float) ($row['durasi'] ?? 0);
        $satuan = (string) ($row['satuan'] ?? 'bulan');

        return $satuan === 'hari' ? $durasi / 30 : $durasi;
    }

    /**
     * Bangun key bucket kolek per index. Mis. index 0 → 'kolek1_lancar', dst.
     *
     * @param  list<array{nama:string, prosentase:string, durasi:string, satuan:string}>  $activeLevels
     * @return list<string>
     */
    private function buildLevelBucketKeys(array $activeLevels): array
    {
        $out = [];
        foreach ($activeLevels as $idx => $row) {
            $nama = strtolower((string) ($row['nama'] ?? 'kolek'));
            $slug = preg_replace('/[^a-z0-9]+/i', '_', $nama) ?? 'kolek';
            $slug = trim($slug, '_');
            $out[] = sprintf('kolek%d_%s', $idx + 1, $slug === '' ? 'kolek' : $slug);
        }

        return $out;
    }

    /**
     * @param  list<array{nama:string, prosentase:string, durasi:string, satuan:string}>  $activeLevels
     * @return array<string, float>
     */
    private function buildEmptyTotals(array $activeLevels): array
    {
        $totals = [
            'alokasi' => 0.0,
            'saldo' => 0.0,
            'tunggakan_pokok' => 0.0,
            'tunggakan_jasa' => 0.0,
            'peminjam_count' => 0,
        ];
        foreach ($this->buildLevelBucketKeys($activeLevels) as $bucket) {
            $totals[$bucket] = 0.0;
        }

        return $totals;
    }
}
