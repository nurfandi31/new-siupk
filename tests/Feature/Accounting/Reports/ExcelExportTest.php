<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reports;

use App\Support\Excel\ReportExcel;
use App\Support\Excel\XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class ExcelExportTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private ReportExcel $excel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->excel = new ReportExcel;
    }

    public function test_xlsx_writer_generates_valid_zip_file(): void
    {
        $w = new XlsxWriter;
        $w->addSheet('Test Sheet');
        $w->setColumnWidths([10, 20, 15]);
        $w->addRow(['Header 1', 'Header 2', 'Nominal'], [XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD, XlsxWriter::STYLE_BOLD]);
        $w->addRow(['Row 1', 'Item A', 1500000], [XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_RUPIAH]);
        $w->addRow(['Row 2', 'Item B', 2500000], [XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_RUPIAH]);
        $w->addRow(['', 'TOTAL', 4000000], [XlsxWriter::STYLE_DEFAULT, XlsxWriter::STYLE_RUPIAH_BOLD, XlsxWriter::STYLE_RUPIAH_BOLD]);

        $path = $w->save();

        self::assertFileExists($path);
        self::assertGreaterThan(0, filesize($path));

        // Verify it is a valid zip containing required parts
        $zip = new \ZipArchive;
        self::assertTrue($zip->open($path));
        self::assertNotFalse($zip->locateName('[Content_Types].xml'));
        self::assertNotFalse($zip->locateName('xl/workbook.xml'));
        self::assertNotFalse($zip->locateName('xl/styles.xml'));
        self::assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $zip->close();

        @unlink($path);
    }

    public function test_trial_balance_excel_export(): void
    {
        $data = [
            'period' => ['label' => 'Juli 2026', 'year' => 2026, 'month' => 7],
            'identity' => ['legal_name' => 'BUMDesma LKD Mandiri'],
            'rows' => [
                ['code' => '1.1.01.01', 'name' => 'Kas', 'ns_debit' => 1000000, 'ns_credit' => 0, 'lr_debit' => 0, 'lr_credit' => 0, 'bs_debit' => 1000000, 'bs_credit' => 0],
            ],
            'totals' => ['ns_debit' => 1000000, 'ns_credit' => 1000000, 'lr_debit' => 0, 'lr_credit' => 0, 'bs_debit' => 1000000, 'bs_credit' => 1000000],
        ];

        $response = $this->excel->trialBalance($data);

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function test_balance_sheet_excel_export(): void
    {
        $data = [
            'period' => ['label' => 'Juli 2026', 'year' => 2026, 'month' => 7],
            'identity' => ['legal_name' => 'BUMDesma LKD Mandiri'],
            'assets' => [
                ['code' => '1.1.01.01', 'name' => 'Kas', 'balance' => 1000000, 'level' => 4, 'is_postable' => true],
            ],
            'liabilities' => [],
            'equity' => [
                ['code' => '3.1.01.01', 'name' => 'Modal', 'balance' => 1000000, 'level' => 4, 'is_postable' => true],
            ],
            'totals' => ['assets' => 1000000, 'liabilities_equity' => 1000000],
        ];

        $response = $this->excel->balanceSheet($data);

        self::assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_income_statement_excel_export(): void
    {
        $data = [
            'period' => ['label' => 'Juli 2026', 'year' => 2026, 'month' => 7],
            'identity' => ['legal_name' => 'BUMDesma LKD Mandiri'],
            'revenue' => [
                ['code' => '4.1.01.01', 'name' => 'Pendapatan Jasa', 'balance' => 500000, 'level' => 4, 'is_postable' => true],
            ],
            'expenses' => [
                ['code' => '5.1.01.01', 'name' => 'Beban Operasional', 'balance' => 100000, 'level' => 4, 'is_postable' => true],
            ],
            'totals' => ['revenue' => 500000, 'expenses' => 100000, 'net_income' => 400000],
        ];

        $response = $this->excel->incomeStatement($data);

        self::assertInstanceOf(StreamedResponse::class, $response);
    }
}
