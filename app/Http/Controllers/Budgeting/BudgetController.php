<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budgeting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Budgeting\Services\BudgetService;
use App\Http\Requests\Budgeting\SaveBudgetMonthRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BudgetController
{
    public function __construct(
        private readonly BudgetService $budgets,
        private readonly PermissionChecker $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'budgeting.view');

        $year = $this->resolveYear($request);
        $month = $this->resolveMonth($request);
        $overview = $this->budgets->yearOverview($year);
        $sheet = $this->budgets->monthSheet($year, $month);

        return Inertia::render('Budgeting/Index', [
            'year' => $year,
            'month' => $month,
            'budget' => $overview['budget'],
            'months' => $overview['months'],
            'sheet' => $sheet,
            'monthLabels' => $this->monthLabels(),
        ]);
    }

    public function save(SaveBudgetMonthRequest $request, int $year, int $month): RedirectResponse
    {
        try {
            $result = $this->budgets->saveMonth($year, $month, $request->validated('amounts'));
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $label = $this->monthLabels()[$month] ?? "Bulan {$month}";

        return to_route('budgeting.index', ['year' => $year, 'month' => $month])
            ->with('success', "Rencana anggaran {$label} {$year} disimpan ({$result['imported']} akun).");
    }

    public function copyPrevious(Request $request, int $year, int $month): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'budgeting.manage');

        try {
            $count = $this->budgets->copyFromPreviousMonth($year, $month);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $label = $this->monthLabels()[$month] ?? "Bulan {$month}";

        return to_route('budgeting.index', ['year' => $year, 'month' => $month])
            ->with('success', "Disalin dari bulan sebelumnya ke {$label} ({$count} akun).");
    }

    public function approve(Request $request, int $year): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'budgeting.manage');

        try {
            $this->budgets->approve($year, (int) $request->user()->row_id);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('budgeting.index', ['year' => $year])
            ->with('success', "Anggaran tahun {$year} disetujui.");
    }

    public function reopen(Request $request, int $year): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'budgeting.manage');

        try {
            $this->budgets->reopen($year);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('budgeting.index', ['year' => $year])
            ->with('success', "Anggaran tahun {$year} dibuka kembali (draft).");
    }

    private function resolveYear(Request $request): int
    {
        $year = (int) $request->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    private function resolveMonth(Request $request): int
    {
        $month = (int) $request->query('month', date('n'));
        if ($month < 1 || $month > 12) {
            return (int) date('n');
        }

        return $month;
    }

    /**
     * @return array<int, string>
     */
    private function monthLabels(): array
    {
        return [
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
