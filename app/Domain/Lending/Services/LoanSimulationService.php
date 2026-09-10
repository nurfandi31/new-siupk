<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use Carbon\CarbonImmutable;

final class LoanSimulationService
{
    private const FREQUENCY_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semi_annually' => 6,
        'annually' => 12,
        'every_4_months' => 4,
        'every_5_months' => 5,
        'every_6_months' => 6,
        'every_7_months' => 7,
        'every_8_months' => 8,
        'every_9_months' => 9,
        'every_10_months' => 10,
        'every_11_months' => 11,
        'every_12_months' => 12,
        'every_24_months' => 24,
        'every_36_months' => 36,
        'at_maturity' => 0,
    ];

    /**
     * Calculate loan simulation schedule and key metrics.
     *
     * @param  array<string, mixed>  $params
     * @return array{
     *   parameters: array<string, mixed>,
     *   summary: array{
     *     principal_amount: float,
     *     total_interest: float,
     *     total_payment: float,
     *     estimated_monthly_payment: float,
     *     term_months: int,
     *     interest_rate: float,
     *     rate_unit: string,
     *     interest_rate_monthly: float,
     *     interest_rate_annual: float,
     *     installment_method: string,
     *     rounding_step: int,
     *   },
     *   schedule: list<array{
     *     number: int,
     *     due_date: string,
     *     principal_due: float,
     *     interest_due: float,
     *     total_due: float,
     *     remaining_principal: float,
     *   }>
     * }
     */
    public function simulate(array $params): array
    {
        $principal = max(0.0, (float) ($params['principal_amount'] ?? 10000000));
        $termMonths = max(1, (int) ($params['term_months'] ?? 12));
        $rawRate = max(0.0, (float) ($params['interest_rate'] ?? 1.5));
        $rateUnit = (string) ($params['rate_unit'] ?? '');

        // Standardize monthly vs annual rate
        if ($rateUnit === 'monthly') {
            $rateMonthly = $rawRate;
            $rateAnnual = $rawRate * 12;
        } elseif ($rateUnit === 'annual') {
            $rateAnnual = $rawRate;
            $rateMonthly = $rawRate / 12;
        } else {
            // Auto-detect: values >= 10 without explicit unit are treated as annual rate (e.g. 12% p.a.)
            if ($rawRate >= 10.0) {
                $rateAnnual = $rawRate;
                $rateMonthly = $rawRate / 12;
                $rateUnit = 'annual';
            } else {
                $rateMonthly = $rawRate;
                $rateAnnual = $rawRate * 12;
                $rateUnit = 'monthly';
            }
        }

        $method = (string) ($params['installment_method'] ?? 'flat');
        if (! in_array($method, ['flat', 'annuity', 'declining'], true)) {
            $method = 'flat';
        }

        $principalFreq = (string) ($params['principal_frequency'] ?? 'monthly');
        $interestFreq = (string) ($params['interest_frequency'] ?? 'monthly');
        $principalGrace = max(0, (int) ($params['principal_grace_months'] ?? 0));
        $interestGrace = max(0, (int) ($params['interest_grace_months'] ?? 0));
        $roundingStep = isset($params['rounding_step']) ? max(0, (int) $params['rounding_step']) : 500;
        $startDateStr = (string) ($params['start_date'] ?? date('Y-m-d'));
        $start = CarbonImmutable::parse($startDateStr);

        $schedule = match ($method) {
            'annuity' => $this->calculateAnnuity($principal, $termMonths, $rateMonthly, $principalFreq, $roundingStep, $start, $principalGrace),
            'declining' => $this->calculateDeclining($principal, $termMonths, $rateMonthly, $principalFreq, $roundingStep, $start, $principalGrace),
            default => $this->calculateFlat($principal, $termMonths, $rateMonthly, $principalFreq, $interestFreq, $roundingStep, $start, $principalGrace, $interestGrace),
        };

        $totalInterest = 0.0;
        $totalPrincipal = 0.0;
        foreach ($schedule as $row) {
            $totalPrincipal += $row['principal_due'];
            $totalInterest += $row['interest_due'];
        }

        $totalPayment = $totalPrincipal + $totalInterest;
        $estimatedMonthly = $termMonths > 0 ? round($totalPayment / $termMonths, 2) : 0.0;

        return [
            'parameters' => [
                'principal_amount' => $principal,
                'term_months' => $termMonths,
                'interest_rate' => $rawRate,
                'rate_unit' => $rateUnit,
                'installment_method' => $method,
                'principal_frequency' => $principalFreq,
                'interest_frequency' => $interestFreq,
                'principal_grace_months' => $principalGrace,
                'interest_grace_months' => $interestGrace,
                'rounding_step' => $roundingStep,
                'start_date' => $startDateStr,
            ],
            'summary' => [
                'principal_amount' => round($principal, 2),
                'total_interest' => round($totalInterest, 2),
                'total_payment' => round($totalPayment, 2),
                'estimated_monthly_payment' => round($estimatedMonthly, 2),
                'term_months' => $termMonths,
                'interest_rate' => round($rawRate, 4),
                'rate_unit' => $rateUnit,
                'interest_rate_monthly' => round($rateMonthly, 4),
                'interest_rate_annual' => round($rateAnnual, 4),
                'installment_method' => $method,
                'rounding_step' => $roundingStep,
            ],
            'schedule' => $schedule,
        ];
    }

    /**
     * Flat calculation: bunga dihitung dari plafon awal secara merata.
     *
     * @return list<array{number: int, due_date: string, principal_due: float, interest_due: float, total_due: float, remaining_principal: float}>
     */
    private function calculateFlat(
        float $principal,
        int $termMonths,
        float $rateMonthly,
        string $principalFreq,
        string $interestFreq,
        int $roundingStep,
        CarbonImmutable $start,
        int $principalGrace = 0,
        int $interestGrace = 0,
    ): array {
        $pPeriods = $this->periodsCount($principalFreq, $termMonths, $principalGrace);
        $iPeriods = $this->periodsCount($interestFreq, $termMonths, $interestGrace);
        $totalInterest = $principal * ($rateMonthly / 100) * $termMonths;

        $rawPrincipalPerPeriod = $pPeriods > 0 ? $principal / $pPeriods : $principal;
        $roundedPrincipal = $this->roundValue($rawPrincipalPerPeriod, $roundingStep);

        $rawInterestPerPeriod = $iPeriods > 0 ? $totalInterest / $iPeriods : $totalInterest;
        $roundedInterest = $this->roundValue($rawInterestPerPeriod, $roundingStep);

        // When principal and interest frequencies are identical (e.g. monthly-monthly)
        if ($principalFreq === $interestFreq && $principalGrace === $interestGrace) {
            $periods = $pPeriods;
            $schedule = [];
            $accumulatedPrincipal = 0.0;
            $accumulatedInterest = 0.0;
            $remaining = $principal;

            for ($i = 1; $i <= $periods; $i++) {
                $pDue = ($i === $periods)
                    ? round($principal - $accumulatedPrincipal, 2)
                    : $roundedPrincipal;
                $accumulatedPrincipal = round($accumulatedPrincipal + $pDue, 2);

                $iDue = ($i === $periods)
                    ? round($totalInterest - $accumulatedInterest, 2)
                    : $roundedInterest;
                $accumulatedInterest = round($accumulatedInterest + $iDue, 2);

                $remaining = max(0.0, round($remaining - $pDue, 2));
                $dueDate = $this->advanceDate($start, $principalFreq, $i + $principalGrace, $termMonths);

                $schedule[] = [
                    'number' => $i,
                    'due_date' => $dueDate->toDateString(),
                    'principal_due' => $pDue,
                    'interest_due' => $iDue,
                    'total_due' => round($pDue + $iDue, 2),
                    'remaining_principal' => $remaining,
                ];
            }

            return $schedule;
        }

        // Split frequencies (e.g. interest monthly, principal at_maturity or quarterly)
        $schedule = [];
        $accumulatedPrincipal = 0.0;
        $accumulatedInterest = 0.0;
        $remaining = $principal;

        $pMonthsStep = self::FREQUENCY_MONTHS[$principalFreq] ?? 1;
        $iMonthsStep = self::FREQUENCY_MONTHS[$interestFreq] ?? 1;

        $principalDueMap = [];
        for ($p = 1; $p <= $pPeriods; $p++) {
            $m = $principalFreq === 'at_maturity' ? $termMonths : ($p + $principalGrace) * $pMonthsStep;
            $pDue = ($p === $pPeriods) ? round($principal - $accumulatedPrincipal, 2) : $roundedPrincipal;
            $accumulatedPrincipal = round($accumulatedPrincipal + $pDue, 2);
            $principalDueMap[$m] = $pDue;
        }

        $interestDueMap = [];
        for ($it = 1; $it <= $iPeriods; $it++) {
            $m = $interestFreq === 'at_maturity' ? $termMonths : ($it + $interestGrace) * $iMonthsStep;
            $iDue = ($it === $iPeriods) ? round($totalInterest - $accumulatedInterest, 2) : $roundedInterest;
            $accumulatedInterest = round($accumulatedInterest + $iDue, 2);
            $interestDueMap[$m] = $iDue;
        }

        for ($m = 1; $m <= $termMonths; $m++) {
            $pDue = $principalDueMap[$m] ?? 0.0;
            $iDue = $interestDueMap[$m] ?? 0.0;
            if ($pDue <= 0.0 && $iDue <= 0.0) {
                continue;
            }

            $remaining = max(0.0, round($remaining - $pDue, 2));
            $dueDate = $start->addMonths($m);

            $schedule[] = [
                'number' => count($schedule) + 1,
                'due_date' => $dueDate->toDateString(),
                'principal_due' => $pDue,
                'interest_due' => $iDue,
                'total_due' => round($pDue + $iDue, 2),
                'remaining_principal' => $remaining,
            ];
        }

        return $schedule;
    }

    /**
     * Declining balance (Efektif Menurun): pokok tetap per periode, bunga dihitung dari sisa pokok pinjaman.
     *
     * @return list<array{number: int, due_date: string, principal_due: float, interest_due: float, total_due: float, remaining_principal: float}>
     */
    private function calculateDeclining(
        float $principal,
        int $termMonths,
        float $rateMonthly,
        string $principalFreq,
        int $roundingStep,
        CarbonImmutable $start,
        int $principalGrace = 0,
    ): array {
        $pPeriods = $this->periodsCount($principalFreq, $termMonths, $principalGrace);
        $rawPrincipalPerPeriod = $pPeriods > 0 ? $principal / $pPeriods : $principal;
        $roundedPrincipal = $this->roundValue($rawPrincipalPerPeriod, $roundingStep);

        $monthsPerPeriod = $pPeriods > 0 ? (int) round($termMonths / $pPeriods) : 1;
        $periodicRate = ($rateMonthly / 100) * $monthsPerPeriod;

        $schedule = [];
        $accumulatedPrincipal = 0.0;
        $remaining = $principal;

        for ($i = 1; $i <= $pPeriods; $i++) {
            $pDue = ($i === $pPeriods)
                ? round($principal - $accumulatedPrincipal, 2)
                : min($remaining, $roundedPrincipal);
            $accumulatedPrincipal = round($accumulatedPrincipal + $pDue, 2);

            $rawInterest = $remaining * $periodicRate;
            $iDue = $this->roundValue($rawInterest, $roundingStep);
            $remaining = max(0.0, round($remaining - $pDue, 2));
            $dueDate = $this->advanceDate($start, $principalFreq, $i + $principalGrace, $termMonths);

            $schedule[] = [
                'number' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal_due' => $pDue,
                'interest_due' => $iDue,
                'total_due' => round($pDue + $iDue, 2),
                'remaining_principal' => $remaining,
            ];
        }

        return $schedule;
    }

    /**
     * Annuity calculation: angsuran total (pokok + bunga) tetap setiap periode, bunga dihitung dari saldo pokok.
     *
     * @return list<array{number: int, due_date: string, principal_due: float, interest_due: float, total_due: float, remaining_principal: float}>
     */
    private function calculateAnnuity(
        float $principal,
        int $termMonths,
        float $rateMonthly,
        string $principalFreq,
        int $roundingStep,
        CarbonImmutable $start,
        int $principalGrace = 0,
    ): array {
        $periods = $this->periodsCount($principalFreq, $termMonths, $principalGrace);
        $monthsPerPeriod = $periods > 0 ? (int) round($termMonths / $periods) : 1;
        $periodicRate = ($rateMonthly / 100) * $monthsPerPeriod;

        if ($periodicRate > 0) {
            $rawPmt = $principal * ($periodicRate * (1 + $periodicRate) ** $periods) / (((1 + $periodicRate) ** $periods) - 1);
        } else {
            $rawPmt = $principal / max(1, $periods);
        }

        $pmt = $this->roundValue($rawPmt, $roundingStep);

        $schedule = [];
        $remaining = $principal;

        for ($i = 1; $i <= $periods; $i++) {
            $rawInterest = $remaining * $periodicRate;
            $iDue = $this->roundValue($rawInterest, $roundingStep);

            if ($i === $periods) {
                $pDue = $remaining;
                $remaining = 0.0;
            } else {
                $pDue = max(0.0, round($pmt - $iDue, 2));
                if ($pDue > $remaining) {
                    $pDue = $remaining;
                }
                $remaining = max(0.0, round($remaining - $pDue, 2));
            }

            $dueDate = $this->advanceDate($start, $principalFreq, $i + $principalGrace, $termMonths);

            $schedule[] = [
                'number' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal_due' => $pDue,
                'interest_due' => $iDue,
                'total_due' => round($pDue + $iDue, 2),
                'remaining_principal' => $remaining,
            ];
        }

        return $schedule;
    }

    private function periodsCount(string $frequency, int $termMonths, int $graceMonths = 0): int
    {
        if ($frequency === 'at_maturity') {
            return 1;
        }

        $step = self::FREQUENCY_MONTHS[$frequency] ?? 1;
        if ($step <= 0) {
            return 1;
        }

        $firstPeriod = $graceMonths + $step;

        return max(1, (int) floor(($termMonths - $firstPeriod) / $step) + 1);
    }

    private function advanceDate(CarbonImmutable $start, string $frequency, int $periodIndex, int $termMonths): CarbonImmutable
    {
        if ($frequency === 'at_maturity') {
            return $start->addMonths($termMonths);
        }

        $step = self::FREQUENCY_MONTHS[$frequency] ?? 1;

        return $start->addMonths($periodIndex * $step);
    }

    public function roundValue(float $amount, int|string $step): float
    {
        $s = is_numeric($step) ? (int) $step : 0;
        if ($s <= 1) {
            return round($amount, 2);
        }

        return (float) (round($amount / $s) * $s);
    }
}
