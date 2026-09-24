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

    public function simpanan(array $data): StreamedResponse
    {
        $w = new XlsxWriter;
        $w->addSheet('Daftar Simpanan');
        $w->setColumnWidths([8, 14, 30, 18, 18, 18, 18, 18]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period']['period_label'] ?? '';
        $w->addRow([$identity], [self::B]);
        $w->addRow(['Daftar Simpanan — '.$period], [self::B]);
        $w->addRow([]);

        // Per-kind summary block first — gives the recipient the headline
        // numbers (Pokok/Wajib/Sukarela/Berjangka) before the line items.
        $w->addRow(
            ['No', 'Jenis Simpanan', 'Jumlah Akun', 'Saldo Awal', 'Mutasi Debit', 'Mutasi Kredit', 'Saldo Akhir'],
            array_fill(0, 7, self::B),
        );

        $kindOrder = ['pokok', 'wajib', 'sukarela', 'berjangka', 'lainnya'];
        $no = 1;
        $byKind = $data['by_kind'] ?? [];
        $bucketsRendered = 0;
        foreach ($kindOrder as $kind) {
            $bucket = $byKind[$kind] ?? null;
            if ($bucket === null || (int) ($bucket['count'] ?? 0) === 0) {
                continue;
            }
            $w->addRow(
                [
                    $no++,
                    $bucket['label'] ?? '',
                    $bucket['count'] ?? 0,
                    $bucket['opening_balance'] ?? 0,
                    $bucket['period_debit'] ?? 0,
                    $bucket['period_credit'] ?? 0,
                    $bucket['closing_balance'] ?? 0,
                ],
                [self::D, self::D, self::D, self::R, self::R, self::R, self::R],
            );
            $bucketsRendered++;
        }

        $totals = $data['totals'] ?? [];
        $w->addRow(
            [
                '', 'TOTAL', '',
                $totals['opening_balance'] ?? 0,
                $totals['period_debit'] ?? 0,
                $totals['period_credit'] ?? 0,
                $totals['closing_balance'] ?? 0,
            ],
            [self::B, self::B, self::B, self::RB, self::RB, self::RB, self::RB],
        );

        if ($bucketsRendered === 0) {
            $w->addRow(['Belum ada akun simpanan untuk periode ini.'] ?? 'Belum ada akun simpanan untuk periode ini.', [self::B]);
        }

        // Detail rows by account.
        $w->addRow([]);
        $w->addRow(
            ['No', 'Kode Akun', 'Nama Akun', 'Jenis', 'Saldo Awal', 'Mutasi Debit', 'Mutasi Kredit', 'Saldo Akhir'],
            array_fill(0, 8, self::B),
        );

        $no = 1;
        $rows = $data['rows'] ?? [];
        if ($rows === []) {
            $w->addRow(['', 'Tidak ada akun simpanan.', '', '', '', '', '', ''], array_fill(0, 8, self::D));
        } else {
            foreach ($rows as $row) {
                $w->addRow(
                    [
                        $no++,
                        $row['code'] ?? '',
                        $row['name'] ?? '',
                        $row['kind_label'] ?? '',
                        $row['opening_balance'] ?? 0,
                        $row['period_debit'] ?? 0,
                        $row['period_credit'] ?? 0,
                        $row['closing_balance'] ?? 0,
                    ],
                    [self::D, self::D, self::D, self::D, self::R, self::R, self::R, self::R],
                );
            }
        }

        return $w->download($this->filename('daftar-simpanan', $data));
    }

    /**
     * E-Budgeting per Triwulan — Rencana vs Realisasi.
     *
     * @param  array<string, mixed>  $data  Output BudgetingReportService::build().
     */
    public function budgeting(array $data): StreamedResponse
    {
        $w = new XlsxWriter;
        $w->addSheet('E-Budgeting');
        $w->setColumnWidths([6, 14, 38, 18, 18, 18, 14, 18, 18, 18, 14]);

        $identity = (string) ($data['identity']['legal_name'] ?? '');
        $period = (string) ($data['period']['label'] ?? '');
        $range = (string) ($data['period']['range_label'] ?? '');
        $isYtd = (bool) ($data['period']['is_ytd'] ?? false);

        $w->addRow([$identity], [self::B]);
        $w->addRow(['E-Budgeting — '.$period], [self::B]);
        if ($range !== '') {
            $w->addRow([$range], [self::D]);
        }
        $w->addRow([]);

        // Header row
        $w->addRow(
            [
                'No',
                'Kode',
                'Nama Akun',
                'Anggaran Triwulan',
                'Realisasi Triwulan',
                'Selisih Triwulan',
                '% Triwulan',
                'Anggaran YTD',
                'Realisasi YTD',
                'Selisih YTD',
                '% YTD',
            ],
            array_fill(0, 11, self::B),
        );

        $no = 1;
        $rows = $data['rows'] ?? [];
        if ($rows === []) {
            $w->addRow(
                ['', 'Tidak ada akun anggaran untuk filter ini.', '', '', '', '', '', '', '', '', ''],
                array_fill(0, 11, self::D),
            );
        } else {
            $currentType = null;
            foreach ($rows as $row) {
                $type = (string) ($row['account_type'] ?? '');
                if ($type !== $currentType) {
                    $currentType = $type;
                    $label = $type === 'revenue' ? 'PENDAPATAN' : 'BEBAN';
                    $w->addRow(['', '', $label, '', '', '', '', '', '', '', ''], array_fill(0, 11, self::B));
                }

                $w->addRow(
                    [
                        $no++,
                        $row['code'] ?? '',
                        $row['name'] ?? '',
                        $row['budget_quarter'] ?? 0,
                        $row['realized_quarter'] ?? 0,
                        $row['variance'] ?? 0,
                        $row['realized_pct'] ?? 0,
                        $row['budget_ytd'] ?? 0,
                        $row['realized_ytd'] ?? 0,
                        $row['variance_ytd'] ?? 0,
                        $row['realized_pct_ytd'] ?? 0,
                    ],
                    [
                        self::D, self::D, self::D,
                        self::R, self::R, self::R, self::D,
                        self::R, self::R, self::R, self::D,
                    ],
                );
            }

            // Totals
            $totals = $data['totals'] ?? [];
            foreach (['revenue', 'expense', 'net'] as $key) {
                $t = $totals[$key] ?? null;
                if ($t === null) {
                    continue;
                }
                $label = $key === 'revenue' ? 'TOTAL PENDAPATAN' : ($key === 'expense' ? 'TOTAL BEBAN' : 'SURPLUS / (DEFISIT)');
                $w->addRow(
                    [
                        '', '', $label,
                        $t['budget_quarter'] ?? 0,
                        $t['realized_quarter'] ?? 0,
                        $t['variance'] ?? 0,
                        $t['realized_pct'] ?? 0,
                        $t['budget_ytd'] ?? 0,
                        $t['realized_ytd'] ?? 0,
                        $t['variance_ytd'] ?? 0,
                        $t['realized_pct_ytd'] ?? 0,
                    ],
                    array_fill(0, 3, self::B) + array_fill(3, 4, self::RB) + array_fill(7, 4, self::RB),
                );
            }
        }

        $year = (int) ($data['period']['year'] ?? date('Y'));
        $suffix = $isYtd
            ? sprintf('ytd-%04d', $year)
            : sprintf('%04d-q%d', $year, (int) ($data['period']['quarter'] ?? 1));

        return $w->download("e-budgeting-{$suffix}.xlsx");
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

    /**
     * Daftar pinjaman per-tahapan pipeline (Proposal / Verifikasi / Waiting List).
     *
     * @param  array<string, mixed>  $data  Output StageListReportService::build().
     */
    public function stageList(array $data, string $slug): StreamedResponse
    {
        $w = new XlsxWriter;
        $title = $data['stage_title'] ?? 'Daftar Pinjaman';
        $sheet = mb_substr($title, 0, 31);
        $w->addSheet($sheet);
        $w->setColumnWidths([5, 12, 18, 28, 22, 22, 12, 14, 18, 18, 22]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period_label'] ?? '';
        $stageDateLabel = $data['stage_date_label'] ?? 'Tanggal';

        $w->addRow([$identity], [self::B]);
        $w->addRow([$title.' — '.$period], [self::B]);
        $w->addRow([]);

        $w->addRow(
            ['No', 'Loan ID', 'Nomor Pinjaman', 'Peminjam', 'Desa', 'Produk', 'Jenis', 'Pokok Pinjaman', $stageDateLabel, 'Status', 'Kolektibilitas'],
            array_fill(0, 11, self::B),
        );

        foreach ($data['rows'] ?? [] as $row) {
            $w->addRow(
                [
                    $row['no'] ?? '',
                    '#'.($row['loan_id'] ?? ''),
                    $row['loan_number'] ?? '',
                    trim(($row['borrower_name'] ?? '').' '.($row['borrower_code'] !== '' && $row['borrower_code'] !== null ? '('.$row['borrower_code'].')' : '')),
                    $row['village_name'] ?? '',
                    ($row['product_code'] ?? '').' '.($row['product_name'] ?? ''),
                    $row['borrower_kind'] ?? '',
                    $row['principal_amount'] ?? 0,
                    $row['stage_date_label'] ?? '',
                    $row['status_label'] ?? '',
                    $row['collector'] ?? '',
                ],
                [self::D, self::D, self::D, self::D, self::D, self::D, self::D, self::R, self::D, self::D, self::D],
            );
        }

        $t = $data['totals'] ?? [];
        $w->addRow([]);
        $w->addRow(
            ['', '', '', 'TOTAL', '', '', '', $t['principal_amount'] ?? 0, '', '', ''],
            [self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::RB, self::B, self::B, self::B],
        );
        $w->addRow(
            ['', '', 'Jumlah pinj', $t['count'] ?? 0, 'Kelompok', $t['group_count'] ?? 0, 'Individu', $t['member_count'] ?? 0, 'Pemanfaat', $t['beneficiary_count'] ?? 0, ''],
            [self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::B],
        );

        return $w->download(sprintf('%s-%s.xlsx', $slug, $t !== [] && isset($data['year'], $data['month']) && $data['month']
            ? sprintf('%04d-%02d', $data['year'], $data['month'])
            : ($data['year'] ?? date('Y'))));
    }

    /**
     * Pinjaman Dihapusbukukan (write-off) — Kelompok & Individu.
     *
     * @param  array<string, mixed>  $data  Output WriteOffsReportService::buildReport() / WriteOffsIndividuReportService::buildReport()
     * @param  string  $slug  Filename slug (e.g. 'pinjaman-dihapusbukukan-kelompok')
     * @param  string  $borrowerHeader  Header untuk kolom identitas peminjam/kelompok ('Kelompok' / 'Peminjam')
     * @param  string  $borrowerLabelFn  Closure name resolution via property path: 'group_name' or 'member_name'
     */
    public function writeOffs(array $data, string $slug, string $borrowerHeader = 'Kelompok', string $borrowerNameKey = 'group_name'): StreamedResponse
    {
        $w = new XlsxWriter;
        $title = $data['title'] ?? 'DAFTAR PINJAMAN DIHAPUSBUKUKAN';
        $sheet = mb_substr($title, 0, 31);
        $w->addSheet($sheet);
        $w->setColumnWidths([5, 12, 18, 28, 18, 16, 18, 18, 18, 18, 14, 30]);

        $identity = $data['identity']['legal_name'] ?? '';
        $period = $data['period_label'] ?? '';

        $w->addRow([$identity], [self::B]);
        $w->addRow([$title.' — '.$period], [self::B]);
        $w->addRow([]);

        $w->addRow(
            ['No', 'Loan ID', 'Nomor Pinjaman', $borrowerHeader, 'Desa', 'Produk', 'Pokok Pinjaman', 'Sisa Pokok', 'Cadangan CKPN', 'Nilai Bersih', 'Tgl Hapus', 'Alasan Hapus'],
            array_fill(0, 12, self::B),
        );

        foreach ($data['rows'] ?? [] as $row) {
            $w->addRow(
                [
                    $row['no'] ?? '',
                    '#'.($row['loan_id'] ?? ''),
                    $row['loan_number'] ?? '',
                    (string) ($row[$borrowerNameKey] ?? ''),
                    $row['village_name'] ?? '',
                    trim(($row['product_code'] ?? '').' '.($row['product_name'] ?? '')),
                    (float) ($row['principal_amount'] ?? 0),
                    (float) ($row['sisa_pokok'] ?? 0),
                    (float) ($row['ckpn'] ?? 0),
                    (float) ($row['nilai_bersih'] ?? 0),
                    $row['written_off_at_label'] ?? '—',
                    (string) ($row['reason'] ?? ''),
                ],
                [self::D, self::D, self::D, self::D, self::D, self::D, self::R, self::R, self::R, self::R, self::D, self::D],
            );
        }

        $t = $data['totals'] ?? [];
        $w->addRow([]);
        $w->addRow(
            ['', '', '', 'TOTAL', '', '', $t['principal_total'] ?? 0, $t['sisa_pokok_total'] ?? 0, $t['ckpn_total'] ?? 0, $t['nilai_bersih_total'] ?? 0, '', ''],
            [self::B, self::B, self::B, self::B, self::B, self::B, self::RB, self::RB, self::RB, self::RB, self::B, self::B],
        );

        $suffix = isset($data['year'], $data['month']) && $data['month']
            ? sprintf('%04d-%02d', $data['year'], $data['month'])
            : sprintf('%04d', $data['year'] ?? date('Y'));

        return $w->download(sprintf('%s-%s.xlsx', $slug, $suffix));
    }
}
