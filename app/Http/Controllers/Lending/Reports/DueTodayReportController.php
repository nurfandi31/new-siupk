<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Services\Reports\DueTodayReportService;
use App\Models\User;
use App\Support\Excel\XlsxWriter;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DueTodayReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly DueTodayReportService $service,
        private readonly ReportPdf $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$asOf, $scope] = $this->filters($request);

        return Inertia::render('Lending/Reports/DueToday', [
            ...$this->service->build($asOf, $scope),
            'filters' => ['as_of' => $asOf, 'scope' => $scope],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$asOf, $scope] = $this->filters($request);
        $data = $this->service->build($asOf, $scope);

        $filename = 'tagihan-jatuh-tempo-'.($data['as_of'] ?? date('Y-m-d')).'.pdf';

        return $this->pdf->stream(
            'reports.pdf.lending.due_today',
            $data,
            $filename,
            'landscape',
        );
    }

    public function excel(Request $request): StreamedResponse
    {
        $this->authorize($request);
        [$asOf, $scope] = $this->filters($request);
        $data = $this->service->build($asOf, $scope);

        $w = new XlsxWriter;
        $w->addSheet('Tagihan Jatuh Tempo');
        $w->setColumnWidths([5, 12, 18, 26, 22, 12, 14, 14, 14, 14, 14, 14]);

        $w->addRow([$data['identity']['legal_name'] ?? ''], [XlsxWriter::STYLE_BOLD]);
        $w->addRow(['Tagihan Jatuh Tempo — '.$data['period']['period_label']], [XlsxWriter::STYLE_BOLD]);
        $w->addRow([]);

        $w->addRow(
            ['No', 'Loan ID', 'Nomor Pinjaman', 'Peminjam/Kelompok', 'Desa', 'Produk', 'Angsuran Ke-', 'Tgl Jatuh Tempo', 'Pokok', 'Bunga', 'Denda', 'Total Angsuran'],
            array_fill(0, 12, XlsxWriter::STYLE_BOLD),
        );

        $no = 1;
        foreach ($data['rows'] ?? [] as $row) {
            $w->addRow(
                [
                    $no++,
                    '#'.($row['loan_id'] ?? ''),
                    $row['loan_number'] ?? '',
                    trim(($row['borrower_name'] ?? '').' '.($row['borrower_code'] !== '' && $row['borrower_code'] !== null ? '('.$row['borrower_code'].')' : '')),
                    $row['village_name'] ?? '',
                    ($row['product_code'] ?? '').' '.($row['product_name'] ?? ''),
                    $row['installment_number'] ?? '',
                    $row['due_date'] ?? '',
                    $row['principal_due'] ?? 0,
                    $row['interest_due'] ?? 0,
                    $row['penalty_due'] ?? 0,
                    $row['total_due'] ?? 0,
                ],
                array_merge([XlsxWriter::STYLE_DEFAULT], array_fill(1, 4, XlsxWriter::STYLE_DEFAULT), [XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_RUPIAH, XlsxWriter::STYLE_RUPIAH, XlsxWriter::STYLE_RUPIAH, XlsxWriter::STYLE_RUPIAH]),
            );
        }

        $t = $data['totals'] ?? [];
        $w->addRow([]);
        $w->addRow(
            ['', '', '', 'TOTAL', '', '', $t['count'] ?? 0, 'angsuran', '', $t['principal_due'] ?? 0, $t['interest_due'] ?? 0, $t['penalty_due'] ?? 0, $t['total_due'] ?? 0],
            array_merge([XlsxWriter::STYLE_BOLD], array_fill(1, 6, XlsxWriter::STYLE_BOLD), [XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD]),
        );

        return $w->download('tagihan-jatuh-tempo-'.($data['as_of'] ?? date('Y-m-d')).'.xlsx');
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'loans.view');
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function filters(Request $request): array
    {
        $asOf = $request->query('as_of');
        if (! is_string($asOf) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) !== 1) {
            $asOf = null;
        }

        $scope = (string) $request->query('scope', 'all');
        if (! in_array($scope, ['all', 'group', 'member'], true)) {
            $scope = 'all';
        }

        return [$asOf, $scope === 'all' ? null : $scope];
    }
}
