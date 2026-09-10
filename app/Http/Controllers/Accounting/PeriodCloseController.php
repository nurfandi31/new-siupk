<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Services\FiscalPeriodCloseService;
use App\Domain\Accounting\Services\ProfitAllocationService;
use App\Domain\Accounting\Services\Reports\TrialBalanceService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PeriodCloseController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly FiscalPeriodCloseService $closer,
        private readonly ProfitAllocationService $allocation,
        private readonly TrialBalanceService $trialBalance,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'period_close.view');

        $year = (int) $request->query('year', CarbonImmutable::today()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) CarbonImmutable::today()->year;
        }

        $payload = $this->closer->overview($year);
        try {
            $allocation = $this->allocation->formState($year);
        } catch (DomainException $e) {
            $allocation = [
                'error' => $e->getMessage(),
                'profit_year' => $year,
                'available' => 0,
                'already_allocated' => 0,
                'remaining' => 0,
                'accounts' => null,
                'community_lines' => [],
                'villages' => [],
                'existing' => [],
                'default_date' => CarbonImmutable::create($year + 1, 1, 1)->toDateString(),
            ];
        }

        return Inertia::render('Accounting/PeriodClose/Index', [
            ...$payload,
            'allocation' => $allocation,
            'trial_balance' => $this->trialBalance->build($year, 12),
            'can_close' => $this->permissions->allows($request->user(), 'period_close.manage'),
            'year_options' => $this->yearOptions($year),
        ]);
    }

    public function closeMonth(Request $request, int $year, int $month): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'period_close.manage');

        try {
            $this->closer->closeMonth($year, $month, (int) $request->user()->row_id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('accounting.period-close.index', ['year' => $year])
            ->with('success', sprintf('Periode %02d/%d ditutup.', $month, $year));
    }

    public function reopenMonth(Request $request, int $year, int $month): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'period_close.manage');

        try {
            $this->closer->reopenMonth($year, $month);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('accounting.period-close.index', ['year' => $year])
            ->with('success', sprintf('Periode %02d/%d dibuka kembali.', $month, $year));
    }

    public function closeYear(Request $request): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'period_close.manage');

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $year = (int) $validated['year'];
        $force = (bool) ($validated['force'] ?? false);

        try {
            $result = $this->closer->closeYear($year, (int) $request->user()->row_id, $force);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('accounting.period-close.index', ['year' => $year])
            ->with('success', sprintf(
                'Tutup tahun %d selesai. Bulan ditutup: %d. Saldo awal %d: %d akun. Laba/rugi: %s.',
                $year,
                $result['closed_months'],
                $result['next_year'],
                $result['openings_written'],
                number_format($result['net_income'], 0, ',', '.'),
            ));
    }

    public function allocate(Request $request): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'period_close.manage');

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'date' => ['required', 'date'],
            'community' => ['nullable', 'array'],
            'community.*' => ['nullable', 'numeric', 'min:0'],
            'villages' => ['nullable', 'array'],
            'villages.*' => ['nullable', 'numeric', 'min:0'],
            'investor' => ['nullable', 'numeric', 'min:0'],
            'retained' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $year = (int) $validated['year'];

        try {
            $result = $this->allocation->allocate($year, [
                'date' => $validated['date'],
                'community' => $validated['community'] ?? [],
                'villages' => $validated['villages'] ?? [],
                'investor' => $validated['investor'] ?? 0,
                'retained' => $validated['retained'] ?? 0,
                'note' => $validated['note'] ?? null,
            ], (int) $request->user()->row_id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('accounting.period-close.index', ['year' => $year])
            ->with('success', sprintf(
                'Alokasi laba %d tersimpan (jurnal #%d, total %s).',
                $year,
                $result['journal_id'],
                number_format($result['total'], 0, ',', '.'),
            ));
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
