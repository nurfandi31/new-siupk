<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\OrganizationProfile;
use DomainException;

/**
 * Kartu angsuran per pinjaman — jadwal + realisasi ringkas.
 * Referensi legacy kartu_angsuran; layout Next lebih rapat & readable.
 */
final class LoanCardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Loan $loan): array
    {
        $loan->loadMissing([
            'product:row_id,code,name',
            'borrower.group:row_id,name,address,organization_unit_row_id',
            'borrower.group.village:row_id,name',
            'installments',
            'committee.member.person',
            'beneficiaries.member.person',
        ]);

        if (! in_array($loan->status, ['active', 'disbursed', 'completed', 'written_off', 'rescheduled'], true)) {
            // Still allow print for pipeline loans that already have schedule
            if ($loan->installments->isEmpty()) {
                throw new DomainException('Pinjaman belum memiliki jadwal angsuran.');
            }
        }

        // Merge dual-component installments by number
        $grouped = [];
        foreach ($loan->installments as $inst) {
            $n = (int) $inst->installment_number;
            if (! isset($grouped[$n])) {
                $grouped[$n] = [
                    'installment_number' => $n,
                    'due_date' => $inst->due_date?->format('Y-m-d'),
                    'principal_due' => 0.0,
                    'interest_due' => 0.0,
                    'principal_paid' => 0.0,
                    'interest_paid' => 0.0,
                    'penalty_due' => 0.0,
                    'penalty_paid' => 0.0,
                ];
            }
            $grouped[$n]['principal_due'] = round($grouped[$n]['principal_due'] + (float) $inst->principal_due, 2);
            $grouped[$n]['interest_due'] = round($grouped[$n]['interest_due'] + (float) $inst->interest_due, 2);
            $grouped[$n]['principal_paid'] = round($grouped[$n]['principal_paid'] + (float) $inst->principal_paid, 2);
            $grouped[$n]['interest_paid'] = round($grouped[$n]['interest_paid'] + (float) $inst->interest_paid, 2);
            $grouped[$n]['penalty_due'] = round($grouped[$n]['penalty_due'] + (float) $inst->penalty_due, 2);
            $grouped[$n]['penalty_paid'] = round($grouped[$n]['penalty_paid'] + (float) $inst->penalty_paid, 2);
            // keep earliest due if mixed
            $due = $inst->due_date?->format('Y-m-d');
            if ($due && ($grouped[$n]['due_date'] === null || $due < $grouped[$n]['due_date'])) {
                $grouped[$n]['due_date'] = $due;
            }
        }
        ksort($grouped);

        $rows = [];
        $totPlanP = $totPlanI = $totPaidP = $totPaidI = 0.0;
        foreach ($grouped as $g) {
            $pRem = max(0.0, round($g['principal_due'] - $g['principal_paid'], 2));
            $iRem = max(0.0, round($g['interest_due'] - $g['interest_paid'], 2));
            $status = 'pending';
            if ($pRem <= 0.009 && $iRem <= 0.009 && ($g['principal_due'] + $g['interest_due']) > 0) {
                $status = 'paid';
            } elseif ($g['principal_paid'] + $g['interest_paid'] > 0.009) {
                $status = 'partial';
            }
            $rows[] = [
                ...$g,
                'principal_remaining' => $pRem,
                'interest_remaining' => $iRem,
                'status' => $status,
            ];
            $totPlanP = round($totPlanP + $g['principal_due'], 2);
            $totPlanI = round($totPlanI + $g['interest_due'], 2);
            $totPaidP = round($totPaidP + $g['principal_paid'], 2);
            $totPaidI = round($totPaidI + $g['interest_paid'], 2);
        }

        $committee = [];
        foreach ($loan->committee as $c) {
            $committee[] = [
                'position' => (string) $c->position,
                'name' => (string) ($c->member?->person?->full_name ?? '—'),
            ];
        }

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name', 'address']);
        $group = $loan->borrower?->group;

        return [
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
                'address' => $profile?->address,
            ],
            'loan' => [
                'row_id' => (int) $loan->row_id,
                'id' => (int) $loan->id,
                'loan_number' => $loan->loan_number,
                'status' => (string) $loan->status,
                'product_code' => $loan->product?->code,
                'product_name' => $loan->product?->name,
                'principal_amount' => round((float) $loan->principal_amount, 2),
                'interest_rate' => (float) ($loan->service_rate_total ?? $loan->interest_rate ?? 0),
                'term_months' => (int) $loan->term_months,
                'disbursed_at' => $loan->disbursed_at?->format('Y-m-d'),
                'group_name' => $group?->name,
                'group_address' => $group?->address,
                'village_name' => $group?->village?->name,
                'beneficiaries_count' => $loan->beneficiaries->count(),
            ],
            'committee' => $committee,
            'rows' => array_values($rows),
            'totals' => [
                'plan_principal' => $totPlanP,
                'plan_interest' => $totPlanI,
                'paid_principal' => $totPaidP,
                'paid_interest' => $totPaidI,
                'remaining_principal' => round($totPlanP - $totPaidP, 2),
                'remaining_interest' => round($totPlanI - $totPaidI, 2),
            ],
            'period' => [
                'period_label' => 'Kartu Angsuran · Pinjaman #'.$loan->id,
                'as_of' => now()->toDateString(),
            ],
        ];
    }
}
