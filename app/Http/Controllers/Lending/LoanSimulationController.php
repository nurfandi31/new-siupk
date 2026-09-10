<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Lending\Services\LoanSimulationService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ReportPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LoanSimulationController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly LoanSimulationService $simulator,
        private readonly ReportPdf $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        $products = LoanProduct::query()
            ->active()
            ->orderBy('name')
            ->get(['row_id', 'code', 'name', 'default_interest_rate', 'default_term_months', 'minimum_amount', 'maximum_amount', 'borrower_scope', 'rounding_method'])
            ->map(fn (LoanProduct $p): array => [
                'row_id' => (int) $p->row_id,
                'code' => (string) $p->code,
                'name' => (string) $p->name,
                'interest_rate' => (float) $p->default_interest_rate,
                'term_months' => (int) $p->default_term_months,
                'min_amount' => (float) $p->minimum_amount,
                'max_amount' => (float) $p->maximum_amount,
                'rounding_step' => $this->parseRoundingStep($p->rounding_method),
            ])
            ->values()
            ->all();

        $defaultParams = [
            'principal_amount' => (float) ($request->query('principal') ?: 10000000),
            'term_months' => (int) ($request->query('term') ?: 12),
            'interest_rate' => (float) ($request->query('rate') ?: 1.5),
            'rate_unit' => (string) ($request->query('rate_unit') ?: 'monthly'),
            'installment_method' => (string) ($request->query('method') ?: 'flat'),
            'principal_frequency' => (string) ($request->query('principal_freq') ?: 'monthly'),
            'interest_frequency' => (string) ($request->query('interest_freq') ?: 'monthly'),
            'principal_grace_months' => (int) ($request->query('principal_grace') ?: 0),
            'interest_grace_months' => (int) ($request->query('interest_grace') ?: 0),
            'rounding_step' => $request->has('rounding') ? max(0, (int) $request->query('rounding')) : 500,
            'start_date' => (string) ($request->query('start_date') ?: date('Y-m-d')),
        ];

        $initialSimulation = $this->simulator->simulate($defaultParams);

        return Inertia::render('Lending/Simulation/Index', [
            'products' => $products,
            'defaultSimulation' => $initialSimulation,
            'frequencyOptions' => [
                ['value' => 'monthly', 'label' => 'Bulanan (Tiap 1 Bulan)'],
                ['value' => 'quarterly', 'label' => 'Triwulanan (Tiap 3 Bulan)'],
                ['value' => 'semi_annually', 'label' => 'Semesteran (Tiap 6 Bulan)'],
                ['value' => 'annually', 'label' => 'Tahunan (Tiap 12 Bulan)'],
                ['value' => 'every_4_months', 'label' => 'Tiap 4 Bulan'],
                ['value' => 'every_5_months', 'label' => 'Tiap 5 Bulan'],
                ['value' => 'every_6_months', 'label' => 'Tiap 6 Bulan'],
                ['value' => 'every_7_months', 'label' => 'Tiap 7 Bulan'],
                ['value' => 'every_8_months', 'label' => 'Tiap 8 Bulan'],
                ['value' => 'every_9_months', 'label' => 'Tiap 9 Bulan'],
                ['value' => 'every_10_months', 'label' => 'Tiap 10 Bulan'],
                ['value' => 'every_11_months', 'label' => 'Tiap 11 Bulan'],
                ['value' => 'every_12_months', 'label' => 'Tiap 12 Bulan'],
                ['value' => 'every_24_months', 'label' => 'Tiap 24 Bulan'],
                ['value' => 'every_36_months', 'label' => 'Tiap 36 Bulan'],
                ['value' => 'at_maturity', 'label' => 'Jatuh Tempo (Sekaligus di Akhir)'],
            ],
            'methodOptions' => [
                [
                    'value' => 'flat',
                    'label' => 'Flat / Tetap',
                    'description' => 'Angsuran pokok dan jasa bernilai sama/tetap setiap periode.',
                ],
                [
                    'value' => 'declining',
                    'label' => 'Efektif Menurun',
                    'description' => 'Pokok tetap, bunga dihitung dari sisa saldo pokok pinjaman sehingga total angsuran menurun.',
                ],
                [
                    'value' => 'annuity',
                    'label' => 'Anuitas',
                    'description' => 'Total angsuran (pokok + jasa) tetap tiap periode, proporsi pokok bertambah & jasa menurun.',
                ],
            ],
            'roundingOptions' => [
                ['value' => 0, 'label' => 'Tanpa Pembulatan (Desimal / Rp 1)'],
                ['value' => 100, 'label' => 'Pembulatan Ratusan (Rp 100)'],
                ['value' => 500, 'label' => 'Pembulatan Rp 500 (Standar UPK)'],
                ['value' => 1000, 'label' => 'Pembulatan Ribuan (Rp 1.000)'],
                ['value' => 5000, 'label' => 'Pembulatan Rp 5.000'],
                ['value' => 10000, 'label' => 'Pembulatan Puluhan Ribu (Rp 10.000)'],
                ['value' => 50000, 'label' => 'Pembulatan Rp 50.000'],
            ],
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $this->authorize($request);
        $params = $request->validate([
            'principal_amount' => ['required', 'numeric', 'min:100000'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'rate_unit' => ['nullable', 'string', 'in:monthly,annual'],
            'installment_method' => ['required', 'string', 'in:flat,declining,annuity'],
            'principal_frequency' => ['required', 'string', 'in:weekly,biweekly,monthly,bimonthly,quarterly,every_4_months,every_5_months,every_6_months,every_7_months,every_8_months,every_9_months,every_10_months,every_11_months,every_12_months,every_24_months,every_36_months,at_maturity'],
            'interest_frequency' => ['required', 'string', 'in:weekly,biweekly,monthly,bimonthly,quarterly,every_4_months,every_5_months,every_6_months,every_7_months,every_8_months,every_9_months,every_10_months,every_11_months,every_12_months,every_24_months,every_36_months,at_maturity'],
            'principal_grace_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'interest_grace_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'rounding_step' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['nullable', 'date'],
        ]);

        $result = $this->simulator->simulate($params);

        return response()->json($result);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $params = [
            'principal_amount' => (float) ($request->query('principal_amount') ?: 10000000),
            'term_months' => (int) ($request->query('term_months') ?: 12),
            'interest_rate' => (float) ($request->query('interest_rate') ?: 1.5),
            'rate_unit' => (string) ($request->query('rate_unit') ?: 'monthly'),
            'installment_method' => (string) ($request->query('installment_method') ?: 'flat'),
            'principal_frequency' => (string) ($request->query('principal_frequency') ?: 'monthly'),
            'interest_frequency' => (string) ($request->query('interest_frequency') ?: 'monthly'),
            'principal_grace_months' => (int) ($request->query('principal_grace_months') ?: 0),
            'interest_grace_months' => (int) ($request->query('interest_grace_months') ?: 0),
            'rounding_step' => $request->has('rounding_step') ? max(0, (int) $request->query('rounding_step')) : 500,
            'start_date' => (string) ($request->query('start_date') ?: date('Y-m-d')),
        ];

        $simulation = $this->simulator->simulate($params);
        $profile = OrganizationProfile::query()->first();

        $identity = [
            'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
            'short_name' => $profile?->short_name,
            'address' => $profile?->address,
            'phone' => $profile?->phone,
            'email' => $profile?->email,
            'district_name' => $profile?->district_name,
            'regency_name' => $profile?->regency_name,
        ];

        $filename = sprintf('simulasi-pinjaman-%s.pdf', date('Ymd-His'));

        return $this->pdf->stream('reports.pdf.loan_simulation', [
            'simulation' => $simulation,
            'identity' => $identity,
            'title' => 'Simulasi Pinjaman',
            'borrower_name' => $request->query('borrower_name') ?: 'Calon Peminjam',
        ], $filename);
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'loans.view');
    }

    private function parseRoundingStep(?string $method): int
    {
        if ($method === null || $method === '') {
            return 500;
        }

        if (is_numeric($method)) {
            return max(0, (int) $method);
        }

        return match ($method) {
            'decimal_2', 'rupiah_bersih', '0' => 0,
            '100', 'ceil_100', 'floor_100' => 100,
            '500' => 500,
            '1000' => 1000,
            '5000' => 5000,
            '10000' => 10000,
            '50000' => 50000,
            default => 500,
        };
    }
}
