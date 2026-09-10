<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Perkembangan Piutang (LPP) — Rekap per Desa & Rincian per Kelompok.
 * Menghitung Alokasi, Target, Realisasi (Lalu, Ini, Kumulatif), Saldo, dan Tunggakan (Pokok & Jasa).
 */
final class LppReportService
{
    private const ACTIVE = ['active', 'disbursed'];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   period_label: string,
     *   identity: array{legal_name: string, short_name: ?string},
     *   products: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function buildDesa(int $year, int $month, ?string $productCode = null): array
    {
        $tenantId = $this->context->id();
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $profile = OrganizationProfile::query()->first();

        // 1. Ambil Produk Pinjaman
        $productsQuery = DB::connection('tenant')
            ->table('loan_products')
            ->where('tenant_id', $tenantId)
            ->orderBy('code');

        if ($productCode !== null && $productCode !== 'all') {
            $productsQuery->where('code', $productCode);
        }

        $products = $productsQuery->get(['row_id', 'code', 'name', 'default_interest_rate', 'interest_method']);

        // 2. Ambil Pinjaman Aktif
        $loansQuery = DB::connection('tenant')
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
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->orderBy('v.name')
            ->orderBy('g.name')
            ->orderBy('l.id');

        $loans = $loansQuery->get([
            'l.row_id',
            'l.id',
            'l.loan_number',
            'l.loan_product_row_id',
            'l.disbursed_at',
            'l.principal_amount',
            'g.row_id as group_row_id',
            'g.name as group_name',
            'v.row_id as village_row_id',
            'v.name as village_name',
        ]);

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        // 3. Ambil Rencana Angsuran (Target s.d. Bulan Ini)
        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->get([
                    'loan_row_id',
                    'installment_number',
                    'due_date',
                    'principal_due',
                    'interest_due',
                ]);

        // 4. Ambil Pembayaran & Alokasi Pembayaran
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
                ->get([
                    'p.loan_row_id',
                    'p.paid_at',
                    'a.component',
                    'a.amount',
                ]);

        // Pre-group installments & allocations by loan_row_id
        $instByLoan = $installments->groupBy('loan_row_id');
        $allocByLoan = $allocations->groupBy('loan_row_id');

        // Total akumulasi per Produk dan per Desa
        $productBlocks = [];
        $grandTotals = [
            'alokasi' => 0.0,
            'target_pokok' => 0.0,
            'target_jasa' => 0.0,
            'real_lalu_pokok' => 0.0,
            'real_lalu_jasa' => 0.0,
            'real_ini_pokok' => 0.0,
            'real_ini_jasa' => 0.0,
            'real_kumulatif_pokok' => 0.0,
            'real_kumulatif_jasa' => 0.0,
            'saldo_pokok' => 0.0,
            'saldo_jasa' => 0.0,
            'tunggakan_pokok' => 0.0,
            'tunggakan_jasa' => 0.0,
            'kelompok_count' => 0,
            'pemanfaat_count' => 0,
        ];

        foreach ($products as $prod) {
            $prodLoans = $loans->where('loan_product_row_id', $prod->row_id);
            if ($prodLoans->isEmpty()) {
                continue;
            }

            $villagesMap = [];
            $prodTotals = [
                'alokasi' => 0.0,
                'target_pokok' => 0.0,
                'target_jasa' => 0.0,
                'real_lalu_pokok' => 0.0,
                'real_lalu_jasa' => 0.0,
                'real_ini_pokok' => 0.0,
                'real_ini_jasa' => 0.0,
                'real_kumulatif_pokok' => 0.0,
                'real_kumulatif_jasa' => 0.0,
                'saldo_pokok' => 0.0,
                'saldo_jasa' => 0.0,
                'tunggakan_pokok' => 0.0,
                'tunggakan_jasa' => 0.0,
                'kelompok_count' => 0,
                'pemanfaat_count' => 0,
            ];

            foreach ($prodLoans as $loan) {
                $vKey = (string) ($loan->village_name ?? 'Lain-lain');
                if (! isset($villagesMap[$vKey])) {
                    $villagesMap[$vKey] = [
                        'village_name' => $vKey,
                        'alokasi' => 0.0,
                        'target_pokok' => 0.0,
                        'target_jasa' => 0.0,
                        'real_lalu_pokok' => 0.0,
                        'real_lalu_jasa' => 0.0,
                        'real_ini_pokok' => 0.0,
                        'real_ini_jasa' => 0.0,
                        'real_kumulatif_pokok' => 0.0,
                        'real_kumulatif_jasa' => 0.0,
                        'saldo_pokok' => 0.0,
                        'saldo_jasa' => 0.0,
                        'tunggakan_pokok' => 0.0,
                        'tunggakan_jasa' => 0.0,
                        'kelompok_count' => 0,
                        'pemanfaat_count' => 0,
                    ];
                }

                $alokasi = (float) $loan->principal_amount;
                $borrowerCount = max(1, (int) ($loan->borrower_count ?? 1));

                // Hitung target s.d. bulan ini
                $loanInsts = $instByLoan->get($loan->row_id) ?? collect();
                $targetPokok = 0.0;
                $targetJasa = 0.0;
                $totalJasaDue = 0.0;

                foreach ($loanInsts as $inst) {
                    $dueDate = (string) $inst->due_date;
                    $pDue = (float) $inst->principal_due;
                    $iDue = (float) $inst->interest_due;
                    $totalJasaDue += $iDue;

                    if ($dueDate <= $endOfMonth) {
                        $targetPokok += $pDue;
                        $targetJasa += $iDue;
                    }
                }

                // Hitung realisasi lalu dan ini
                $loanAllocs = $allocByLoan->get($loan->row_id) ?? collect();
                $realLaluPokok = 0.0;
                $realLaluJasa = 0.0;
                $realIniPokok = 0.0;
                $realIniJasa = 0.0;

                foreach ($loanAllocs as $alloc) {
                    $paidAt = (string) $alloc->paid_at;
                    $comp = (string) $alloc->component;
                    $amt = (float) $alloc->amount;

                    if ($paidAt < $startOfMonth) {
                        if ($comp === 'principal') {
                            $realLaluPokok += $amt;
                        } elseif ($comp === 'interest') {
                            $realLaluJasa += $amt;
                        }
                    } elseif ($paidAt <= $endOfMonth) {
                        if ($comp === 'principal') {
                            $realIniPokok += $amt;
                        } elseif ($comp === 'interest') {
                            $realIniJasa += $amt;
                        }
                    }
                }

                $realKumulatifPokok = round($realLaluPokok + $realIniPokok, 2);
                $realKumulatifJasa = round($realLaluJasa + $realIniJasa, 2);

                $saldoPokok = max(0.0, round($alokasi - $realKumulatifPokok, 2));
                $saldoJasa = max(0.0, round($totalJasaDue - $realKumulatifJasa, 2));

                $tunggakanPokok = max(0.0, round($targetPokok - $realKumulatifPokok, 2));
                $tunggakanJasa = max(0.0, round($targetJasa - $realKumulatifJasa, 2));

                // Akumulasi ke Desa
                $villagesMap[$vKey]['alokasi'] += $alokasi;
                $villagesMap[$vKey]['target_pokok'] += $targetPokok;
                $villagesMap[$vKey]['target_jasa'] += $targetJasa;
                $villagesMap[$vKey]['real_lalu_pokok'] += $realLaluPokok;
                $villagesMap[$vKey]['real_lalu_jasa'] += $realLaluJasa;
                $villagesMap[$vKey]['real_ini_pokok'] += $realIniPokok;
                $villagesMap[$vKey]['real_ini_jasa'] += $realIniJasa;
                $villagesMap[$vKey]['real_kumulatif_pokok'] += $realKumulatifPokok;
                $villagesMap[$vKey]['real_kumulatif_jasa'] += $realKumulatifJasa;
                $villagesMap[$vKey]['saldo_pokok'] += $saldoPokok;
                $villagesMap[$vKey]['saldo_jasa'] += $saldoJasa;
                $villagesMap[$vKey]['tunggakan_pokok'] += $tunggakanPokok;
                $villagesMap[$vKey]['tunggakan_jasa'] += $tunggakanJasa;
                $villagesMap[$vKey]['kelompok_count'] += 1;
                $villagesMap[$vKey]['pemanfaat_count'] += $borrowerCount;

                // Akumulasi ke Produk
                $prodTotals['alokasi'] += $alokasi;
                $prodTotals['target_pokok'] += $targetPokok;
                $prodTotals['target_jasa'] += $targetJasa;
                $prodTotals['real_lalu_pokok'] += $realLaluPokok;
                $prodTotals['real_lalu_jasa'] += $realLaluJasa;
                $prodTotals['real_ini_pokok'] += $realIniPokok;
                $prodTotals['real_ini_jasa'] += $realIniJasa;
                $prodTotals['real_kumulatif_pokok'] += $realKumulatifPokok;
                $prodTotals['real_kumulatif_jasa'] += $realKumulatifJasa;
                $prodTotals['saldo_pokok'] += $saldoPokok;
                $prodTotals['saldo_jasa'] += $saldoJasa;
                $prodTotals['tunggakan_pokok'] += $tunggakanPokok;
                $prodTotals['tunggakan_jasa'] += $tunggakanJasa;
                $prodTotals['kelompok_count'] += 1;
                $prodTotals['pemanfaat_count'] += $borrowerCount;
            }

            // Urutkan desa berdasarkan nama
            ksort($villagesMap);

            $productBlocks[] = [
                'product_code' => (string) $prod->code,
                'product_name' => (string) $prod->name,
                'villages' => array_values($villagesMap),
                'totals' => $prodTotals,
            ];

            // Akumulasi ke Grand Totals
            foreach ($prodTotals as $k => $val) {
                $grandTotals[$k] += $val;
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
            'products' => $productBlocks,
            'totals' => $grandTotals,
        ];
    }

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   period_label: string,
     *   identity: array{legal_name: string, short_name: ?string},
     *   products: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function buildKelompok(int $year, int $month, ?string $productCode = null): array
    {
        $tenantId = $this->context->id();
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $profile = OrganizationProfile::query()->first();

        // 1. Ambil Produk Pinjaman
        $productsQuery = DB::connection('tenant')
            ->table('loan_products')
            ->where('tenant_id', $tenantId)
            ->orderBy('code');

        if ($productCode !== null && $productCode !== 'all') {
            $productsQuery->where('code', $productCode);
        }

        $products = $productsQuery->get(['row_id', 'code', 'name', 'default_interest_rate', 'interest_method']);

        // 2. Ambil Pinjaman Aktif
        $loansQuery = DB::connection('tenant')
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
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->orderBy('v.name')
            ->orderBy('g.name')
            ->orderBy('l.id');

        $loans = $loansQuery->get([
            'l.row_id',
            'l.id',
            'l.loan_number',
            'l.loan_product_row_id',
            'l.disbursed_at',
            'l.principal_amount',
            'l.borrower_count',
            'g.row_id as group_row_id',
            'g.name as group_name',
            'g.code as group_code',
            'v.row_id as village_row_id',
            'v.name as village_name',
        ]);

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->get([
                    'loan_row_id',
                    'installment_number',
                    'due_date',
                    'principal_due',
                    'interest_due',
                ]);

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
                ->get([
                    'p.loan_row_id',
                    'p.paid_at',
                    'a.component',
                    'a.amount',
                ]);

        $instByLoan = $installments->groupBy('loan_row_id');
        $allocByLoan = $allocations->groupBy('loan_row_id');

        $productBlocks = [];
        $grandTotals = [
            'alokasi' => 0.0,
            'target_pokok' => 0.0,
            'target_jasa' => 0.0,
            'real_lalu_pokok' => 0.0,
            'real_lalu_jasa' => 0.0,
            'real_ini_pokok' => 0.0,
            'real_ini_jasa' => 0.0,
            'real_kumulatif_pokok' => 0.0,
            'real_kumulatif_jasa' => 0.0,
            'saldo_pokok' => 0.0,
            'saldo_jasa' => 0.0,
            'tunggakan_pokok' => 0.0,
            'tunggakan_jasa' => 0.0,
            'kelompok_count' => 0,
            'pemanfaat_count' => 0,
        ];

        foreach ($products as $prod) {
            $prodLoans = $loans->where('loan_product_row_id', $prod->row_id);
            if ($prodLoans->isEmpty()) {
                continue;
            }

            $villagesMap = [];
            $prodTotals = [
                'alokasi' => 0.0,
                'target_pokok' => 0.0,
                'target_jasa' => 0.0,
                'real_lalu_pokok' => 0.0,
                'real_lalu_jasa' => 0.0,
                'real_ini_pokok' => 0.0,
                'real_ini_jasa' => 0.0,
                'real_kumulatif_pokok' => 0.0,
                'real_kumulatif_jasa' => 0.0,
                'saldo_pokok' => 0.0,
                'saldo_jasa' => 0.0,
                'tunggakan_pokok' => 0.0,
                'tunggakan_jasa' => 0.0,
                'kelompok_count' => 0,
                'pemanfaat_count' => 0,
            ];

            foreach ($prodLoans as $loan) {
                $vKey = (string) ($loan->village_name ?? 'Lain-lain');
                if (! isset($villagesMap[$vKey])) {
                    $villagesMap[$vKey] = [
                        'village_name' => $vKey,
                        'loans' => [],
                        'subtotal' => [
                            'alokasi' => 0.0,
                            'target_pokok' => 0.0,
                            'target_jasa' => 0.0,
                            'real_lalu_pokok' => 0.0,
                            'real_lalu_jasa' => 0.0,
                            'real_ini_pokok' => 0.0,
                            'real_ini_jasa' => 0.0,
                            'real_kumulatif_pokok' => 0.0,
                            'real_kumulatif_jasa' => 0.0,
                            'saldo_pokok' => 0.0,
                            'saldo_jasa' => 0.0,
                            'tunggakan_pokok' => 0.0,
                            'tunggakan_jasa' => 0.0,
                            'kelompok_count' => 0,
                            'pemanfaat_count' => 0,
                        ],
                    ];
                }

                $alokasi = (float) $loan->principal_amount;
                $borrowerCount = max(1, (int) ($loan->borrower_count ?? 1));

                $loanInsts = $instByLoan->get($loan->row_id) ?? collect();
                $targetPokok = 0.0;
                $targetJasa = 0.0;
                $totalJasaDue = 0.0;

                foreach ($loanInsts as $inst) {
                    $dueDate = (string) $inst->due_date;
                    $pDue = (float) $inst->principal_due;
                    $iDue = (float) $inst->interest_due;
                    $totalJasaDue += $iDue;

                    if ($dueDate <= $endOfMonth) {
                        $targetPokok += $pDue;
                        $targetJasa += $iDue;
                    }
                }

                $loanAllocs = $allocByLoan->get($loan->row_id) ?? collect();
                $realLaluPokok = 0.0;
                $realLaluJasa = 0.0;
                $realIniPokok = 0.0;
                $realIniJasa = 0.0;

                foreach ($loanAllocs as $alloc) {
                    $paidAt = (string) $alloc->paid_at;
                    $comp = (string) $alloc->component;
                    $amt = (float) $alloc->amount;

                    if ($paidAt < $startOfMonth) {
                        if ($comp === 'principal') {
                            $realLaluPokok += $amt;
                        } elseif ($comp === 'interest') {
                            $realLaluJasa += $amt;
                        }
                    } elseif ($paidAt <= $endOfMonth) {
                        if ($comp === 'principal') {
                            $realIniPokok += $amt;
                        } elseif ($comp === 'interest') {
                            $realIniJasa += $amt;
                        }
                    }
                }

                $realKumulatifPokok = round($realLaluPokok + $realIniPokok, 2);
                $realKumulatifJasa = round($realLaluJasa + $realIniJasa, 2);

                $saldoPokok = max(0.0, round($alokasi - $realKumulatifPokok, 2));
                $saldoJasa = max(0.0, round($totalJasaDue - $realKumulatifJasa, 2));

                $tunggakanPokok = max(0.0, round($targetPokok - $realKumulatifPokok, 2));
                $tunggakanJasa = max(0.0, round($targetJasa - $realKumulatifJasa, 2));

                $loanData = [
                    'loan_id' => (int) $loan->id,
                    'loan_number' => (string) $loan->loan_number,
                    'group_name' => (string) ($loan->group_name ?? 'Individu'),
                    'group_code' => $loan->group_code,
                    'disbursed_at' => (string) $loan->disbursed_at,
                    'alokasi' => $alokasi,
                    'pemanfaat_count' => $borrowerCount,
                    'target_pokok' => $targetPokok,
                    'target_jasa' => $targetJasa,
                    'real_lalu_pokok' => $realLaluPokok,
                    'real_lalu_jasa' => $realLaluJasa,
                    'real_ini_pokok' => $realIniPokok,
                    'real_ini_jasa' => $realIniJasa,
                    'real_kumulatif_pokok' => $realKumulatifPokok,
                    'real_kumulatif_jasa' => $realKumulatifJasa,
                    'saldo_pokok' => $saldoPokok,
                    'saldo_jasa' => $saldoJasa,
                    'tunggakan_pokok' => $tunggakanPokok,
                    'tunggakan_jasa' => $tunggakanJasa,
                ];

                $villagesMap[$vKey]['loans'][] = $loanData;

                // Subtotal Desa
                $sub = &$villagesMap[$vKey]['subtotal'];
                $sub['alokasi'] += $alokasi;
                $sub['target_pokok'] += $targetPokok;
                $sub['target_jasa'] += $targetJasa;
                $sub['real_lalu_pokok'] += $realLaluPokok;
                $sub['real_lalu_jasa'] += $realLaluJasa;
                $sub['real_ini_pokok'] += $realIniPokok;
                $sub['real_ini_jasa'] += $realIniJasa;
                $sub['real_kumulatif_pokok'] += $realKumulatifPokok;
                $sub['real_kumulatif_jasa'] += $realKumulatifJasa;
                $sub['saldo_pokok'] += $saldoPokok;
                $sub['saldo_jasa'] += $saldoJasa;
                $sub['tunggakan_pokok'] += $tunggakanPokok;
                $sub['tunggakan_jasa'] += $tunggakanJasa;
                $sub['kelompok_count'] += 1;
                $sub['pemanfaat_count'] += $borrowerCount;
                unset($sub);

                // Prod Totals
                $prodTotals['alokasi'] += $alokasi;
                $prodTotals['target_pokok'] += $targetPokok;
                $prodTotals['target_jasa'] += $targetJasa;
                $prodTotals['real_lalu_pokok'] += $realLaluPokok;
                $prodTotals['real_lalu_jasa'] += $realLaluJasa;
                $prodTotals['real_ini_pokok'] += $realIniPokok;
                $prodTotals['real_ini_jasa'] += $realIniJasa;
                $prodTotals['real_kumulatif_pokok'] += $realKumulatifPokok;
                $prodTotals['real_kumulatif_jasa'] += $realKumulatifJasa;
                $prodTotals['saldo_pokok'] += $saldoPokok;
                $prodTotals['saldo_jasa'] += $saldoJasa;
                $prodTotals['tunggakan_pokok'] += $tunggakanPokok;
                $prodTotals['tunggakan_jasa'] += $tunggakanJasa;
                $prodTotals['kelompok_count'] += 1;
                $prodTotals['pemanfaat_count'] += $borrowerCount;
            }

            ksort($villagesMap);

            $productBlocks[] = [
                'product_code' => (string) $prod->code,
                'product_name' => (string) $prod->name,
                'villages' => array_values($villagesMap),
                'totals' => $prodTotals,
            ];

            foreach ($prodTotals as $k => $val) {
                $grandTotals[$k] += $val;
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
            'products' => $productBlocks,
            'totals' => $grandTotals,
        ];
    }
}
