<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;

/**
 * Kalkulator rencana angsuran pinjaman individu.
 * COPY RUMUS DARI PACUAN: PinjamanIndividuController::generate() & PinjamanIndividuController::generateRA().
 * Beda dengan LoanService::generatePrincipalSchedule (kelompok) yang generik:
 *   - Pakai setting per-tenant (OrganizationProfile): installment_rounding, disbursement_cutoff_day, village_installment_day
 *   - Magic formula untuk jangka == 24 (alokasi 6/8/12/14/18/20 juta)
 *   - Sistem angsuran: 11, 12, 14, 15, 20 (pokok/jasa) → tempo calculation
 *   - Mode mingguan (sistem == 12): +x*7 days
 *   - End-of-month handling
 *   - target_pokok, target_jasa (running total)
 */
final class MemberLoanScheduleCalculator
{
    /**
     * Hitung dan simpan jadwal angsuran ke loan_installments (replace existing).
     *
     * @param  string  $principalSystem  Sistem angsuran pokok (id: 11/12/14/15/20/custom)
     * @param  string  $interestSystem  Sistem angsuran jasa (id)
     * @param  float   $serviceRateTotal  prosentase jasa total (mis. 24 untuk 24%)
     * @param  string  $interestMethod  'flat' atau 'effective' (sliding)
     * @param  CarbonImmutable  $disbursementDate  tanggal cair / proposal
     * @return int  jumlah baris angsuran yang dibuat
     */
    public function recalculate(
        Loan $loan,
        int $term,
        float $principal,
        string $principalSystem,
        string $interestSystem,
        float $serviceRateTotal,
        string $interestMethod,
        CarbonImmutable $disbursementDate,
    ): int {
        $loan->loadMissing('borrower.member.address.village');

        $profile = OrganizationProfile::query()->first();
        $rounding = (string) ($profile?->installment_rounding ?? '5000');
        $cutoffDay = (int) ($profile?->disbursement_cutoff_day ?? 0);
        $villageDay = (int) ($profile?->village_installment_day ?? 0);

        // Override tanggal dari jadwal angsuran desa (kalau ada)
        $dueDay = (int) $disbursementDate->format('d');
        if ($villageDay > 0) {
            $dueDay = $villageDay;
        }

        // Batas tanggal — kalau tanggal cair >= cutoff, angsuran pertama +1 bulan
        $firstDue = $disbursementDate->setDay($dueDay);
        if ($cutoffDay > 0 && (int) $disbursementDate->format('d') >= $cutoffDay) {
            $firstDue = $firstDue->addMonth();
        }

        $principalPeriods = $this->tempo($term, $principalSystem);
        $interestPeriods = $this->tempo($term, $interestSystem);

        $principalPeriodAmount = $this->bulatkan($principal / $principalPeriods, $rounding);
        $sumPokok = $principalPeriodAmount * ($principalPeriods - 1);

        $totalInterest = $principal * ($serviceRateTotal / 100);
        $interestPeriodAmount = $this->bulatkan($totalInterest / $interestPeriods, $rounding);
        $sumJasa = $interestPeriodAmount * ($interestPeriods - 1);

        $rows = [];
        $remainingPrincipal = $principal;
        $runningPrincipal = 0.0;
        $runningInterest = 0.0;

        for ($x = 1; $x <= $term; $x++) {
            // Hitung jatuh tempo
            if ($principalSystem === '12') {
                $due = $firstDue->addDays($x * 7);
            } else {
                $due = $firstDue->addMonths($x);
                // End-of-month handling
                $originalDay = (int) $disbursementDate->format('d');
                $lastDayOfMonth = (int) $due->format('t');
                if ($originalDay > $lastDayOfMonth) {
                    $due = $due->setDay($lastDayOfMonth);
                }
            }

            // Pokok angsuran ke-x
            if ($x === $term) {
                // Angsuran terakhir = sisa
                $principalDue = round($remainingPrincipal, 2);
            } else {
                $p = $principalPeriodAmount;

                // Magic formula pacuan: jangka == 24 dgn adjustment alokasi 6/8/12/14/18 juta
                if ($term === 24) {
                    $jasa = $this->bulatkan(($principal / 10) - $this->interestForMonth($x, $principal, $serviceRateTotal, $interestSystem), -500);
                    $p = $jasa / 2;
                    if ($principal > 1_000_000) {
                        $p = $this->bulatkan((($principal / 10) - $this->interestForMonth($x, $principal, $serviceRateTotal, $interestSystem)) / 2, 5000);
                    }
                    if ($principal !== 20_000_000) {
                        if ($principal >= 8_000_000) {
                            $p -= 5000;
                        }
                        if ($principal === 12_000_000 || $principal >= 14_000_000) {
                            $p -= 5000;
                        }
                        if ($principal === 18_000_000 || $principal === 6_000_000) {
                            $p -= 5000;
                        }
                    }
                    $p = round($p);
                }

                $principalDue = round($p, 2);
                $remainingPrincipal = round($remainingPrincipal - $principalDue, 2);
            }

            // Jasa angsuran ke-x
            $interestDue = 0.0;
            if ($interestMethod === 'effective') {
                // Sliding: jasa dihitung dari sisa pokok
                $interestDue = round($remainingPrincipal * ($serviceRateTotal / 100), 2);
            } else {
                // Flat: jasa konstan, terakhir = selisih agar pas
                if ($x === $term) {
                    $interestDue = round($totalInterest - $sumJasa, 2);
                } else {
                    $interestDue = round($interestPeriodAmount, 2);
                }
            }

            $runningPrincipal = round($runningPrincipal + $principalDue, 2);
            $runningInterest = round($runningInterest + $interestDue, 2);

            $rows[] = [
                'installment_number' => $x,
                'due_date' => $due->toDateString(),
                'principal_due' => $principalDue,
                'interest_due' => $interestDue,
                'running_principal' => $runningPrincipal,
                'running_interest' => $runningInterest,
            ];
        }

        // Replace existing installments
        LoanInstallment::query()->where('loan_row_id', $loan->row_id)->delete();
        $now = now();
        foreach ($rows as $r) {
            LoanInstallment::query()->create([
                'loan_row_id' => $loan->row_id,
                'installment_number' => $r['installment_number'],
                'due_date' => $r['due_date'],
                'principal_due' => $r['principal_due'],
                'interest_due' => $r['interest_due'],
                'principal_paid' => 0,
                'interest_paid' => 0,
                'penalty_due' => 0,
                'penalty_paid' => 0,
                'status' => 'pending',
                'paid_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return count($rows);
    }

    /**
     * Tentukan tempo (jumlah periode angsuran efektif).
     *
     * Sistem angsuran pacuan:
     *  11 → mingguan berbatas (jangka - 24 / sistem) — jarang, khusus
     *  12 → mingguan (+x * 7 days) — handled separately
     *  14 → bulanan dgn grace 3 bulan (jangka - 3 / sistem)
     *  15 → bulanan dgn grace 2 bulan (jangka - 2 / sistem)
     *  20 → bulanan dgn grace 12 bulan (jangka - 12 / sistem)
     *  else (1 bulanan, 3 triwulan, 6 semester) → floor(jangka / sistem)
     */
    private function tempo(int $term, string $system): int
    {
        return match ((int) $system) {
            11 => max(1, intdiv($term - 24, max(1, (int) $system))),
            14 => max(1, intdiv($term - 3, max(1, (int) $system))),
            15 => max(1, intdiv($term - 2, max(1, (int) $system))),
            20 => max(1, intdiv($term - 12, max(1, (int) $system))),
            default => max(1, intdiv($term, max(1, (int) $system))),
        };
    }

    /**
     * Hitung jasa bulan ke-x (untuk magic formula jangka==24).
     */
    private function interestForMonth(int $x, float $principal, float $serviceRateTotal, string $system): float
    {
        $tempo = $this->tempo(1, $system); // tempo per bulan
        $totalInterest = $principal * ($serviceRateTotal / 100);

        return $totalInterest / max(1, $tempo);
    }

    /**
     * Pembulatan ala Keuangan::pembulatan pacuan.
     *  '5000' auto: < 2500 → turun, >= 2500 → naik ke 5000
     *  '+5000' selalu ke atas
     *  '-5000' selalu ke bawah
     */
    private function bulatkan(float $value, string $mode): float
    {
        $value = round($value);

        $sistem = 'auto';
        $pembulatan = $mode;
        if (str_starts_with($mode, '+')) {
            $sistem = 'keatas';
            $pembulatan = (string) ((int) $mode);
        }
        if (str_starts_with($mode, '-')) {
            $sistem = 'kebawah';
            $pembulatan = (string) abs((int) $mode);
        }

        $intVal = (int) $pembulatan;
        if ($intVal <= 0) {
            return $value;
        }
        $ratusan = (int) substr((string) $value, -strlen((string) ($intVal / 2 === 0 ? 1 : $intVal / 2)));
        // Fallback pembulatan sederhana (ribuan)
        if ($intVal === 5000) {
            $sisa = (int) $value % 5000;
            if ($sisa < 2500) {
                return (float) ($value - $sisa);
            }

            return (float) ($value + (5000 - $sisa));
        }

        return match ($sistem) {
            'keatas' => (float) ($value + ($intVal - ((int) $value % $intVal))),
            'kebawah' => (float) ($value - ((int) $value % $intVal)),
            default => $value,
        };
    }
}
