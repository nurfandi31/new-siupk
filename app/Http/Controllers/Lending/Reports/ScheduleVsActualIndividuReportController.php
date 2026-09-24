<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Services\Reports\ScheduleVsActualIndividuService;
use App\Models\User;
use App\Support\Excel\XlsxWriter;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ScheduleVsActualIndividuReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly ScheduleVsActualIndividuService $service,
        private readonly ReportPdf $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month, $loanId, $product] = $this->filters($request);

        return Inertia::render('Lending/Reports/ScheduleVsActualIndividu', [
            ...$this->service->build($year, $month, $loanId, $product),
            'filters' => [
                'year' => $year,
                'month' => $month,
                'loan_id' => $loanId,
                'product' => $product,
            ],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month, $loanId, $product] = $this->filters($request);
        $data = $this->service->build($year, $month, $loanId, $product);

        $filename = sprintf('rencana-realisasi-individu-%04d-%02d.pdf', $year, $month);

        return $this->pdf->stream(
            'reports.pdf.lending.schedule_vs_actual_individu',
            $data,
            $filename,
            'landscape',
        );
    }

    public function excel(Request $request): StreamedResponse
    {
        $this->authorize($request);
        [$year, $month, $loanId, $product] = $this->filters($request);
        $data = $this->service->build($year, $month, $loanId, $product);

        $w = new XlsxWriter;
        $w->addSheet('Rencana vs Realisasi');
        $w->setColumnWidths([5, 14, 22, 22, 22, 14, 14, 14, 14, 14, 14, 14, 14, 12, 18]);

        $w->addRow([$data['identity']['legal_name'] ?? ''], [XlsxWriter::STYLE_BOLD]);
        $w->addRow(['Rencana vs Realisasi Angsuran — Pinjaman Individu — '.$data['period']['period_label']], [XlsxWriter::STYLE_BOLD]);
        $w->addRow([]);

        $w->addRow(
            ['No', 'Loan ID', 'Nomor Pinjaman', 'Peminjam', 'Desa', 'Produk', 'Tgl', 'Angsuran', 'Rencana Pokok', 'Realisasi Pokok', 'Selisih Pokok', 'Rencana Jasa', 'Realisasi Jasa', 'Selisih Jasa', 'Status'],
            array_fill(0, 15, XlsxWriter::STYLE_BOLD),
        );

        $no = 1;
        foreach ($data['loans'] ?? [] as $loan) {
            // Section header
            $w->addRow(
                [
                    '', '#'.($loan['loan_id'] ?? ''),
                    $loan['loan_number'] ?? '',
                    $loan['member_name'] ?? '',
                    $loan['village_name'] ?? '',
                    ($loan['product_code'] ?? '').' '.($loan['product_name'] ?? ''),
                    '', '',
                    $loan['totals']['plan_principal'] ?? 0,
                    $loan['totals']['actual_principal'] ?? 0,
                    $loan['totals']['gap_principal'] ?? 0,
                    $loan['totals']['plan_interest'] ?? 0,
                    $loan['totals']['actual_interest'] ?? 0,
                    $loan['totals']['gap_interest'] ?? 0,
                    'Realisasi '.($loan['totals']['pct_realization'] ?? 0).'%',
                ],
                [
                    XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD,
                    XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD,
                    XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD,
                    XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD,
                    XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD,
                    XlsxWriter::STYLE_BOLD,
                ],
            );

            foreach ($loan['rows'] ?? [] as $row) {
                $w->addRow(
                    [
                        $no++,
                        '',
                        '',
                        '',
                        '',
                        '',
                        $row['due_date'] ?? '',
                        $row['installment_number'] ?? '',
                        $row['plan_principal'] ?? 0,
                        $row['actual_principal'] ?? 0,
                        $row['gap_principal'] ?? 0,
                        $row['plan_interest'] ?? 0,
                        $row['actual_interest'] ?? 0,
                        $row['gap_interest'] ?? 0,
                        $row['realization_status'] ?? '',
                    ],
                    [
                        XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_DEFAULT,
                        XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_DEFAULT,
                        XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_DEFAULT,
                        XlsxWriter::STYLE_RUPIAH, XlsxWriter::STYLE_RUPIAH, XlsxWriter::STYLE_RUPIAH,
                        XlsxWriter::STYLE_RUPIAH, XlsxWriter::STYLE_RUPIAH, XlsxWriter::STYLE_RUPIAH,
                        XlsxWriter::STYLE_DEFAULT,
                    ],
                );
            }
        }

        $t = $data['totals'] ?? [];
        $w->addRow([]);
        $w->addRow(
            ['', '', '', 'TOTAL', '', '', '', $t['installment_count'] ?? 0, $t['plan_principal'] ?? 0, $t['actual_principal'] ?? 0, $t['gap_principal'] ?? 0, $t['plan_interest'] ?? 0, $t['actual_interest'] ?? 0, $t['gap_interest'] ?? 0, 'Realisasi '.($t['pct_realization'] ?? 0).'%'],
            [
                XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD,
                XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD,
                XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD,
                XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD,
                XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD,
                XlsxWriter::STYLE_BOLD,
            ],
        );

        return $w->download(sprintf('rencana-realisasi-individu-%04d-%02d.xlsx', $year, $month));
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'loans.view');
    }

    /**
     * @return array{0: int, 1: int, 2: ?string, 3: ?string}
     */
    private function filters(Request $request): array
    {
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('n'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        $loanId = $request->query('loan_id');
        if (! is_string($loanId) || trim($loanId) === '') {
            $loanId = null;
        }

        $product = $request->query('product');
        if (! is_string($product) || trim($product) === '' || $product === 'all') {
            $product = null;
        }

        return [$year, $month, $loanId, $product];
    }
}
