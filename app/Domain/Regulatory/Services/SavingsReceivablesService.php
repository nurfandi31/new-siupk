<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * SMPN — Simpanan & Piutang (OJK).
 *
 * Rekapitulasi dua sisi dalam satu lembar: sisi PASIVA menampilkan
 * total simpanan (kewajiban) yang dihimpun dari anggota, sedangkan
 * sisi AKTIVA menampilkan total piutang (pinjaman) yang disalurkan.
 *
 * Tujuan OJK dari laporan ini: melihat rasio Piutang/Simpanan (LDR
 * sederhana) sebagai indikator likuiditas & kesehatan lembaga.
 *
 * Sumber data:
 *  - Simpanan  → akun liability 2.1.10.* (Pokok), 2.1.11.* (Wajib),
 *                2.1.12.* (Sukarela), 2.1.13.* (Berjangka) — sama
 *                dengan SimpananReportService.
 *  - Piutang Pokok → akun asset 1.1.03.0[1-3,9] (Piutang Masyarakat
 *                    SPP/UEP/Lembaga Lain/Perorangan Pokok).
 *  - Piutang Bunga → akun asset 1.1.03.0[4-6,10] (Piutang Jasa
 *                    SPP/UEP/Lembaga Lain/Perorangan).
 *  - Piutang Lain  → akun asset 1.1.03.07 (Piutang Dividen),
 *                    1.1.03.08 (Piutang lain), 1.1.03.99 (fallback
 *                    generic), dan akun receivable lain yang matching.
 *
 * Tidak semua tenant punya akun dengan kode persis seperti di COA
 * default, sehingga classifier menggunakan prefix + keyword fallback
 * agar laporan tetap terisi.
 */
