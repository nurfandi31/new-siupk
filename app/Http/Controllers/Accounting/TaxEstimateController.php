<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Services\TaxEstimateService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TaxEstimateController
{
    public function __construct(
        private readonly TaxEstimateService $estimates,
        private readonly PermissionChecker $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'tax.view');

        $year = $this->resolveYear($request);
        $month = $this->resolveMonth($request);
        $selected = $request->query('accounts');
        $selectedIds = is_array($selected)
            ? array_map('intval', $selected)
            : (is_string($selected) && $selected !== ''
                ? array_map('intval', explode(',', $selected))
                : []);

        try {
            $payload = $this->estimates->estimate($year, $month, $selectedIds);
        } catch (DomainException $exception) {
            $payload = $this->estimates->estimate((int) date('Y'), (int) date('n'), []);
            $payload['error'] = $exception->getMessage();
        }

        return Inertia::render('Accounting/TaxEstimate/Index', [
            ...$payload,
            'monthLabels' => $this->monthLabels(),
            'onlySelected' => $selectedIds !== [],
        ]);
    }

    private function resolveYear(Request $request): int
    {
        $year = (int) $request->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    private function resolveMonth(Request $request): ?int
    {
        $raw = $request->query('month', date('n'));
        if ($raw === 'all' || $raw === '-12' || $raw === '0') {
            return null;
        }

        $month = (int) $raw;
        if ($month < 1 || $month > 12) {
            return (int) date('n');
        }

        return $month;
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
