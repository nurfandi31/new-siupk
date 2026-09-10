<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Accounting\Services\Reports\AnnualReportPackService;
use App\Domain\Accounting\Services\Reports\BalanceSheetService;
use App\Domain\Accounting\Services\Reports\CalkService;
use App\Domain\Accounting\Services\Reports\CashFlowService;
use App\Domain\Accounting\Services\Reports\EquityChangeService;
use App\Domain\Accounting\Services\Reports\FinancialHealthService;
use App\Domain\Accounting\Services\Reports\GeneralLedgerService;
use App\Domain\Accounting\Services\Reports\IncomeStatementService;
use App\Domain\Accounting\Services\Reports\JournalListingService;
use App\Domain\Accounting\Services\Reports\TrialBalanceService;
use App\Domain\Assets\Services\AssetReportService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\User;
use App\Support\Excel\ReportExcel;
use App\Support\ReportPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly TrialBalanceService $trialBalance,
        private readonly BalanceSheetService $balanceSheet,
        private readonly IncomeStatementService $incomeStatement,
        private readonly GeneralLedgerService $generalLedger,
        private readonly JournalListingService $journals,
        private readonly CashFlowService $cashFlow,
        private readonly EquityChangeService $equityChange,
        private readonly CalkService $calk,
        private readonly FinancialHealthService $financialHealthService,
        private readonly AssetReportService $assetReportService,
        private readonly AnnualReportPackService $annualReportPack,
        private readonly ReportPdf $pdf,
        private readonly ReportExcel $excel,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);

        return Inertia::render('Accounting/Reports/Index', [
            'reports' => [
                ['key' => 'loan-portfolio', 'title' => 'Portofolio Pinjaman', 'href' => '/lending/reports/portfolio', 'icon' => 'payments'],
                ['key' => 'schedule-vs-actual', 'title' => 'Rencana vs Realisasi', 'href' => '/lending/reports/schedule-vs-actual', 'icon' => 'compare_arrows'],
                ['key' => 'lpp-desa', 'title' => 'LPP Rekap Desa', 'href' => '/lending/reports/lpp-desa', 'icon' => 'holiday_village'],
                ['key' => 'lpp-kelompok', 'title' => 'LPP Rincian Kelompok', 'href' => '/lending/reports/lpp-kelompok', 'icon' => 'groups_2'],
                ['key' => 'kolek-desa', 'title' => 'Kolektibilitas Pinjaman', 'href' => '/lending/reports/kolek-desa', 'icon' => 'pie_chart'],
                ['key' => 'cadangan-penghapusan', 'title' => 'Cadangan Kerugian (CKPN)', 'href' => '/lending/reports/cadangan-penghapusan', 'icon' => 'shield'],
                ['key' => 'financial-health', 'title' => 'Penilaian Kesehatan Usaha', 'href' => '/accounting/reports/financial-health', 'icon' => 'health_and_safety'],
                ['key' => 'journals', 'title' => 'Jurnal Transaksi', 'href' => '/accounting/reports/journals', 'icon' => 'receipt_long'],
                ['key' => 'trial-balance', 'title' => 'Neraca Saldo', 'href' => '/accounting/reports/trial-balance', 'icon' => 'table_chart'],
                ['key' => 'balance-sheet', 'title' => 'Neraca', 'href' => '/accounting/reports/balance-sheet', 'icon' => 'account_balance'],
                ['key' => 'income-statement', 'title' => 'Laba Rugi', 'href' => '/accounting/reports/income-statement', 'icon' => 'trending_up'],
                ['key' => 'cash-flow', 'title' => 'Arus Kas', 'href' => '/accounting/reports/cash-flow', 'icon' => 'water_drop'],
                ['key' => 'equity-change', 'title' => 'Perubahan Ekuitas', 'href' => '/accounting/reports/equity-change', 'icon' => 'account_balance_wallet'],
                ['key' => 'calk', 'title' => 'CALK', 'href' => '/accounting/reports/calk', 'icon' => 'description'],
                ['key' => 'general-ledger', 'title' => 'Buku Besar', 'href' => '/accounting/reports/general-ledger', 'icon' => 'menu_book'],
                ['key' => 'fixed-assets-pdf', 'title' => 'Rekapitulasi Aset Tetap (PDF)', 'href' => '/accounting/reports/assets/fixed/pdf', 'icon' => 'domain', 'external' => true],
                ['key' => 'annual-pack', 'title' => 'Dokumen LPJ & Cover Tahunan', 'href' => '/accounting/reports/annual-pack', 'icon' => 'auto_stories'],
            ],
        ]);
    }

    public function journals(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request);
        $day = $this->optionalDay($request);
        $page = max(1, (int) $request->query('page', 1));

        return Inertia::render('Accounting/Reports/Journal', [
            ...$this->journals->build($year, $month, $day, $page),
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all', 'day' => $day, 'page' => $page],
        ]);
    }

    public function journalsPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request);
        $data = $this->journals->build($year, $month, $this->optionalDay($request), 1, 50, true);

        return $this->pdf->stream(
            'reports.pdf.journal',
            $data,
            'jurnal-transaksi-'.$year.($month ? '-'.$month : '').'.pdf',
        );
    }

    public function trialBalance(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));

        return Inertia::render('Accounting/Reports/TrialBalance', [
            ...$this->trialBalance->build($year, $month),
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function trialBalancePdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->trialBalance->build($year, $month);

        return $this->pdf->stream(
            'reports.pdf.trial_balance',
            $data,
            'neraca-saldo-'.$year.($month ? '-'.$month : '').'.pdf',
        );
    }

    public function balanceSheet(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));

        return Inertia::render('Accounting/Reports/BalanceSheet', [
            ...$this->balanceSheet->build($year, $month),
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function balanceSheetPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->balanceSheet->build($year, $month);

        return $this->pdf->stream(
            'reports.pdf.balance_sheet',
            $data,
            'neraca-'.$year.($month ? '-'.$month : '').'.pdf',
        );
    }

    public function incomeStatement(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));

        return Inertia::render('Accounting/Reports/IncomeStatement', [
            ...$this->incomeStatement->build($year, $month),
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function incomeStatementPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->incomeStatement->build($year, $month);

        return $this->pdf->stream(
            'reports.pdf.income_statement',
            $data,
            'laba-rugi-'.$year.($month ? '-'.$month : '').'.pdf',
        );
    }

    public function cashFlow(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));

        return Inertia::render('Accounting/Reports/CashFlow', [
            ...$this->cashFlow->build($year, $month),
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function cashFlowPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->cashFlow->build($year, $month);

        return $this->pdf->stream(
            'reports.pdf.cash_flow',
            $data,
            'arus-kas-'.$year.($month ? '-'.$month : '').'.pdf',
        );
    }

    public function equityChange(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));

        return Inertia::render('Accounting/Reports/EquityChange', [
            ...$this->equityChange->build($year, $month),
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function equityChangePdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->equityChange->build($year, $month);

        return $this->pdf->stream(
            'reports.pdf.equity_change',
            $data,
            'perubahan-ekuitas-'.$year.($month ? '-'.$month : '').'.pdf',
        );
    }

    public function calk(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));

        return Inertia::render('Accounting/Reports/Calk', [
            ...$this->calk->build($year, $month),
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function calkPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->calk->build($year, $month);

        return $this->pdf->stream(
            'reports.pdf.calk',
            $data,
            'calk-'.$year.($month ? '-'.$month : '').'.pdf',
        );
    }

    public function saveCalkNotes(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'basis_notes' => ['nullable', 'string', 'max:5000'],
            'receivable_notes' => ['nullable', 'string', 'max:5000'],
            'events_after_notes' => ['nullable', 'string', 'max:5000'],
            'other_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->calk->saveNotes(
            (int) $validated['year'],
            isset($validated['month']) ? (int) $validated['month'] : null,
            [
                'basis_notes' => $validated['basis_notes'] ?? null,
                'receivable_notes' => $validated['receivable_notes'] ?? null,
                'events_after_notes' => $validated['events_after_notes'] ?? null,
                'other_notes' => $validated['other_notes'] ?? null,
            ],
        );

        return back()->with('success', 'Catatan CALK berhasil disimpan.');
    }

    public function generalLedger(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $day = $this->optionalDay($request);
        $accountId = (int) $request->query('account', 0);

        if ($accountId <= 0) {
            return Inertia::render('Accounting/Reports/GeneralLedger', [
                ...$this->generalLedgerEmpty($year, $month),
                'monthLabels' => $this->monthLabels(),
                'filters' => ['year' => $year, 'month' => $month ?? 'all', 'day' => $day, 'account' => null],
            ]);
        }

        try {
            $payload = $this->generalLedger->build($year, $month, $accountId, $day);
        } catch (InvalidArgumentException $e) {
            return Inertia::render('Accounting/Reports/GeneralLedger', [
                ...$this->generalLedgerEmpty($year, $month),
                'error' => $e->getMessage(),
                'monthLabels' => $this->monthLabels(),
                'filters' => ['year' => $year, 'month' => $month ?? 'all', 'day' => $day, 'account' => $accountId],
            ]);
        }

        return Inertia::render('Accounting/Reports/GeneralLedger', [
            ...$payload,
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all', 'day' => $day, 'account' => $accountId],
        ]);
    }

    public function generalLedgerPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $day = $this->optionalDay($request);
        $accountId = (int) $request->query('account', 0);
        if ($accountId <= 0) {
            abort(422, 'Pilih akun untuk Buku Besar.');
        }

        $data = $this->generalLedger->build($year, $month, $accountId, $day);

        return $this->pdf->stream(
            'reports.pdf.general_ledger',
            $data,
            'buku-besar-'.$data['account']['code'].'-'.$year.($month ? '-'.$month : '').'.pdf',
        );
    }

    // --- Penilaian Kesehatan Keuangan ---
    public function financialHealth(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->financialHealthService->build($year, $month);

        return Inertia::render('Accounting/Reports/FinancialHealth', [
            ...$data,
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function financialHealthPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->financialHealthService->build($year, $month);

        return $this->pdf->stream(
            'reports.pdf.penilaian_kesehatan',
            $data,
            sprintf('penilaian-kesehatan-%04d-%02d.pdf', $year, $month ?? 12),
        );
    }

    // --- Rekapitulasi Aset Tetap & ATB ---
    public function fixedAssetsPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->assetReportService->build($year, $month, 'tangible');

        return $this->pdf->stream(
            'reports.pdf.assets.fixed_assets',
            $data,
            sprintf('rekapitulasi-aset-tetap-%04d-%02d.pdf', $year, $month ?? 12),
            'landscape',
        );
    }

    public function intangibleAssetsPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->period($request, defaultMonth: (int) date('n'));
        $data = $this->assetReportService->build($year, $month, 'intangible');

        return $this->pdf->stream(
            'reports.pdf.assets.intangible_assets',
            $data,
            sprintf('rekapitulasi-aset-tak-berwujud-%04d-%02d.pdf', $year, $month ?? 12),
            'landscape',
        );
    }

    // --- Paket Dokumen Laporan Tahunan / Administratif ---
    public function annualPack(Request $request): InertiaResponse
    {
        $this->authorize($request);
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', 12);
        $data = $this->annualReportPack->build($year, $month);

        return Inertia::render('Accounting/Reports/AnnualPack', [
            ...$data,
            'filters' => ['year' => $year, 'month' => $month],
        ]);
    }

    public function annualCoverPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', 12);
        $data = $this->annualReportPack->build($year, $month, 'cover');

        return $this->pdf->stream(
            'reports.pdf.annual.cover',
            $data,
            sprintf('cover-laporan-tahunan-%04d.pdf', $year),
        );
    }

    public function annualSuratPengantarPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', 12);
        $data = $this->annualReportPack->build($year, $month, 'surat_pengantar');

        return $this->pdf->stream(
            'reports.pdf.annual.surat_pengantar',
            $data,
            sprintf('surat-pengantar-lpj-%04d.pdf', $year),
        );
    }

    public function annualBaPergantianPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', 12);
        $data = $this->annualReportPack->build($year, $month, 'ba_pergantian');

        return $this->pdf->stream(
            'reports.pdf.annual.ba_pergantian',
            $data,
            sprintf('berita-acara-lpj-%04d.pdf', $year),
        );
    }

    public function annualMouPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', 12);
        $data = $this->annualReportPack->build($year, $month, 'mou');

        return $this->pdf->stream(
            'reports.pdf.annual.mou',
            $data,
            sprintf('mou-kerjasama-antar-desa-%04d.pdf', $year),
        );
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'reports.view');
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    private function period(Request $request, ?int $defaultMonth = null): array
    {
        $year = (int) $request->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $raw = $request->query('month', $defaultMonth ?? 'all');
        if ($raw === null || $raw === 'all' || $raw === '' || $raw === '0') {
            return [$year, null];
        }

        $month = (int) $raw;
        if ($month < 1 || $month > 12) {
            $month = $defaultMonth;
        }

        return [$year, $month];
    }

    private function optionalDay(Request $request): ?string
    {
        $day = $request->query('day');
        if (! is_string($day) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return null;
        }

        return $day;
    }

    /**
     * @return array<string, mixed>
     */
    private function generalLedgerEmpty(int $year, ?int $month): array
    {
        $options = Account::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name'])
            ->map(fn ($a) => [
                'row_id' => (int) $a->row_id,
                'code' => (string) $a->code,
                'name' => (string) $a->name,
                'label' => $a->code.' · '.$a->name,
            ])
            ->all();

        $balances = app(AccountBalanceQuery::class);
        $period = $balances->resolvePeriod($year, $month);
        $profile = OrganizationProfile::query()->first();

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? ''),
                'short_name' => $profile?->short_name,
            ],
            'account' => null,
            'opening' => null,
            'rows' => [],
            'totals' => [
                'debit' => 0,
                'credit' => 0,
                'closing_balance' => 0,
                'period' => ['label' => 'Total Transaksi Periode', 'debit' => 0, 'credit' => 0, 'balance' => null],
                'ytd' => ['label' => 'Total Transaksi s/d Periode', 'debit' => 0, 'credit' => 0, 'balance' => 0],
                'cumulative' => ['label' => 'Total Transaksi Kumulatif Tahun', 'debit' => 0, 'credit' => 0, 'balance' => null],
            ],
            'account_options' => $options,
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private function monthLabels(): array
    {
        return [
            'all' => 'Januari – Desember',
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }
}
