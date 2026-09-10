<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Services\TenantSettingService;
use Carbon\CarbonImmutable;

/**
 * CALK — catatan atas laporan keuangan.
 * Legacy: free-text + template statis. Next: ringkasan otomatis + catatan editable (tenant_settings).
 */
final class CalkService
{
    public const NOTES_KEY = 'calk.notes';

    public function __construct(
        private readonly AccountBalanceQuery $balances,
        private readonly BalanceSheetService $balanceSheet,
        private readonly IncomeStatementService $incomeStatement,
        private readonly CashFlowService $cashFlow,
        private readonly TenantSettingService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();

        $bs = $this->balanceSheet->build($year, $month);
        $is = $this->incomeStatement->build($year, $month);
        $cf = $this->cashFlow->build($year, $month);

        $profile = OrganizationProfile::query()->first();
        $notes = $this->settings->get(self::NOTES_KEY, '');
        if (! is_string($notes)) {
            $notes = is_array($notes) ? (string) ($notes['body'] ?? '') : '';
        }

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
                'address' => $profile?->address,
                'registration_number' => $profile?->registration_number,
                'tax_number' => $profile?->tax_number,
            ],
            'notes' => $notes,
            'highlights' => [
                [
                    'key' => 'net_income',
                    'label' => 'Laba (rugi) bersih YTD',
                    'amount' => round((float) ($is['summary']['after_tax']['ytd'] ?? $this->balances->netIncome($asOf)), 2),
                ],
                [
                    'key' => 'total_asset',
                    'label' => 'Total aset',
                    'amount' => round((float) ($bs['totals']['assets'] ?? 0), 2),
                ],
                [
                    'key' => 'total_liability_equity',
                    'label' => 'Total utang + ekuitas',
                    'amount' => round((float) ($bs['totals']['liabilities_equity'] ?? 0), 2),
                ],
                [
                    'key' => 'cash_closing',
                    'label' => 'Saldo kas akhir',
                    'amount' => round((float) ($cf['closing_cash'] ?? 0), 2),
                ],
                [
                    'key' => 'cash_net',
                    'label' => 'Perubahan kas periode',
                    'amount' => round((float) ($cf['net_change'] ?? 0), 2),
                ],
            ],
            'policies' => [
                'Basis pencatatan adalah akrual dengan jurnal berpasangan (debit = kredit).',
                'Saldo bulanan merupakan projection dari jurnal posted, bukan sumber kebenaran terpisah.',
                'Piutang pinjaman diukur sebesar sisa pokok (due − paid) pada jadwal angsuran.',
                'Pendapatan jasa diakui saat diterima/dicatat pada jurnal angsuran.',
                'Aset kas meliputi akun dengan kode awalan 1.1.01.',
            ],
        ];
    }

    public function saveNotes(string $notes): void
    {
        $this->settings->set(self::NOTES_KEY, $notes, 'string');
    }
}