final readonly class SavingsReceivablesService
{
    /** @var list<array{prefix: string, kind: string}> */
    private const SAVINGS_PREFIXES = [
        ['prefix' => '2.1.13', 'kind' => 'berjangka'],
        ['prefix' => '2.1.12', 'kind' => 'sukarela'],
        ['prefix' => '2.1.11', 'kind' => 'wajib'],
        ['prefix' => '2.1.10', 'kind' => 'pokok'],
    ];

    /** @var array<string, list<string>> */
    private const SAVINGS_KEYWORDS = [
        'pokok' => ['simpanan pokok', 'pokok', 'modal setor', 'modal dasar'],
        'wajib' => ['simpanan wajib', 'wajib'],
        'sukarela' => ['simpanan sukarela', 'sukarela'],
        'berjangka' => ['simpanan berjangka', 'berjangka', 'deposito'],
    ];

    /** @var array<string, string> */
    private const SAVINGS_LABELS = [
        'pokok' => 'Simpanan Pokok',
        'wajib' => 'Simpanan Wajib',
        'sukarela' => 'Simpanan Sukarela',
        'berjangka' => 'Simpanan Berjangka',
        'lainnya' => 'Simpanan Lainnya',
    ];

    /** @var list<string> */
    private const RECEIVABLE_PRINCIPAL_PREFIXES = ['1.1.03.01', '1.1.03.02', '1.1.03.03', '1.1.03.09'];

    /** @var list<string> */
    private const RECEIVABLE_INTEREST_PREFIXES = ['1.1.03.04', '1.1.03.05', '1.1.03.06', '1.1.03.10'];

    /** @var list<string> */
    private const RECEIVABLE_OTHER_PREFIXES = ['1.1.03.07', '1.1.03.08', '1.1.03.99'];

    public function __construct(
        private AccountBalanceQuery $balances,
        private TenantContext $context,
    ) {}

    /**
     * @return array{
     *   period: array<string, mixed>,
     *   identity: array<string, string|null>,
     *   passiva: array<string, mixed>,
     *   aktiva: array<string, mixed>,
     *   totals: array<string, float>,
     *   ratio: array<string, float>,
     *   generated_at: string,
     *   tenant_id: int
     * }
     */
    public function buildReport(int $tenantId, int $year, ?int $month): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();

        $savings = $this->collectSavings($asOf);
        $receivables = $this->collectReceivables($asOf);

        $totalSavings = (float) $savings['total_closing'];
        $totalReceivables = (float) $receivables['total_closing'];

        $ratio = [
            'piutang_to_simpanan' => $totalSavings > 0 ? round($totalReceivables / $totalSavings * 100, 2) : 0.0,
            'pokok_to_simpanan' => $totalSavings > 0 ? round((float) $receivables['pokok_closing'] / $totalSavings * 100, 2) : 0.0,
        ];

        $profile = OrganizationProfile::query()->first();

        return [
            'period' => $period,
            'identity' => $this->identity($profile),
            'passiva' => $savings,
            'aktiva' => $receivables,
            'totals' => [
                'savings' => $totalSavings,
                'receivables' => $totalReceivables,
                'selisih' => round($totalSavings - $totalReceivables, 2),
            ],
            'ratio' => $ratio,
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
            'tenant_id' => $tenantId,
        ];
    }

    /**
     * Convenience overload — uses the current tenant from TenantContext.
     *
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month): array
    {
        $tenantId = (int) ($this->context->id() ?? 0);

        return $this->buildReport($tenantId, $year, $month);
    }

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   by_kind: array<string, array<string, float|int>>,
     *   total_closing: float
     * }
     */
    private function collectSavings(CarbonImmutable $asOf): array
    {
        $accounts = Account::query()
            ->where('account_type', 'liability')
            ->where(function ($q) use ($asOf): void {
                $q->where('is_active', true)
                    ->orWhereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>', $asOf->toDateString());
            })
            ->whereDate('created_at', '<=', $asOf->toDateString())
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'normal_balance']);

        $rows = [];
        $byKind = $this->emptySavingsBuckets();

        foreach ($accounts as $account) {
            $kind = $this->classifySavingsAccount((string) $account->code, (string) $account->name);
            if ($kind === null) {
                continue;
            }

            $raw = $this->balances->asOfRaw($account, $asOf);
            $closing = round((float) $raw['signed'], 2);

            $rows[] = [
                'row_id' => (int) $account->row_id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'kind' => $kind,
                'kind_label' => self::SAVINGS_LABELS[$kind] ?? 'Simpanan Lainnya',
                'closing_balance' => $closing,
            ];

            $byKind[$kind]['closing_balance'] = round(((float) $byKind[$kind]['closing_balance']) + $closing, 2);
            $byKind[$kind]['count']++;
        }

        $total = 0.0;
        foreach ($byKind as $bucket) {
            $total = round($total + (float) $bucket['closing_balance'], 2);
        }

        return [
            'rows' => $rows,
            'by_kind' => $byKind,
            'total_closing' => $total,
        ];
    }

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   by_kind: array<string, array<string, float|int>>,
     *   total_closing: float,
     *   pokok_closing: float,
     *   bunga_closing: float,
     *   lain_closing: float
     * }
     */
    private function collectReceivables(CarbonImmutable $asOf): array
    {
        $accounts = Account::query()
            ->where('account_type', 'asset')
            ->where(function ($q) use ($asOf): void {
                $q->where('is_active', true)
                    ->orWhereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>', $asOf->toDateString());
            })
            ->whereDate('created_at', '<=', $asOf->toDateString())
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'normal_balance']);

        $rows = [];
        $byKind = [
            'pokok' => ['label' => 'Piutang Pokok', 'closing_balance' => 0.0, 'count' => 0],
            'bunga' => ['label' => 'Piutang Bunga', 'closing_balance' => 0.0, 'count' => 0],
            'lain' => ['label' => 'Piutang Lain', 'closing_balance' => 0.0, 'count' => 0],
        ];

        foreach ($accounts as $account) {
            $kind = $this->classifyReceivable((string) $account->code, (string) $account->name);
            if ($kind === null) {
                continue;
            }

            $raw = $this->balances->asOfRaw($account, $asOf);
            $closing = round((float) $raw['signed'], 2);

            $rows[] = [
                'row_id' => (int) $account->row_id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'kind' => $kind,
                'kind_label' => $byKind[$kind]['label'],
                'closing_balance' => $closing,
            ];

            $byKind[$kind]['closing_balance'] = round(((float) $byKind[$kind]['closing_balance']) + $closing, 2);
            $byKind[$kind]['count']++;
        }

        $total = round(
            (float) $byKind['pokok']['closing_balance']
            + (float) $byKind['bunga']['closing_balance']
            + (float) $byKind['lain']['closing_balance'],
            2,
        );

        return [
            'rows' => $rows,
            'by_kind' => $byKind,
            'pokok_closing' => (float) $byKind['pokok']['closing_balance'],
            'bunga_closing' => (float) $byKind['bunga']['closing_balance'],
            'lain_closing' => (float) $byKind['lain']['closing_balance'],
            'total_closing' => $total,
        ];
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function emptySavingsBuckets(): array
    {
        $order = ['pokok', 'wajib', 'sukarela', 'berjangka', 'lainnya'];
        $buckets = [];
        foreach ($order as $kind) {
            $buckets[$kind] = [
                'label' => self::SAVINGS_LABELS[$kind] ?? $kind,
                'closing_balance' => 0.0,
                'count' => 0,
            ];
        }

        return $buckets;
    }

    private function classifySavingsAccount(string $code, string $name): ?string
    {
        $trimmedCode = trim($code);
        foreach (self::SAVINGS_PREFIXES as $rule) {
            if ($trimmedCode !== '' && str_starts_with($trimmedCode, $rule['prefix'])) {
                return $rule['kind'];
            }
        }

        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }

        foreach (self::SAVINGS_KEYWORDS as $kind => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($needle, $keyword)) {
                    return $kind;
                }
            }
        }

        if (str_contains($needle, 'simpanan') || str_contains($needle, 'tabungan') || str_contains($needle, 'deposit')) {
            return 'lainnya';
        }

        return null;
    }

    private function classifyReceivable(string $code, string $name): ?string
    {
        $trimmedCode = trim($code);
        $needle = mb_strtolower(trim($name));

        if ($trimmedCode !== '' && str_starts_with($trimmedCode, '1.1.03')) {
            foreach (self::RECEIVABLE_PRINCIPAL_PREFIXES as $prefix) {
                if (str_starts_with($trimmedCode, $prefix)) {
                    return 'pokok';
                }
            }
            foreach (self::RECEIVABLE_INTEREST_PREFIXES as $prefix) {
                if (str_starts_with($trimmedCode, $prefix)) {
                    return 'bunga';
                }
            }
            foreach (self::RECEIVABLE_OTHER_PREFIXES as $prefix) {
                if (str_starts_with($trimmedCode, $prefix)) {
                    return 'lain';
                }
            }
        }

        if ($needle === '') {
            return null;
        }

        if (str_contains($needle, 'piutang')) {
            if (str_contains($needle, 'jasa') || str_contains($needle, 'bunga')) {
                return 'bunga';
            }
            if (str_contains($needle, 'pokok')) {
                return 'pokok';
            }

            return 'lain';
        }

        return null;
    }

    /**
     * @return array<string, string|null>
     */
    private function identity(?OrganizationProfile $profile): array
    {
        return [
            'legal_name' => (string) ($profile?->legal_name ?? ''),
            'short_name' => $profile?->short_name,
            'logo_url' => $profile?->logo_url,
            'registration_number' => (string) ($profile?->registration_number ?? ''),
            'address' => (string) ($profile?->address ?? ''),
            'phone' => (string) ($profile?->phone ?? ''),
            'district_name' => (string) ($profile?->district_name ?? ''),
            'regency_name' => (string) ($profile?->regency_name ?? ''),
            'manager_name' => (string) ($profile?->manager_name ?? ''),
            'manager_title' => (string) ($profile?->manager_title ?? ''),
            'treasurer_name' => (string) ($profile?->treasurer_name ?? ''),
            'treasurer_title' => (string) ($profile?->treasurer_title ?? ''),
        ];
    }
}
