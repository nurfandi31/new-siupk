<?php

declare(strict_types=1);

namespace App\Http\Controllers\Savings;

use App\Domain\Savings\Models\SavingsInterestRun;
use App\Domain\Savings\Services\SavingsModuleManager;
use App\Domain\Savings\Services\SavingsService;
use App\Http\Requests\Savings\SavingsInterestRequest;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Proses bunga & pajak simpanan per periode (bulan).
 */
final class SavingsInterestController
{
    public function __construct(
        private readonly SavingsService $savings,
        private readonly SavingsModuleManager $modules,
    ) {}

    public function index(Request $request): Response
    {
        [$year, $month] = $this->resolvePeriod($request);
        $cif = $this->resolveCif($request);
        $accountIds = $this->accountIds($cif);
        $preview = $this->savings->interestPreview($year, $month, null, $accountIds);

        return Inertia::render('Savings/Interest', [
            'preview' => $preview,
            'runs' => $this->savings->paginateInterestRuns($this->perPage($request->query('per_page'))),
            'filters' => ['year' => $year, 'month' => $month, 'cif' => $cif],
            'monthLabels' => $this->monthLabels(),
            'settings' => $this->modules->settings(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        [$year, $month] = $this->resolvePeriod($request);

        return response()->json([
            'data' => $this->savings->interestPreview($year, $month, null, $this->accountIds($this->resolveCif($request))),
        ]);
    }

    public function store(SavingsInterestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $cif = trim((string) ($data['cif'] ?? ''));
        $accountIds = $this->accountIds($cif);

        try {
            $run = $this->savings->postInterest(
                (int) $data['year'],
                (int) $data['month'],
                (int) $request->user()->row_id,
                $data['posting_date'] ?? null,
                $accountIds,
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('savings.interest.index', [
            'year' => (int) $data['year'],
            'month' => (int) $data['month'],
            'cif' => $cif,
        ])->with(
            'success',
            sprintf(
                'Bunga %s diproses untuk %d rekening (bunga Rp %s, pajak Rp %s, admin Rp %s). %d rekening dilewati.',
                $run['period'],
                (int) ($run['processed'] ?? 0),
                number_format((float) $run['gross_interest'], 0, ',', '.'),
                number_format((float) $run['tax_total'], 0, ',', '.'),
                number_format((float) $run['admin_total'], 0, ',', '.'),
                (int) ($run['skipped'] ?? 0),
            ),
        );
    }

    public function destroy(int $run): RedirectResponse
    {
        $model = SavingsInterestRun::query()->whereKey($run)->firstOrFail();

        try {
            $this->savings->voidInterestRun($model);
        } catch (Throwable $exception) {
            return back()->with('error', 'Gagal membatalkan proses bunga: '.$exception->getMessage());
        }

        return back()->with('success', 'Proses bunga dibatalkan. Saldo rekening telah dihitung ulang.');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolvePeriod(Request $request): array
    {
        $now = CarbonImmutable::now()->subMonthNoOverflow();

        $year = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);

        if ($year < 2000 || $year > 2100) {
            $year = $now->year;
        }

        if ($month < 1 || $month > 12) {
            $month = $now->month;
        }

        return [$year, $month];
    }

    /**
     * @return array<int, string>
     */
    private function monthLabels(): array
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    }

    private function resolveCif(Request $request): string
    {
        return trim((string) $request->query('cif', ''));
    }

    /**
     * Legacy memakai CIF (id rekening) yang dipisah koma. Nilai non-angka
     * diabaikan seperti pada SimpananController::simpanBunga().
     *
     * @return list<int>|null
     */
    private function accountIds(string $cif): ?array
    {
        if ($cif === '') {
            return null;
        }

        $ids = array_values(array_filter(
            array_map('intval', array_map('trim', explode(',', $cif))),
            static fn (int $id): bool => $id > 0,
        ));

        return $ids === [] ? null : $ids;
    }

    private function perPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [10, 15, 30, 50], true) ? $value : 10;
    }
}
