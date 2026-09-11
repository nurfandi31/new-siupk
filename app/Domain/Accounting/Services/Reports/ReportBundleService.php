<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Support\ReportPdf;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

final class ReportBundleService
{
    public function __construct(
        private readonly ReportPdf $pdf,
    ) {}

    private int $skippedAccounts = 0;

    /**
     * @return array{filename: string, path: string, size: int, files: list<array{name: string, label: string}>}
     */
    public function build(int $year, int $month): array
    {
        set_time_limit(0);

        $files = [
            ...$this->financialReports($year, $month),
            ...$this->generalLedgerReports($year, $month),
            ...$this->annualPackReports($year, $month),
        ];
        $filename = sprintf('bundle-laporan-%s%04d-%02d.pdf.zip', $this->tenantSlug(), $year, $month);
        $path = $this->directory().'/'.$filename;

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat bundle laporan.');
        }

        try {
            if ($zip->addFromString('README-bundle.txt', $this->manifest($year, $month, $files)) !== true) {
                throw new RuntimeException('Gagal menambahkan manifest bundle.');
            }

            foreach ($files as $file) {
                if ($zip->addFile($file['path'], $file['name']) !== true) {
                    throw new RuntimeException('Gagal menambahkan laporan ke bundle.');
                }
            }

            if ($zip->close() !== true) {
                throw new RuntimeException('Gagal menyimpan bundle laporan.');
            }
        } catch (Throwable $exception) {
            $zip->close();

            throw $exception;
        }

        clearstatcache(true, $path);

