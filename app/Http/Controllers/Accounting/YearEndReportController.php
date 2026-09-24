<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Services\Reports\YearEnd\AllocationService;
use App\Domain\Accounting\Services\Reports\YearEnd\ClosingJournalService;
use App\Domain\Accounting\Services\Reports\YearEnd\YearEndBalanceSheetService;
use App\Domain\Accounting\Services\Reports\YearEnd\YearEndCalkService;
use App\Domain\Accounting\Services\Reports\YearEnd\YearEndIncomeStatementService;
use App\Models\User;
use App\Support\ReportPdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 5 Laporan Tutup Buku (Year-End Closing).
 *
 * Semua laporan adalah PREVIEW/SIMULASI — tidak benar-benar memposting
 * jurnal tutup buku. Untuk eksekusi tutup buku sebenarnya, gunakan
 * PeriodCloseController.
 *
 * Permission: `reports.view` (sama dengan ReportController).
 *
 * Routes (didefinisikan di routes/web.php):
 *   GET  /accounting/year-end/allocation              → Inertia page
 *   GET  /accounting/year-end/allocation/pdf          → PDF stream
 *   GET  /accounting/year-end/journal                 → Inertia page
 *   GET  /accounting/year-end/journal/pdf             → PDF stream
 *   GET  /accounting/year-end/balance-sheet           → Inertia page
 *   GET  /accounting/year-end/balance-sheet/pdf       → PDF stream
 *   GET  /accounting/year-end/income-statement        → Inertia page
 *   GET  /accounting/year-end/income-statement/pdf    → PDF stream
 *   GET  /accounting/year-end/calk                    → Inertia page
 *   GET  /accounting/year-end/calk/pdf                → PDF stream
 *   PUT  /accounting/year-end/calk/notes              → Save CALK notes
 *   PUT  /accounting/year-end/allocation/notes        → Save allocation notes/percentages
 */
final class YearEndReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly AllocationService $allocation,
        private readonly ClosingJournalService $closingJournal,
        private readonly YearEndBalanceSheetService $yearEndBalanceSheet,
        private readonly YearEndIncomeStatementService $yearEndIncomeStatement,
        private readonly YearEndCalkService $yearEndCalk,
        private readonly ReportPdf $pdf,
    ) {}

    // --- 1) Alokasi Laba ---

    public function allocation(Request $request): InertiaResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);
        $percentages = $this->extractPercentages($request);

        $data = $this->allocation->build($year, $percentages);

        return Inertia::render('Accounting/Reports/YearEnd/Allocation', [
            ...$data,
            'yearOptions' => $this->yearOptions($year),
            'filters' => ['year' => $year],
        ]);
    }

    public function allocationPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);
        $percentages = $this->extractPercentages($request);

        $data = $this->allocation->build($year, $percentages);

        return $this->pdf->stream(
            'reports.pdf.year_end.allocation',
            $data,
            sprintf('alokasi-laba-tutup-buku-%04d.pdf', $year),
        );
    }

    public function saveAllocationNotes(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'percentages' => ['nullable', 'array'],
            'percentages.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $percentages = null;
        if (isset($validated['percentages']) && is_array($validated['percentages'])) {
            $percentages = [];
            foreach ($validated['percentages'] as $key => $value) {
                if ($value !== null && $value !== '') {
                    $percentages[(string) $key] = (float) $value;
                }
            }
        }

        $this->allocation->saveNotes(
            (int) $validated['year'],
            (string) ($validated['notes'] ?? ''),
            $percentages,
        );

        return back()->with('success', 'Catatan alokasi laba berhasil disimpan.');
    }

    // --- 2) Jurnal Tutup Buku ---

    public function closingJournal(Request $request): InertiaResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);

        $data = $this->closingJournal->build($year);

        return Inertia::render('Accounting/Reports/YearEnd/ClosingJournal', [
            ...$data,
            'yearOptions' => $this->yearOptions($year),
            'filters' => ['year' => $year],
        ]);
    }

    public function closingJournalPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);

        $data = $this->closingJournal->build($year);

        return $this->pdf->stream(
            'reports.pdf.year_end.closing_journal',
            $data,
            sprintf('jurnal-tutup-buku-%04d.pdf', $year),
        );
    }

    // --- 3) Neraca Tutup Buku ---

    public function yearEndBalanceSheet(Request $request): InertiaResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);

        $data = $this->yearEndBalanceSheet->build($year);

        return Inertia::render('Accounting/Reports/YearEnd/BalanceSheet', [
            ...$data,
            'yearOptions' => $this->yearOptions($year),
            'filters' => ['year' => $year],
        ]);
    }

    public function yearEndBalanceSheetPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);

        $data = $this->yearEndBalanceSheet->build($year);

        return $this->pdf->stream(
            'reports.pdf.year_end.balance_sheet',
            $data,
            sprintf('neraca-tutup-buku-%04d.pdf', $year),
        );
    }

    // --- 4) Laba Rugi Tutup Buku ---

    public function yearEndIncomeStatement(Request $request): InertiaResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);

        $data = $this->yearEndIncomeStatement->build($year);

        return Inertia::render('Accounting/Reports/YearEnd/IncomeStatement', [
            ...$data,
            'yearOptions' => $this->yearOptions($year),
            'filters' => ['year' => $year],
        ]);
    }

    public function yearEndIncomeStatementPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);

        $data = $this->yearEndIncomeStatement->build($year);

        return $this->pdf->stream(
            'reports.pdf.year_end.income_statement',
            $data,
            sprintf('laba-rugi-tutup-buku-%04d.pdf', $year),
        );
    }

    // --- 5) CALK Tutup Buku ---

    public function yearEndCalk(Request $request): InertiaResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);

        $data = $this->yearEndCalk->build($year);

        return Inertia::render('Accounting/Reports/YearEnd/Calk', [
            ...$data,
            'yearOptions' => $this->yearOptions($year),
            'filters' => ['year' => $year],
            'can_edit' => $this->permissions->allows($request->user(), 'reports.manage'),
        ]);
    }

    public function yearEndCalkPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $year = $this->resolveYear($request);

        $data = $this->yearEndCalk->build($year);

        return $this->pdf->stream(
            'reports.pdf.year_end.calk',
            $data,
            sprintf('calk-tutup-buku-%04d.pdf', $year),
        );
    }

    public function saveYearEndCalkNotes(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:10000'],
        ]);

        $notes = [];
        if (isset($validated['notes']) && is_array($validated['notes'])) {
            foreach ($validated['notes'] as $key => $value) {
                if (is_string($value)) {
                    $notes[(string) $key] = $value;
                }
            }
        }

        $this->yearEndCalk->saveNotes((int) $validated['year'], $notes);

        return back()->with('success', 'Catatan CALK tutup buku berhasil disimpan.');
    }

    // --- helpers ---

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'reports.view');
    }

    private function resolveYear(Request $request): int
    {
        $year = (int) $request->query('year', CarbonImmutable::today()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) CarbonImmutable::today()->year;
        }

        return $year;
    }

    /**
     * @return array<string, float>
     */
    private function extractPercentages(Request $request): array
    {
        $raw = $request->query('pct', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            $out[(string) $key] = (float) $value;
        }

        return $out;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function yearOptions(int $current): array
    {
        $opts = [];
        for ($y = $current + 1; $y >= $current - 8; $y--) {
            $opts[] = ['value' => $y, 'label' => (string) $y];
        }

        return $opts;
    }
}
