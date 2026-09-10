<?php

declare(strict_types=1);

namespace App\Support\Excel;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds Excel (.xlsx) exports for accounting reports.
 *
 * Every public method receives the same data array that the corresponding
 * report service returns, and streams an XLSX download with Rupiah-formatted
 * monetary columns.
 */
final class ReportExcel
{
    private const R = XlsxWriter::STYLE_RUPIAH;

    private const RB = XlsxWriter::STYLE_RUPIAH_BOLD;

    private const B = XlsxWriter::STYLE_BOLD;

    private const D = XlsxWriter::STYLE_DEFAULT;

    public function trialBalance(array $data): StreamedResponse
    {
        $w = new XlsxWriter;
        $w->addSheet('Neraca Saldo');
        $w->setColumnWidths([8, 12, 35, 18, 18, 18, 18, 18, 18]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period']['label'] ?? '';
        $w->addRow([$identity], [self::B]);
        $w->addRow(['Neraca Saldo — '.$period], [self::B]);
        $w->addRow([]);

        $w->addRow(
            ['No', 'Kode', 'Nama Akun', 'NS Debit', 'NS Kredit', 'L/R Debit', 'L/R Kredit', 'Neraca Debit', 'Neraca Kredit'],
            [self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::B],
        );

        $no = 1;
        foreach ($data['rows'] ?? [] as $row) {
            $w->addRow(
                [$no++, $row['code'], $row['name'], $row['ns_debit'], $row['ns_credit'], $row['lr_debit'], $row['lr_credit'], $row['bs_debit'], $row['bs_credit']],
                [self::D, self::D, self::D, self::R, self::R, self::R, self::R, self::R, self::R],
            );
        }

        $t = $data['totals'] ?? [];
        $w->addRow(
            ['', '', 'TOTAL', $t['ns_debit'] ?? 0, $t['ns_credit'] ?? 0, $t['lr_debit'] ?? 0, $t['lr_credit'] ?? 0, $t['bs_debit'] ?? 0, $t['bs_credit'] ?? 0],
            [self::B, self::B, self::RB, self::RB, self::RB, self::RB, self::RB, self::RB, self::RB],
        );

        return $w->download($this->filename('neraca-saldo', $data));
    }

    public function balanceSheet(array $data): StreamedResponse
    {
        $w = new XlsxWriter;
        $w->addSheet('Neraca');
        $w->setColumnWidths([8, 12, 40, 18]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period']['label'] ?? '';
        $w->addRow([$identity], [self::B]);
        $w->addRow(['Neraca — '.$period], [self::B]);
        $w->addRow([]);

        $w->addRow(['No', 'Kode', 'Nama Akun', 'Saldo'], [self::B, self::B, self::B, self::B]);

        $no = 1;
        foreach (['assets', 'liabilities', 'equity'] as $section) {
            $sectionData = $data[$section] ?? [];
            foreach ($sectionData as $row) {
                $isHeader = ($row['level'] ?? 1) <= 2 && ! ($row['is_postable'] ?? true);
                $style = $isHeader ? self::RB : self::R;
                $labelStyle = $isHeader ? self::B : self::D;
                $indent = str_repeat('  ', max(0, ($row['level'] ?? 1) - 1));
                $w->addRow(
                    [$isHeader ? '' : $no++, $row['code'] ?? '', $indent.($row['name'] ?? ''), $row['balance'] ?? 0],
                    [$labelStyle, $labelStyle, $labelStyle, $style],
                );
            }
        }

        $totals = $data['totals'] ?? [];
        $w->addRow([]);
        $w->addRow(['', '', 'Total Aset', $totals['assets'] ?? 0], [self::B, self::B, self::RB, self::RB]);
        $w->addRow(['', '', 'Total Kewajiban + Ekuitas', $totals['liabilities_equity'] ?? 0], [self::B, self::B, self::RB, self::RB]);

        return $w->download($this->filename('neraca', $data));
    }

    public function incomeStatement(array $data): StreamedResponse
    {
        $w = new XlsxWriter;
        $w->addSheet('Laba Rugi');
        $w->setColumnWidths([8, 12, 40, 18]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period']['label'] ?? '';
        $w->addRow([$identity], [self::B]);
        $w->addRow(['Laba Rugi — '.$period], [self::B]);
        $w->addRow([]);

        $w->addRow(['No', 'Kode', 'Nama Akun', 'Jumlah'], [self::B, self::B, self::B, self::B]);

        $no = 1;
        foreach (['revenue', 'expenses'] as $section) {
            foreach ($data[$section] ?? [] as $row) {
                $isHeader = ($row['level'] ?? 1) <= 2 && ! ($row['is_postable'] ?? true);
                $style = $isHeader ? self::RB : self::R;
                $labelStyle = $isHeader ? self::B : self::D;
                $indent = str_repeat('  ', max(0, ($row['level'] ?? 1) - 1));
                $w->addRow(
                    [$isHeader ? '' : $no++, $row['code'] ?? '', $indent.($row['name'] ?? ''), $row['balance'] ?? 0],
                    [$labelStyle, $labelStyle, $labelStyle, $style],
                );
            }
        }

        $totals = $data['totals'] ?? [];
        $w->addRow([]);
        $w->addRow(['', '', 'Total Pendapatan', $totals['revenue'] ?? 0], [self::B, self::B, self::RB, self::RB]);
        $w->addRow(['', '', 'Total Beban', $totals['expenses'] ?? 0], [self::B, self::B, self::RB, self::RB]);
        $w->addRow(['', '', 'Surplus / Defisit', $totals['net_income'] ?? 0], [self::B, self::B, self::RB, self::RB]);

        return $w->download($this->filename('laba-rugi', $data));
    }

    public function cashFlow(array $data): StreamedResponse
    {
        $w = new XlsxWriter;
        $w->addSheet('Arus Kas');
        $w->setColumnWidths([40, 18]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period']['label'] ?? '';
        $w->addRow([$identity], [self::B]);
        $w->addRow(['Laporan Arus Kas — '.$period], [self::B]);
        $w->addRow([]);

        $w->addRow(['Uraian', 'Jumlah'], [self::B, self::B]);

        foreach ($data['sections'] ?? [] as $section) {
            $w->addRow([$section['title'] ?? ''], [self::B]);
            foreach ($section['items'] ?? [] as $item) {
                $w->addRow(['  '.($item['label'] ?? ''), $item['amount'] ?? 0], [self::D, self::R]);
            }
            $w->addRow(['  Subtotal '.($section['title'] ?? ''), $section['subtotal'] ?? 0], [self::B, self::RB]);
            $w->addRow([]);
        }

        $totals = $data['totals'] ?? [];
        $w->addRow(['Kenaikan/Penurunan Kas Bersih', $totals['net_change'] ?? 0], [self::B, self::RB]);
        $w->addRow(['Saldo Kas Awal', $totals['opening_cash'] ?? 0], [self::B, self::RB]);
        $w->addRow(['Saldo Kas Akhir', $totals['closing_cash'] ?? 0], [self::B, self::RB]);

        return $w->download($this->filename('arus-kas', $data));
    }

    public function equityChange(array $data): StreamedResponse
    {
        $w = new XlsxWriter;
        $w->addSheet('Perubahan Ekuitas');
        $w->setColumnWidths([40, 18]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period']['label'] ?? '';
        $w->addRow([$identity], [self::B]);
        $w->addRow(['Laporan Perubahan Ekuitas — '.$period], [self::B]);
        $w->addRow([]);

        $w->addRow(['Uraian', 'Jumlah'], [self::B, self::B]);

        foreach ($data['rows'] ?? [] as $row) {
            $isTotal = $row['is_total'] ?? false;
            $w->addRow(
                [$row['label'] ?? '', $row['amount'] ?? 0],
                [$isTotal ? self::B : self::D, $isTotal ? self::RB : self::R],
            );
        }

        return $w->download($this->filename('perubahan-ekuitas', $data));
    }

    public function journals(array $data): StreamedResponse
    {
        $w = new XlsxWriter;
        $w->addSheet('Jurnal');
        $w->setColumnWidths([6, 14, 12, 30, 18, 18]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period']['label'] ?? '';
        $w->addRow([$identity], [self::B]);
        $w->addRow(['Laporan Jurnal — '.$period], [self::B]);
        $w->addRow([]);

        $w->addRow(
            ['No', 'Tanggal', 'Kode Akun', 'Keterangan', 'Debit', 'Kredit'],
            [self::B, self::B, self::B, self::B, self::B, self::B],
        );

        $no = 1;
        foreach ($data['entries'] ?? [] as $entry) {
            foreach ($entry['lines'] ?? [] as $lineIdx => $line) {
                $w->addRow(
                    [
                        $lineIdx === 0 ? $no : '',
                        $lineIdx === 0 ? ($entry['transaction_date'] ?? '') : '',
                        $line['account_code'] ?? '',
                        $lineIdx === 0 ? ($entry['description'] ?? '') : ('  '.($line['account_name'] ?? '')),
                        $line['debit'] ?? 0,
                        $line['credit'] ?? 0,
                    ],
                    [self::D, self::D, self::D, self::D, self::R, self::R],
                );
            }
            $no++;
        }

        $totals = $data['totals'] ?? [];
        $w->addRow(
            ['', '', '', 'TOTAL', $totals['debit'] ?? 0, $totals['credit'] ?? 0],
            [self::B, self::B, self::B, self::RB, self::RB, self::RB],
        );

        return $w->download($this->filename('jurnal', $data));
    }

    public function generalLedger(array $data): StreamedResponse
    {
        $w = new XlsxWriter;
        $account = $data['account'] ?? [];
        $sheetName = mb_substr(($account['code'] ?? '').' '.($account['name'] ?? 'Buku Besar'), 0, 31);
        $w->addSheet($sheetName);
        $w->setColumnWidths([6, 14, 14, 30, 18, 18, 18]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period']['label'] ?? '';
        $w->addRow([$identity], [self::B]);
        $w->addRow(['Buku Besar — '.($account['code'] ?? '').' '.($account['name'] ?? '').' — '.$period], [self::B]);
        $w->addRow([]);

        if (isset($data['opening'])) {
            $w->addRow(['', '', '', 'Saldo Awal', '', '', $data['opening']['balance'] ?? 0], [self::B, self::B, self::B, self::B, self::B, self::B, self::RB]);
        }

        $w->addRow(
            ['No', 'Tanggal', 'ID Transaksi', 'Keterangan', 'Debit', 'Kredit', 'Saldo'],
            [self::B, self::B, self::B, self::B, self::B, self::B, self::B],
        );

        foreach ($data['rows'] ?? [] as $idx => $row) {
            $w->addRow(
                [$idx + 1, $row['transaction_date'] ?? '', $row['entry_id'] ?? '', $row['description'] ?? '', $row['debit'] ?? 0, $row['credit'] ?? 0, $row['running_balance'] ?? 0],
                [self::D, self::D, self::D, self::D, self::R, self::R, self::R],
            );
        }

        $totals = $data['totals'] ?? [];
        $w->addRow(
            ['', '', '', 'TOTAL', $totals['debit'] ?? 0, $totals['credit'] ?? 0, $totals['closing_balance'] ?? 0],
            [self::B, self::B, self::B, self::RB, self::RB, self::RB, self::RB],
        );

        return $w->download($this->filename('buku-besar', $data));
    }

    public function fixedAssets(array $data, string $type = 'tangible'): StreamedResponse
    {
        $w = new XlsxWriter;
        $title = $type === 'intangible' ? 'Aset Tak Berwujud' : 'Aset Tetap';
        $w->addSheet('Rekapitulasi '.$title);
        $w->setColumnWidths([6, 14, 30, 14, 10, 6, 16, 18, 6, 16, 18, 18, 18]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period_label'] ?? '';
        $w->addRow([$identity], [self::B]);
        $w->addRow(['Rekapitulasi '.$title.' — '.$period], [self::B]);
        $w->addRow([]);

        $w->addRow(
            ['No', 'Kode', 'Nama', 'Tgl Perolehan', 'Kondisi', 'Qty', 'Harga Satuan', 'Harga Perolehan', 'UE (bln)', 'Pnytn/bln', 'Pnytn Thn Ini', 'Akm. Pnytn', 'Nilai Buku'],
            array_fill(0, 13, self::B),
        );

        foreach ($data['categories'] ?? [] as $cat) {
            $w->addRow([$cat['category_code'].' — '.$cat['category_name']], [self::B]);

            foreach ($cat['assets'] ?? [] as $asset) {
                $w->addRow(
                    [
                        $asset['no'], $asset['asset_code'], $asset['name'], $asset['purchased_at'],
                        $asset['condition'], $asset['unit'], $asset['unit_cost'], $asset['acquisition'],
                        $asset['useful_life_months'], $asset['monthly_depreciation'],
                        $asset['depreciation_year'], $asset['accumulated_depreciation'], $asset['book_value'],
                    ],
                    [self::D, self::D, self::D, self::D, self::D, self::D, self::R, self::R, self::D, self::R, self::R, self::R, self::R],
                );
            }

            $ct = $cat['totals'];
            $w->addRow(
                ['', '', 'Subtotal '.$cat['category_name'], '', '', $ct['unit'], '', $ct['acquisition'], '', '', $ct['depreciation_year'], $ct['depreciation_accumulated'], $ct['book_value']],
                [self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::RB, self::B, self::B, self::RB, self::RB, self::RB],
            );
        }

        $gt = $data['totals'] ?? [];
        $w->addRow([]);
        $w->addRow(
            ['', '', 'GRAND TOTAL', '', '', $gt['unit'] ?? 0, '', $gt['acquisition'] ?? 0, '', '', $gt['depreciation_year'] ?? 0, $gt['depreciation_accumulated'] ?? 0, $gt['book_value'] ?? 0],
            [self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::RB, self::B, self::B, self::RB, self::RB, self::RB],
        );

        $slug = $type === 'intangible' ? 'aset-tak-berwujud' : 'aset-tetap';

        return $w->download($this->filename($slug, $data));
    }

    private function filename(string $slug, array $data): string
    {
        $year = $data['period']['year'] ?? ($data['year'] ?? date('Y'));
        $month = $data['period']['month'] ?? ($data['month'] ?? null);
        $suffix = $month ? sprintf('%04d-%02d', $year, $month) : sprintf('%04d', $year);

        return "{$slug}-{$suffix}.xlsx";
    }
}
