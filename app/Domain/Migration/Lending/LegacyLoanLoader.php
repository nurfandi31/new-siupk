<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending;

use App\Domain\Migration\Lending\DTO\NormalizedBeneficiary;
use App\Domain\Migration\Lending\DTO\NormalizedGroupLoan;
use App\Domain\Migration\Lending\DTO\NormalizedInstallment;
use App\Domain\Migration\Lending\DTO\NormalizedPayment;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LegacyLoanLoader
{
    public function __construct(
        private TenantContext $context,
        private TenantSequenceService $sequences,
    ) {}

    /**
     * @param  list<NormalizedGroupLoan>  $loans
     * @return array{inserted: int, skipped: int, errors: list<string>}
     */
    public function loadLoans(int $batchRowId, string $sourceTable, array $loans): array
    {
        if ($loans === []) {
            return ['inserted' => 0, 'skipped' => 0, 'errors' => []];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;
        $skipped = 0;
        $errors = [];

        $groups = DB::connection($connName)->table('groups')
            ->where('tenant_id', $tenantId)
            ->pluck('row_id', 'id')
            ->map(fn ($v) => (int) $v)
            ->all();

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId, $batchRowId, $sourceTable, $loans, $now, $groups,
            &$inserted, &$skipped, &$errors,
        ): void {
            foreach ($loans as $loan) {
                $exists = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $sourceTable)
                    ->where('source_id', (string) $loan->legacyId)
                    ->where('source_secondary_key', 'loan')
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }

                $groupRowId = $groups[$loan->groupLegacyId] ?? null;
                if ($groupRowId === null) {
                    $errors[] = "pk id={$loan->legacyId}: group id={$loan->groupLegacyId} not migrated";

                    continue;
                }

                $loanNumber = $loan->loanNumber;
                if ($loanNumber !== null) {
                    $taken = $db->table('loans')
                        ->where('tenant_id', $tenantId)
                        ->where('loan_number', $loanNumber)
                        ->exists();
                    if ($taken) {
                        $loanNumber = 'PK-'.$loan->legacyId;
                    }
                }

                $existingLoan = $db->table('loans')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $loan->legacyId)
                    ->first(['row_id']);

                if ($existingLoan !== null) {
                    $loanRowId = (int) $existingLoan->row_id;
                    $db->table('loans')
                        ->where('row_id', $loanRowId)
                        ->update([
                            'loan_number' => $loanNumber,
                            'loan_product_row_id' => $loan->productRowId,
                            'sequence_number' => $loan->sequenceNumber,
                            'proposed_at' => $loan->proposedAt,
                            'verified_at' => $loan->verifiedAt,
                            'approved_at' => $loan->approvedAt,
                            'funded_at' => $loan->fundedAt,
                            'disbursed_at' => $loan->disbursedAt,
                            'completed_at' => $loan->completedAt,
                            'principal_amount' => $loan->principal,
                            'interest_rate' => $loan->interestRate,
                            'term_months' => $loan->termMonths,
                            'installment_method' => $loan->installmentMethod,
                            'principal_frequency' => $loan->principalFrequency,
                            'interest_frequency' => $loan->interestFrequency,
                            'principal_grace_months' => $loan->principalGraceMonths,
                            'interest_grace_months' => $loan->interestGraceMonths,
                            'status' => $loan->status,
                            'updated_at' => $now,
                        ]);
                } else {
                    $loanRowId = (int) $db->table('loans')->insertGetId([
                        'tenant_id' => $tenantId,
                        'id' => $loan->legacyId,
                        'legacy_source' => 'group_loan',
                        'public_id' => (string) Str::ulid(),
                        'loan_number' => $loanNumber,
                        'loan_product_row_id' => $loan->productRowId,
                        'sequence_number' => $loan->sequenceNumber,
                        'proposed_at' => $loan->proposedAt,
                        'verified_at' => $loan->verifiedAt,
                        'approved_at' => $loan->approvedAt,
                        'funded_at' => $loan->fundedAt,
                        'disbursed_at' => $loan->disbursedAt,
                        'completed_at' => $loan->completedAt,
                        'disbursement_account_row_id' => null,
                        'disbursement_notes' => null,
                        'principal_amount' => $loan->principal,
                        'interest_rate' => $loan->interestRate,
                        'term_months' => $loan->termMonths,
                        'installment_method' => $loan->installmentMethod,
                        'principal_frequency' => $loan->principalFrequency,
                        'interest_frequency' => $loan->interestFrequency,
                        'principal_grace_months' => $loan->principalGraceMonths,
                        'interest_grace_months' => $loan->interestGraceMonths,
                        'service_rate_total' => $loan->interestRate,
                        'status' => $loan->status,
                        'verification_notes' => $loan->verificationNotes,
                        'guidance_notes' => $loan->guidanceNotes,
                        'verification_time' => $loan->verificationTime,
                        'disbursement_schedule_text' => $loan->disbursementScheduleText,
                        'created_by_user_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'row_id');
                }

                $hasBorrower = $db->table('loan_borrowers')
                    ->where('loan_row_id', $loanRowId)
                    ->exists();
                if (! $hasBorrower) {
                    $borrowerId = $this->sequences->next('loan_borrowers');
                    $db->table('loan_borrowers')->insert([
                        'tenant_id' => $tenantId,
                        'id' => $borrowerId,
                        'loan_row_id' => $loanRowId,
                        'member_row_id' => null,
                        'group_row_id' => $groupRowId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $hasHist = $db->table('loan_status_histories')
                    ->where('loan_row_id', $loanRowId)
                    ->exists();
                if (! $hasHist) {
                    $stages = [
                        [
                            'from_status' => null,
                            'to_status' => 'draft',
                            'changed_at' => $loan->proposedAt ?? substr($now, 0, 10),
                            'principal_amount' => $this->snapshotAmount($loan->snapshot, ['proposal'], $loan->principal),
                            'notes' => 'Proposal legacy.',
                        ],
                        [
                            'from_status' => 'draft',
                            'to_status' => 'verified',
                            'changed_at' => $loan->verifiedAt,
                            'principal_amount' => $this->snapshotAmount($loan->snapshot, ['verifikasi'], $loan->principal),
                            'notes' => $loan->verificationNotes ?? 'Verifikasi legacy.',
                        ],
                        [
                            'from_status' => 'verified',
                            'to_status' => 'waiting',
                            'changed_at' => $loan->fundedAt,
                            'principal_amount' => $this->snapshotAmount($loan->snapshot, ['alokasi'], $loan->principal),
                            'notes' => 'Penetapan alokasi legacy.',
                        ],
                        [
                            'from_status' => 'waiting',
                            'to_status' => 'active',
                            'changed_at' => $loan->disbursedAt,
                            'principal_amount' => $this->snapshotAmount($loan->snapshot, ['alokasi'], $loan->principal),
                            'notes' => 'Pencairan legacy.',
                        ],
                    ];

                    foreach ($stages as $stage) {
                        if ($stage['changed_at'] === null) {
                            continue;
                        }

                        $histId = $this->sequences->next('loan_status_histories');
                        $db->table('loan_status_histories')->insert([
                            'tenant_id' => $tenantId,
                            'id' => $histId,
                            'loan_row_id' => $loanRowId,
                            'from_status' => $stage['from_status'],
                            'to_status' => $stage['to_status'],
                            'principal_amount' => $stage['principal_amount'],
                            'product_row_id' => $loan->productRowId,
                            'term_months' => $loan->termMonths,
                            'service_rate_total' => $loan->interestRate,
                            'principal_frequency' => $loan->principalFrequency,
                            'interest_frequency' => $loan->interestFrequency,
                            'principal_grace_months' => $loan->principalGraceMonths,
                            'interest_grace_months' => $loan->interestGraceMonths,
                            'notes' => $stage['notes'],
                            'changed_by_user_id' => null,
                            'changed_at' => $stage['changed_at'].' 00:00:00',
                            'created_at' => $now,
                        ]);
                    }
                }

                $db->table('legacy_record_mappings')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'source_table' => $sourceTable,
                        'source_id' => (string) $loan->legacyId,
                        'source_secondary_key' => 'loan',
                    ],
                    [
                        'batch_row_id' => $batchRowId,
                        'target_table' => 'loans',
                        'target_row_id' => $loanRowId,
                        'target_local_id' => $loan->legacyId,
                        'source_snapshot' => json_encode($loan->snapshot, JSON_THROW_ON_ERROR),
                        'migrated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $inserted++;
            }
        }, 5);

        return ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  list<NormalizedBeneficiary>  $rows
     * @return array{inserted: int, skipped: int, errors: list<string>, warnings: list<string>}
     */
    public function loadBeneficiaries(int $batchRowId, string $sourceTable, array $rows): array
    {
        if ($rows === []) {
            return ['inserted' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => []];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $warnings = [];

        $loanMap = $this->loanRowMap($connName, $tenantId);
        $memberMap = DB::connection($connName)->table('members')
            ->where('tenant_id', $tenantId)
            ->pluck('row_id', 'id')
            ->map(fn ($v) => (int) $v)
            ->all();

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId, $batchRowId, $sourceTable, $rows, $now, $loanMap, $memberMap,
            &$inserted, &$skipped, &$errors, &$warnings,
        ): void {
            foreach ($rows as $b) {
                $exists = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $sourceTable)
                    ->where('source_id', (string) $b->legacyId)
                    ->where('source_secondary_key', 'beneficiary')
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }

                $loanRowId = $loanMap[$b->groupLoanLegacyId] ?? null;
                if ($loanRowId === null) {
                    $warnings[] = "pa id={$b->legacyId}: parent loan {$b->groupLoanLegacyId} missing";

                    continue;
                }
                $memberRowId = $memberMap[$b->memberLegacyId] ?? null;
                if ($memberRowId === null) {
                    $warnings[] = "pa id={$b->legacyId}: member nia={$b->memberLegacyId} missing";

                    continue;
                }

                // unique (tenant, loan, member)
                $dup = $db->table('loan_beneficiaries')
                    ->where('tenant_id', $tenantId)
                    ->where('loan_row_id', $loanRowId)
                    ->where('member_row_id', $memberRowId)
                    ->exists();
                if ($dup) {
                    $warnings[] = "pa id={$b->legacyId}: duplicate beneficiary loan={$b->groupLoanLegacyId} member={$b->memberLegacyId}";

                    continue;
                }

                $id = $this->sequences->next('loan_beneficiaries');
                $rowId = $db->table('loan_beneficiaries')->insertGetId([
                    'tenant_id' => $tenantId,
                    'id' => $id,
                    'loan_row_id' => $loanRowId,
                    'member_row_id' => $memberRowId,
                    'allocated_amount' => $b->allocated,
                    'proposed_amount' => $b->proposed,
                    'verified_amount' => $b->verified,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'row_id');

                $db->table('legacy_record_mappings')->insert([
                    'tenant_id' => $tenantId,
                    'batch_row_id' => $batchRowId,
                    'source_table' => $sourceTable,
                    'source_id' => (string) $b->legacyId,
                    'source_secondary_key' => 'beneficiary',
                    'target_table' => 'loan_beneficiaries',
                    'target_row_id' => $rowId,
                    'target_local_id' => $id,
                    'source_snapshot' => json_encode($b->snapshot, JSON_THROW_ON_ERROR),
                    'migrated_at' => $now,
                    'created_at' => $now,
                ]);
                $inserted++;
            }
        }, 5);

        return compact('inserted', 'skipped', 'errors', 'warnings');
    }

    /**
     * @param  list<NormalizedInstallment>  $rows
     * @return array{inserted: int, skipped: int, errors: list<string>, warnings: list<string>}
     */
    public function loadInstallments(int $batchRowId, string $sourceTable, array $rows): array
    {
        if ($rows === []) {
            return ['inserted' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => []];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $warnings = [];
        $loanMap = $this->loanRowMap($connName, $tenantId);

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId, $batchRowId, $sourceTable, $rows, $now, $loanMap,
            &$inserted, &$skipped, &$errors, &$warnings,
        ): void {
            foreach ($rows as $inst) {
                $exists = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $sourceTable)
                    ->where('source_id', (string) $inst->legacyId)
                    ->where('source_secondary_key', 'installment')
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }

                $loanRowId = $loanMap[$inst->groupLoanLegacyId] ?? null;
                if ($loanRowId === null) {
                    $warnings[] = "rencana id={$inst->legacyId}: loan {$inst->groupLoanLegacyId} missing";

                    continue;
                }

                // dual component rows (principal + interest) per Next schema
                foreach (['principal', 'interest'] as $component) {
                    $id = $this->sequences->next('loan_installments');
                    $principalDue = $component === 'principal' ? $inst->principalDue : '0.00';
                    $interestDue = $component === 'interest' ? $inst->interestDue : '0.00';

                    $rowId = $db->table('loan_installments')->insertGetId([
                        'tenant_id' => $tenantId,
                        'id' => $id,
                        'loan_row_id' => $loanRowId,
                        'component' => $component,
                        'installment_number' => $inst->installmentNumber,
                        'due_date' => $inst->dueDate,
                        'principal_due' => $principalDue,
                        'interest_due' => $interestDue,
                        'principal_paid' => '0.00',
                        'interest_paid' => '0.00',
                        'penalty_due' => '0.00',
                        'penalty_paid' => '0.00',
                        'status' => 'pending',
                        'paid_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'row_id');

                    // only map once on principal component
                    if ($component === 'principal') {
                        $db->table('legacy_record_mappings')->insert([
                            'tenant_id' => $tenantId,
                            'batch_row_id' => $batchRowId,
                            'source_table' => $sourceTable,
                            'source_id' => (string) $inst->legacyId,
                            'source_secondary_key' => 'installment',
                            'target_table' => 'loan_installments',
                            'target_row_id' => $rowId,
                            'target_local_id' => $id,
                            'source_snapshot' => json_encode($inst->snapshot, JSON_THROW_ON_ERROR),
                            'migrated_at' => $now,
                            'created_at' => $now,
                        ]);
                    }
                }
                $inserted++;
            }
        }, 5);

        return compact('inserted', 'skipped', 'errors', 'warnings');
    }

    /**
     * @param  list<NormalizedPayment>  $rows
     * @return array{inserted: int, skipped: int, errors: list<string>, warnings: list<string>}
     */
    public function loadPayments(int $batchRowId, string $sourceTable, array $rows): array
    {
        if ($rows === []) {
            return ['inserted' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => []];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $warnings = [];
        $loanMap = $this->loanRowMap($connName, $tenantId);

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId, $batchRowId, $sourceTable, $rows, $now, $loanMap,
            &$inserted, &$skipped, &$errors, &$warnings,
        ): void {
            foreach ($rows as $pay) {
                $exists = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $sourceTable)
                    ->where('source_id', (string) $pay->legacyId)
                    ->where('source_secondary_key', 'payment')
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }

                $loanRowId = $loanMap[$pay->groupLoanLegacyId] ?? null;
                if ($loanRowId === null) {
                    $warnings[] = "real id={$pay->legacyId}: loan {$pay->groupLoanLegacyId} missing";

                    continue;
                }

                $payNumber = 'RA-'.$pay->legacyId;
                $payId = $pay->legacyId;
                // preserve legacy id when possible
                $idTaken = $db->table('loan_payments')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $payId)
                    ->exists();
                if ($idTaken) {
                    $payId = $this->sequences->next('loan_payments');
                }

                $payRowId = $db->table('loan_payments')->insertGetId([
                    'tenant_id' => $tenantId,
                    'id' => $payId,
                    'public_id' => (string) Str::ulid(),
                    'loan_row_id' => $loanRowId,
                    'payment_number' => $payNumber,
                    'paid_at' => $pay->paidAt,
                    'amount' => $pay->amount,
                    'payment_method' => 'legacy',
                    'reference_number' => (string) $pay->legacyId,
                    'journal_entry_row_id' => null,
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'row_id');

                foreach ([
                    ['principal', $pay->principal],
                    ['interest', $pay->interest],
                ] as [$component, $amt]) {
                    // Keep negative allocations (legacy reversals); skip only exact zero.
                    if (bccomp($amt, '0.00', 2) === 0) {
                        continue;
                    }
                    $allocId = $this->sequences->next('loan_payment_allocations');
                    $db->table('loan_payment_allocations')->insert([
                        'tenant_id' => $tenantId,
                        'id' => $allocId,
                        'payment_row_id' => $payRowId,
                        'installment_row_id' => null,
                        'component' => $component,
                        'amount' => $amt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $db->table('legacy_record_mappings')->insert([
                    'tenant_id' => $tenantId,
                    'batch_row_id' => $batchRowId,
                    'source_table' => $sourceTable,
                    'source_id' => (string) $pay->legacyId,
                    'source_secondary_key' => 'payment',
                    'target_table' => 'loan_payments',
                    'target_row_id' => $payRowId,
                    'target_local_id' => $payId,
                    'source_snapshot' => json_encode($pay->snapshot, JSON_THROW_ON_ERROR),
                    'migrated_at' => $now,
                    'created_at' => $now,
                ]);
                $inserted++;
            }
        }, 5);

        return compact('inserted', 'skipped', 'errors', 'warnings');
    }

    /**
     * FIFO-apply payment allocations onto installment principal_paid / interest_paid.
     * Call after all payments for the tenant (or suffix) are loaded.
     */
    public function applyPaymentProgressToInstallments(): int
    {
        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $updatedLoans = 0;

        $loanRowIds = DB::connection($connName)->table('loan_payments')
            ->where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('loan_row_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        foreach (array_chunk($loanRowIds, 50) as $chunk) {
            DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
                $tenantId, $chunk, $now, &$updatedLoans,
            ): void {
                foreach ($chunk as $loanRowId) {
                    // Reset paid fields for this loan's installments.
                    $db->table('loan_installments')
                        ->where('tenant_id', $tenantId)
                        ->where('loan_row_id', $loanRowId)
                        ->update([
                            'principal_paid' => '0.00',
                            'interest_paid' => '0.00',
                            'status' => 'pending',
                            'paid_at' => null,
                            'updated_at' => $now,
                        ]);

                    $installments = $db->table('loan_installments')
                        ->where('tenant_id', $tenantId)
                        ->where('loan_row_id', $loanRowId)
                        ->orderBy('installment_number')
                        ->orderBy('component')
                        ->get(['row_id', 'component', 'installment_number', 'principal_due', 'interest_due']);

                    /** @var list<array{row_id: int, remaining: string}> $principalQueue */
                    $principalQueue = [];
                    /** @var list<array{row_id: int, remaining: string}> $interestQueue */
                    $interestQueue = [];
                    foreach ($installments as $inst) {
                        if ($inst->component === 'principal') {
                            $principalQueue[] = [
                                'row_id' => (int) $inst->row_id,
                                'remaining' => bcadd((string) $inst->principal_due, '0', 2),
                                'paid' => '0.00',
                            ];
                        } elseif ($inst->component === 'interest') {
                            $interestQueue[] = [
                                'row_id' => (int) $inst->row_id,
                                'remaining' => bcadd((string) $inst->interest_due, '0', 2),
                                'paid' => '0.00',
                            ];
                        }
                    }

                    $payments = $db->table('loan_payments as p')
                        ->join('loan_payment_allocations as a', function ($j): void {
                            $j->on('a.tenant_id', '=', 'p.tenant_id')
                                ->on('a.payment_row_id', '=', 'p.row_id');
                        })
                        ->where('p.tenant_id', $tenantId)
                        ->where('p.loan_row_id', $loanRowId)
                        ->orderBy('p.paid_at')
                        ->orderBy('p.id')
                        ->get(['p.paid_at', 'a.component', 'a.amount', 'a.row_id as alloc_row_id']);

                    $pIdx = 0;
                    $iIdx = 0;
                    foreach ($payments as $pay) {
                        $amt = bcadd((string) $pay->amount, '0', 2);
                        if (bccomp($amt, '0.00', 2) <= 0) {
                            continue;
                        }
                        if ($pay->component === 'principal') {
                            while (bccomp($amt, '0.00', 2) > 0 && $pIdx < count($principalQueue)) {
                                $slot = &$principalQueue[$pIdx];
                                if (bccomp($slot['remaining'], '0.00', 2) <= 0) {
                                    $pIdx++;
                                    unset($slot);

                                    continue;
                                }
                                $take = bccomp($amt, $slot['remaining'], 2) <= 0 ? $amt : $slot['remaining'];
                                $slot['paid'] = bcadd($slot['paid'], $take, 2);
                                $slot['remaining'] = bcsub($slot['remaining'], $take, 2);
                                $amt = bcsub($amt, $take, 2);
                                if (bccomp($slot['remaining'], '0.00', 2) <= 0) {
                                    $pIdx++;
                                }
                                unset($slot);
                            }
                        } elseif ($pay->component === 'interest') {
                            while (bccomp($amt, '0.00', 2) > 0 && $iIdx < count($interestQueue)) {
                                $slot = &$interestQueue[$iIdx];
                                if (bccomp($slot['remaining'], '0.00', 2) <= 0) {
                                    $iIdx++;
                                    unset($slot);

                                    continue;
                                }
                                $take = bccomp($amt, $slot['remaining'], 2) <= 0 ? $amt : $slot['remaining'];
                                $slot['paid'] = bcadd($slot['paid'], $take, 2);
                                $slot['remaining'] = bcsub($slot['remaining'], $take, 2);
                                $amt = bcsub($amt, $take, 2);
                                if (bccomp($slot['remaining'], '0.00', 2) <= 0) {
                                    $iIdx++;
                                }
                                unset($slot);
                            }
                        }
                    }

                    foreach (array_merge($principalQueue, $interestQueue) as $slot) {
                        $row = $db->table('loan_installments')
                            ->where('tenant_id', $tenantId)
                            ->where('row_id', $slot['row_id'])
                            ->first(['component', 'principal_due', 'interest_due']);
                        if ($row === null) {
                            continue;
                        }
                        $due = $row->component === 'principal'
                            ? bcadd((string) $row->principal_due, '0', 2)
                            : bcadd((string) $row->interest_due, '0', 2);
                        $paid = $slot['paid'];
                        $status = 'pending';
                        if (bccomp($due, '0.00', 2) > 0 && bccomp($paid, $due, 2) >= 0) {
                            $status = 'paid';
                        } elseif (bccomp($paid, '0.00', 2) > 0) {
                            $status = 'partial';
                        }
                        $db->table('loan_installments')
                            ->where('tenant_id', $tenantId)
                            ->where('row_id', $slot['row_id'])
                            ->update([
                                'principal_paid' => $row->component === 'principal' ? $paid : '0.00',
                                'interest_paid' => $row->component === 'interest' ? $paid : '0.00',
                                'status' => $status,
                                'updated_at' => $now,
                            ]);
                    }

                    $updatedLoans++;
                }
            }, 5);
        }

        return $updatedLoans;
    }

    /**
     * @return array<int, int> legacy loan id => row_id
     */
    private function loanRowMap(string $connName, int $tenantId): array
    {
        return DB::connection($connName)->table('loans')
            ->where('tenant_id', $tenantId)
            ->where('legacy_source', 'group_loan')
            ->pluck('row_id', 'id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>  $keys
     */
    private function snapshotAmount(array $snapshot, array $keys, string $fallback): ?string
    {
        foreach ($keys as $key) {
            $value = $snapshot[$key] ?? null;
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            if (is_numeric($value)) {
                return number_format((float) $value, 2, '.', '');
            }

            $normalized = str_replace([',', "\xc2\xa0", ' '], '.', (string) $value);
            if (is_numeric($normalized)) {
                return number_format((float) $normalized, 2, '.', '');
            }
        }

        return $fallback;
    }
}