        return [
            'filename' => $filename,
            'path' => $path,
            'size' => (int) filesize($path),
            'files' => array_map(
                static fn (array $file): array => [
                    'name' => (string) $file['name'],
                    'label' => (string) $file['label'],
                ],
                $files,
            ),
        ];
    }

    public function download(int $year, int $month): BinaryFileResponse
    {
        $bundle = $this->build($year, $month);
        $response = response()->download($bundle['path'], $bundle['filename'], ['Content-Type' => 'application/zip']);

        if ($response instanceof BinaryFileResponse) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    /**
     * @return list<array{name: string, label: string, path: string}>
     */
    private function financialReports(int $year, int $month): array
    {
        $reports = [
            ['service' => app(BalanceSheetService::class), 'view' => 'reports.pdf.balance_sheet', 'name' => sprintf('neraca-%04d-%02d.pdf', $year, $month), 'label' => 'Neraca', 'orientation' => 'portrait'],
            ['service' => app(IncomeStatementService::class), 'view' => 'reports.pdf.income_statement', 'name' => sprintf('laba-rugi-%04d-%02d.pdf', $year, $month), 'label' => 'Laba Rugi', 'orientation' => 'portrait'],
            ['service' => app(CashFlowService::class), 'view' => 'reports.pdf.cash_flow', 'name' => sprintf('arus-kas-%04d-%02d.pdf', $year, $month), 'label' => 'Arus Kas', 'orientation' => 'portrait'],
            ['service' => app(EquityChangeService::class), 'view' => 'reports.pdf.equity_change', 'name' => sprintf('perubahan-ekuitas-%04d-%02d.pdf', $year, $month), 'label' => 'Perubahan Ekuitas', 'orientation' => 'portrait'],
            ['service' => app(CalkService::class), 'view' => 'reports.pdf.calk', 'name' => sprintf('calk-%04d-%02d.pdf', $year, $month), 'label' => 'CALK', 'orientation' => 'portrait'],
            ['service' => app(TrialBalanceService::class), 'view' => 'reports.pdf.trial_balance', 'name' => sprintf('neraca-saldo-%04d-%02d.pdf', $year, $month), 'label' => 'Neraca Saldo', 'orientation' => 'portrait'],
            ['service' => app(JournalListingService::class), 'view' => 'reports.pdf.journal', 'name' => sprintf('jurnal-transaksi-%04d-%02d.pdf', $year, $month), 'label' => 'Jurnal Transaksi', 'orientation' => 'portrait'],
        ];

        return array_map(
            function (array $report) use ($year, $month): array {
                $data = $report['service']->build($year, $month);

                return $this->render(
                    $data,
                    (string) $report['view'],
                    (string) $report['name'],
                    (string) $report['label'],
                    (string) $report['orientation'],
                );
            },
            $reports,
        );
    }

    private function generalLedgerReports(int $year, int $month): array
    {
        $accounts = Account::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name']);

        if ($accounts->isEmpty()) {
            throw new RuntimeException('Tidak ada akun aktif untuk membuat Buku Besar.');
        }

        $service = app(GeneralLedgerService::class);
        $files = [];
        $skipped = 0;

        foreach ($accounts as $account) {
            try {
                $data = $service->build($year, $month, (int) $account->row_id);
            } catch (InvalidArgumentException) {
                $skipped++;

                continue;
            }

            $hasMovements = ($data['rows'] ?? []) !== [];
            $opening = (float) ($data['opening']['balance'] ?? 0);
            $totals = $data['totals'] ?? [];
            $periodTotals = $totals['period'] ?? [];
            $periodDebit = (float) ($periodTotals['debit'] ?? 0);
            $periodCredit = (float) ($periodTotals['credit'] ?? 0);

            if (! $hasMovements && $opening === 0.0 && $periodDebit === 0.0 && $periodCredit === 0.0) {
                $skipped++;

                continue;
            }

            $files[] = $this->render(
                $data,
                'reports.pdf.general_ledger',
                sprintf('buku-besar-%s-%04d-%02d.pdf', $account->code, $year, $month),
                sprintf('Buku Besar %s · %s', $account->code, $account->name),
                'portrait',
            );
        }

        $this->skippedAccounts = $skipped;

        return $files;
    }

    /**
     * @return list<array{name: string, label: string, path: string}>
     */
    private function annualPackReports(int $year, int $month): array
    {
        $annualPack = app(AnnualReportPackService::class);
        $documents = [
            ['type' => 'cover', 'view' => 'reports.pdf.annual.cover', 'name' => sprintf('cover-%04d.pdf', $year), 'label' => 'Cover LPJ'],
            ['type' => 'surat_pengantar', 'view' => 'reports.pdf.annual.surat_pengantar', 'name' => sprintf('surat-pengantar-%04d.pdf', $year), 'label' => 'Surat Pengantar LPJ'],
            ['type' => 'ba_pergantian', 'view' => 'reports.pdf.annual.ba_pergantian', 'name' => sprintf('berita-acara-%04d.pdf', $year), 'label' => 'Berita Acara LPJ'],
            ['type' => 'mou', 'view' => 'reports.pdf.annual.mou', 'name' => sprintf('mou-%04d.pdf', $year), 'label' => 'MoU Antar Desa'],
        ];

        return array_map(
            fn (array $document): array => $this->render(
                $annualPack->build($year, $month, (string) $document['type']),
                (string) $document['view'],
                (string) $document['name'],
                (string) $document['label'],
                'portrait',
            ),
            $documents,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, label: string, path: string}
     */
    private function render(array $data, string $view, string $name, string $label, string $orientation): array
    {
        $path = $this->directory().'/'.$name;
        file_put_contents($path, (string) $this->pdf->download($view, $data, $name, $orientation)->getContent());

        return ['name' => $name, 'label' => $label, 'path' => $path];
    }

    /**
     * @param  list<array{name: string, label: string, path: string}>  $files
     */
    private function manifest(int $year, int $month, array $files): string
    {
        $profile = OrganizationProfile::query()->first();
        $tenantName = (string) ($profile?->legal_name ?: $profile?->short_name ?: 'siupk');
        $lines = [
            'BUNDLE LAPORAN KEUANGAN & LPJ',
            '================================',
            'Tenant: '.$tenantName,
            'Periode: '.$year.'-'.$month,
            'Tanggal generate: '.now()->format('Y-m-d H:i:s'),
            '',
            'Daftar isi:',
        ];

        foreach ($files as $file) {
            $lines[] = '- '.$file['name'].' — '.$file['label'];
        }

        $lines[] = '';
        $lines[] = 'Akun tanpa mutasi dilewati: '.$this->skippedAccounts;

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function tenantSlug(): string
    {
        $profile = OrganizationProfile::query()->first();
        $raw = (string) ($profile?->short_name ?: $profile?->legal_name ?: '');
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($raw)) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? '' : $slug.'-';
    }

    private function directory(): string
    {
        $path = storage_path('app/report-bundles');
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException('Gagal membuat direktori bundle laporan.');
        }

        return $path;
    }
}
