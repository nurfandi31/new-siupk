<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Kolektibilitas & Cadangan Kerugian Penurunan Nilai (CKPN / Cadangan Penghapusan Piutang).
 */
final class CollectibilityReportService
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
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->orderBy('v.name')
            ->orderBy('l.id')
            ->get([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.loan_product_row_id',
                'l.disbursed_at',
                'l.principal_amount',
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
                ->where('p.paid_at', '<=', $endOfMonth)
                ->get([
                    'p.loan_row_id',
                    'a.component',
                    'a.amount',
                ]);

        $instByLoan = $installments->groupBy('loan_row_id');
        $allocByLoan = $allocations->groupBy('loan_row_id');

        $productBlocks = [];
        $grandTotals = [
            'alokasi' => 0.0,
            'saldo' => 0.0,
            'tunggakan_pokok' => 0.0,
            'tunggakan_jasa' => 0.0,
            'kolek1_lancar' => 0.0,
            'kolek2_diragukan' => 0.0,
            'kolek3_macet' => 0.0,
        ];

        foreach ($products as $prod) {
            $prodLoans = $loans->where('loan_product_row_id', $prod->row_id);
            if ($prodLoans->isEmpty()) {
                continue;
            }

            $villagesMap = [];
            $prodTotals = [
                'alokasi' => 0.0,
                'saldo' => 0.0,
                'tunggakan_pokok' => 0.0,
                'tunggakan_jasa' => 0.0,
                'kolek1_lancar' => 0.0,
                'kolek2_diragukan' => 0.0,
                'kolek3_macet' => 0.0,
            ];

            foreach ($prodLoans as $loan) {
                $vKey = (string) ($loan->village_name ?? 'Lain-lain');
                if (! isset($villagesMap[$vKey])) {
                    $villagesMap[$vKey] = [
                        'village_name' => $vKey,
                        'alokasi' => 0.0,
                        'saldo' => 0.0,
                        'tunggakan_pokok' => 0.0,
                        'tunggakan_jasa' => 0.0,
                        'kolek1_lancar' => 0.0,
                        'kolek2_diragukan' => 0.0,
                        'kolek3_macet' => 0.0,
                    ];
                }

                $alokasi = (float) $loan->principal_amount;
                $loanInsts = $instByLoan->get($loan->row_id) ?? collect();
                $loanAllocs = $allocByLoan->get($loan->row_id) ?? collect();

                $targetPokok = 0.0;
                $targetJasa = 0.0;
                $overdueMonths = 0;

                foreach ($loanInsts as $inst) {
                    $dueDate = (string) $inst->due_date;
                    if ($dueDate <= $endOfMonth) {
                        $targetPokok += (float) $inst->principal_due;
                        $targetJasa += (float) $inst->interest_due;
                    }
                }

                $paidPokok = (float) $loanAllocs->where('component', 'principal')->sum('amount');
                $paidJasa = (float) $loanAllocs->where('component', 'interest')->sum('amount');

                $saldoPokok = max(0.0, round($alokasi - $paidPokok, 2));
                $tunggakanPokok = max(0.0, round($targetPokok - $paidPokok, 2));
                $tunggakanJasa = max(0.0, round($targetJasa - $paidJasa, 2));

                // Estimasi bulan menunggak berdasarkan proporsi tunggakan pokok terhadap rata-rata angsuran bulanan
                $avgMonthlyInst = $loanInsts->count() > 0 ? ($alokasi / $loanInsts->count()) : 1;
                $overdueMonths = $avgMonthlyInst > 0 ? (int) floor($tunggakanPokok / $avgMonthlyInst) : 0;

                $kolek1 = 0.0;
                $kolek2 = 0.0;
                $kolek3 = 0.0;

                if ($overdueMonths <= 3) {
                    $kolek1 = $saldoPokok; // Lancar / Kolek 1-3
                } elseif ($overdueMonths <= 5) {
                    $kolek2 = $saldoPokok; // Diragukan / Kolek 4-5
                } else {
                    $kolek3 = $saldoPokok; // Macet / Kolek 6+
                }

                $villagesMap[$vKey]['alokasi'] += $alokasi;
                $villagesMap[$vKey]['saldo'] += $saldoPokok;
                $villagesMap[$vKey]['tunggakan_pokok'] += $tunggakanPokok;
                $villagesMap[$vKey]['tunggakan_jasa'] += $tunggakanJasa;
                $villagesMap[$vKey]['kolek1_lancar'] += $kolek1;
                $villagesMap[$vKey]['kolek2_diragukan'] += $kolek2;
                $villagesMap[$vKey]['kolek3_macet'] += $kolek3;

                $prodTotals['alokasi'] += $alokasi;
                $prodTotals['saldo'] += $saldoPokok;
                $prodTotals['tunggakan_pokok'] += $tunggakanPokok;
                $prodTotals['tunggakan_jasa'] += $tunggakanJasa;
                $prodTotals['kolek1_lancar'] += $kolek1;
                $prodTotals['kolek2_diragukan'] += $kolek2;
                $prodTotals['kolek3_macet'] += $kolek3;
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

    /**
     * Cadangan Penghapusan Piutang (CKPN)
     * Lancar (0.5%), Diragukan (50%), Macet (100%).
     *
     * @return array{
     *   year: int,
     *   month: int,
     *   period_label: string,
     *   identity: array{legal_name: string, short_name: ?string},
     *   products: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function buildCadangan(int $year, int $month, ?string $productCode = null): array
    {
        $data = $this->buildDesa($year, $month, $productCode);

        // Tambahkan kalkulasi CKPN pada setiap desa dan totals
        foreach ($data['products'] as &$prod) {
            $ckpnProd = 0.0;
            foreach ($prod['villages'] as &$v) {
                $ckpn1 = round($v['kolek1_lancar'] * 0.005, 2); // 0.5%
                $ckpn2 = round($v['kolek2_diragukan'] * 0.50, 2); // 50%
                $ckpn3 = round($v['kolek3_macet'] * 1.00, 2); // 100%
                $totalCkpn = round($ckpn1 + $ckpn2 + $ckpn3, 2);

                $v['ckpn_lancar'] = $ckpn1;
                $v['ckpn_diragukan'] = $ckpn2;
                $v['ckpn_macet'] = $ckpn3;
                $v['total_ckpn'] = $totalCkpn;
                $ckpnProd += $totalCkpn;
            }
            unset($v);

            $prod['totals']['ckpn_lancar'] = round($prod['totals']['kolek1_lancar'] * 0.005, 2);
            $prod['totals']['ckpn_diragukan'] = round($prod['totals']['kolek2_diragukan'] * 0.50, 2);
            $prod['totals']['ckpn_macet'] = round($prod['totals']['kolek3_macet'] * 1.00, 2);
            $prod['totals']['total_ckpn'] = round($prod['totals']['ckpn_lancar'] + $prod['totals']['ckpn_diragukan'] + $prod['totals']['ckpn_macet'], 2);
        }
        unset($prod);

        $grandCkpn1 = round($data['totals']['kolek1_lancar'] * 0.005, 2);
        $grandCkpn2 = round($data['totals']['kolek2_diragukan'] * 0.50, 2);
        $grandCkpn3 = round($data['totals']['kolek3_macet'] * 1.00, 2);

        $data['totals']['ckpn_lancar'] = $grandCkpn1;
        $data['totals']['ckpn_diragukan'] = $grandCkpn2;
        $data['totals']['ckpn_macet'] = $grandCkpn3;
        $data['totals']['total_ckpn'] = round($grandCkpn1 + $grandCkpn2 + $grandCkpn3, 2);

        return $data;
    }
}
