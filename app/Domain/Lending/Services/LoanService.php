<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBeneficiaryWriteOff;
use App\Domain\Lending\Models\LoanCommittee;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Lending\Models\LoanWriteOff;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Services\TenantSettingService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LoanService
{
    /** Default receivable accounts per loan product code when tenant setting is absent. */
    private const DEFAULT_RECEIVABLE_CODES = [
        'spp' => '1.1.03.01',
        'uep' => '1.1.03.02',
        'pl' => '1.1.03.03',
    ];

    /** Default allowance (cadangan kerugian) accounts per loan product code. */
    private const DEFAULT_ALLOWANCE_CODES = [
        'spp' => '1.1.04.01',
        'uep' => '1.1.04.02',
        'pl' => '1.1.04.03',
    ];

    /** Default revenue (jasa) accounts per loan product code. */
    private const DEFAULT_REVENUE_CODES = [
        'spp' => '4.1.01.01',
        'uep' => '4.1.01.02',
        'pl' => '4.1.01.03',
    ];

    /** Default penalty accounts per loan product code. */
    private const DEFAULT_PENALTY_CODES = [
        'spp' => '4.1.01.04',
        'uep' => '4.1.01.05',
        'pl' => '4.1.01.06',
    ];

    private const FREQUENCY_PER_MONTH = [
        'weekly' => 4.3333,
        'biweekly' => 2.1667,
        'monthly' => 1.0,
        'bimonthly' => 0.5,
        'quarterly' => 0.3333,
        'every_4_months' => 0.25,
        'every_5_months' => 0.2,
        'every_6_months' => 1 / 6,
        'every_7_months' => 1 / 7,
        'every_8_months' => 0.125,
        'every_9_months' => 1 / 9,
        'every_10_months' => 0.1,
        'every_11_months' => 1 / 11,
        'every_12_months' => 1 / 12,
        'every_24_months' => 1 / 24,
        'every_36_months' => 1 / 36,
        'at_maturity' => null,
    ];

    private const FREQUENCY_STEP = [
        'weekly' => ['weeks' => 1],
        'biweekly' => ['weeks' => 2],
        'monthly' => ['months' => 1],
        'bimonthly' => ['months' => 2],
        'quarterly' => ['months' => 3],
        'every_4_months' => ['months' => 4],
        'every_5_months' => ['months' => 5],
        'every_6_months' => ['months' => 6],
        'every_7_months' => ['months' => 7],
        'every_8_months' => ['months' => 8],
        'every_9_months' => ['months' => 9],
        'every_10_months' => ['months' => 10],
        'every_11_months' => ['months' => 11],
        'every_12_months' => ['months' => 12],
        'every_24_months' => ['months' => 24],
        'every_36_months' => ['months' => 36],
    ];

    public function __construct(
        private readonly TenantSettingService $settings,
        private readonly JournalPostingService $journalPosting,
        private readonly TenantContext $tenantContext,
        private readonly LoanTrackingService $tracking,
    ) {}

    public function createProposal(array $data, int $userId): Loan
    {
        return DB::connection('tenant')->transaction(function () use ($data, $userId): Loan {
            $product = LoanProduct::query()
                ->where('row_id', $data['loan_product_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $group = Group::query()->where('row_id', $data['group_id'])->firstOrFail();

            $principal = (float) $data['principal_amount'];
            $serviceRateTotal = (float) $data['service_rate_total'];
            $term = (int) $data['term_months'];
            $method = $data['installment_method'];
            $principalFreq = $data['principal_frequency'];
            $interestFreq = $data['interest_frequency'];
            $principalGraceMonths = (int) ($data['principal_grace_months'] ?? 0);
            $interestGraceMonths = (int) ($data['interest_grace_months'] ?? 0);

            $principalPeriods = $this->periods($principalFreq, $term, $principalGraceMonths);
            $interestPeriods = $this->periods($interestFreq, $term, $interestGraceMonths);
            $principalRatePerPeriod = $principalPeriods > 0 ? round($serviceRateTotal / $principalPeriods, 4) : 0.0;
            $interestRatePerPeriod = $interestPeriods > 0 ? round($serviceRateTotal / $interestPeriods, 4) : 0.0;

            $loan = Loan::query()->create([
                'legacy_source' => 'group_loan',
                'loan_product_row_id' => $product->row_id,
                'sequence_number' => 1,
                'proposed_at' => $data['proposed_at'],
                'principal_amount' => $principal,
                'interest_rate' => $principalRatePerPeriod,
                'service_rate_total' => $serviceRateTotal,
                'term_months' => $term,
                'installment_method' => $method,
                'principal_frequency' => $principalFreq,
                'interest_frequency' => $interestFreq,
                'principal_grace_months' => $principalGraceMonths,
                'interest_grace_months' => $interestGraceMonths,
                'rounding_step' => isset($data['rounding_step']) && $data['rounding_step'] !== '' ? (int) $data['rounding_step'] : null,
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            $loan->borrower()->create([
                'member_row_id' => null,
                'group_row_id' => $group->row_id,
            ]);

            foreach (['chair' => $data['chair_id'], 'secretary' => $data['secretary_id'], 'treasurer' => $data['treasurer_id']] as $position => $memberRowId) {
                $member = Member::query()->with('person')->where('row_id', $memberRowId)->first();
                LoanCommittee::query()->create([
                    'loan_row_id' => $loan->row_id,
                    'position' => $position,
                    'member_row_id' => $memberRowId,
                    'member_name_snapshot' => $member?->person?->full_name,
                    'snapshot_at' => $data['proposed_at'],
                ]);
            }

            $beneficiaryIds = collect($data['beneficiary_ids'])->unique()->values();
            $amounts = $data['beneficiary_amounts'] ?? [];
            $perBeneficiary = round($principal / max(1, $beneficiaryIds->count()), 2);
            foreach ($beneficiaryIds as $memberRowId) {
                $id = (int) $memberRowId;
                $hasAmount = array_key_exists($id, $amounts) || array_key_exists((string) $id, $amounts);
                $proposed = $hasAmount ? (float) ($amounts[$id] ?? $amounts[(string) $id]) : $perBeneficiary;
                LoanBeneficiary::query()->create([
                    'loan_row_id' => $loan->row_id,
                    'member_row_id' => $id,
                    'proposed_amount' => $proposed,
                    'allocated_amount' => $proposed,
                ]);
            }

            $this->generatePrincipalSchedule($loan, $principal, $principalPeriods, $principalRatePerPeriod, $principalFreq, $data['proposed_at'], $principalGraceMonths);
            $this->generateInterestSchedule($loan, $principal, $interestPeriods, $interestRatePerPeriod, $method, $interestFreq, $data['proposed_at'], $interestGraceMonths);

            $loan->statusHistories()->create([
                'from_status' => null,
                'to_status' => 'draft',
                'principal_amount' => $principal,
                'product_row_id' => $product->row_id,
                'term_months' => $term,
                'service_rate_total' => $serviceRateTotal,
                'principal_frequency' => $principalFreq,
                'interest_frequency' => $interestFreq,
                'principal_grace_months' => $principalGraceMonths,
                'interest_grace_months' => $interestGraceMonths,
                'notes' => 'Proposal didaftarkan.',
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            return $loan->fresh(['product', 'borrower.group', 'committee', 'beneficiaries', 'installments']);
        });
    }

    public function recordStatusTransition(Loan $loan, ?string $from, string $to, int $userId, ?string $notes = null): void
    {
        $loan->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'principal_amount' => (float) $loan->principal_amount,
            'product_row_id' => $loan->loan_product_row_id,
            'term_months' => (int) $loan->term_months,
            'notes' => $notes,
            'changed_by_user_id' => $userId,
            'changed_at' => now(),
        ]);
    }

    public function updateProposal(Loan $loan, array $data, int $userId): Loan
    {
        return DB::connection('tenant')->transaction(function () use ($loan, $data): Loan {
            $loan->update([
                'proposed_at' => $data['proposed_at'],
                'principal_amount' => (float) $data['principal_amount'],
                'service_rate_total' => (float) $data['service_rate_total'],
                'term_months' => (int) $data['term_months'],
                'installment_method' => $data['installment_method'],
                'principal_frequency' => $data['principal_frequency'],
                'interest_frequency' => $data['interest_frequency'],
                'rounding_step' => array_key_exists('rounding_step', $data) && $data['rounding_step'] !== '' ? (int) $data['rounding_step'] : $loan->rounding_step,
            ]);

            $previousBeneficiaries = $loan->beneficiaries()->pluck('member_row_id', 'allocated_amount')->all();
            $incoming = $data['beneficiary_amounts'] ?? [];

            $keep = [];
            foreach ($incoming as $memberRowId => $amount) {
                $id = (int) $memberRowId;
                $amount = (float) $amount;
                $loan->beneficiaries()->updateOrCreate(
                    ['member_row_id' => $id],
                    ['proposed_amount' => $amount, 'allocated_amount' => $amount],
                );
                $keep[] = $id;
            }

            $loan->beneficiaries()->whereNotIn('member_row_id', $keep)->delete();

            $this->regenerateInstallmentSchedule($loan, (float) $data['principal_amount']);

            $loan->refresh();
            $loan->load(['product', 'borrower.group', 'committee', 'beneficiaries', 'installments']);

            return $loan;
        });
    }

    public function deleteProposal(Loan $loan): void
    {
        if ($loan->status !== 'draft') {
            throw new RuntimeException('Hanya pinjaman dengan status draft / proposal yang dapat dihapus.');
        }

        if ($loan->journal_entry_row_id !== null || $loan->disbursement_journal_row_id !== null) {
            throw new RuntimeException('Pinjaman yang sudah memiliki pencatatan jurnal tidak dapat dihapus.');
        }

        DB::connection('tenant')->transaction(function () use ($loan): void {
            $loan->beneficiaries()->delete();
            $loan->borrower()->delete();
            $loan->committee()->delete();
            $loan->installments()->delete();
            $loan->statusHistories()->delete();
            $loan->delete();
        });
    }

    public function removeBeneficiary(Loan $loan, int $memberRowId, int $userId): Loan
    {
        if (! in_array($loan->status, ['draft', 'verified'], true)) {
            throw new RuntimeException('Pemanfaat hanya dapat dihapus saat status draft atau verified.');
        }

        return DB::connection('tenant')->transaction(function () use ($loan, $memberRowId): Loan {
            $beneficiary = $loan->beneficiaries()->where('member_row_id', $memberRowId)->first();
            if ($beneficiary === null) {
                throw new RuntimeException('Pemanfaat tidak ditemukan pada pinjaman ini.');
            }

            $beneficiary->delete();

            $loan->load(['product', 'borrower.group', 'committee', 'beneficiaries', 'installments']);

            return $loan;
        });
    }

    /**
     * One-shot committee fill for loans missing officers (e.g. legacy import).
     * After save, committee cannot be changed.
     *
     * @param  array{chair_id: int, secretary_id: int, treasurer_id: int}  $data
     */
    public function setCommittee(Loan $loan, array $data, int $userId): Loan
    {
        return DB::connection('tenant')->transaction(function () use ($loan, $data, $userId): Loan {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->row_id);
            $loan->load('committee');

            if ($loan->committee->isNotEmpty()) {
                throw new RuntimeException('Pengurus pinjaman sudah diisi dan tidak dapat diganti.');
            }

            $positions = [
                'chair' => (int) $data['chair_id'],
                'secretary' => (int) $data['secretary_id'],
                'treasurer' => (int) $data['treasurer_id'],
            ];
            if (count(array_unique(array_values($positions))) < 3) {
                throw new RuntimeException('Ketua, sekretaris, dan bendahara harus orang yang berbeda.');
            }

            $today = now()->toDateString();
            foreach ($positions as $position => $memberRowId) {
                $member = Member::query()->with('person')->where('row_id', $memberRowId)->first();
                if ($member === null) {
                    throw new RuntimeException("Anggota untuk posisi {$position} tidak ditemukan.");
                }
                LoanCommittee::query()->create([
                    'loan_row_id' => $loan->row_id,
                    'position' => $position,
                    'member_row_id' => $memberRowId,
                    'member_name_snapshot' => $member->person?->full_name,
                    'snapshot_at' => $loan->proposed_at?->toDateString() ?? $today,
                ]);
            }

            unset($userId); // reserved for audit later

            return $loan->fresh(['product', 'borrower.group', 'committee', 'beneficiaries', 'installments', 'payments.allocations']);
        });
    }

    public function verify(Loan $loan, array $data, int $userId): Loan
    {
        return DB::connection('tenant')->transaction(function () use ($loan, $data, $userId): Loan {
            $fromStatus = $loan->status;
            $verificationAmount = array_key_exists('verification_amount', $data) && $data['verification_amount'] !== null
                ? (float) $data['verification_amount']
                : (float) $loan->principal_amount;

            $verifiedAmounts = $data['verified_amounts'] ?? null;
            if (is_array($verifiedAmounts) && $verifiedAmounts !== []) {
                foreach ($verifiedAmounts as $memberRowId => $amount) {
                    $loan->beneficiaries()->where('member_row_id', (int) $memberRowId)->update([
                        'verified_amount' => (float) $amount,
                    ]);
                }
            } else {
                foreach ($loan->beneficiaries()->get() as $beneficiary) {
                    $beneficiary->update(['verified_amount' => $beneficiary->proposed_amount ?? $beneficiary->allocated_amount]);
                }
            }

            $termMonths = $this->optionalInteger($data, 'term_months', (int) $loan->term_months);
            $serviceRateTotal = $this->optionalFloat($data, 'service_rate_total', (float) $loan->service_rate_total);
            $principalFrequency = $data['principal_frequency'] ?? (string) $loan->principal_frequency;
            $interestFrequency = $data['interest_frequency'] ?? (string) $loan->interest_frequency;
            $principalGraceMonths = $this->optionalInteger($data, 'principal_grace_months', (int) $loan->principal_grace_months);
            $interestGraceMonths = $this->optionalInteger($data, 'interest_grace_months', (int) $loan->interest_grace_months);

            $loan->update([
                'verified_at' => $data['verified_at'],
                'verification_notes' => $data['verification_notes'],
                'status' => 'verified',
            ]);

            $loan->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => 'verified',
                'principal_amount' => $verificationAmount,
                'product_row_id' => $loan->loan_product_row_id,
                'term_months' => $termMonths,
                'service_rate_total' => $serviceRateTotal,
                'principal_frequency' => $principalFrequency,
                'interest_frequency' => $interestFrequency,
                'principal_grace_months' => $principalGraceMonths,
                'interest_grace_months' => $interestGraceMonths,
                'notes' => $data['verification_notes'],
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            return $loan->fresh(['product', 'borrower.group', 'committee', 'beneficiaries', 'installments']);
        });
    }

    public function approve(Loan $loan, array $data, int $userId): Loan
    {
        return DB::connection('tenant')->transaction(function () use ($loan, $data, $userId): Loan {
            $fromStatus = $loan->status;

            $loan->loadMissing('beneficiaries');
            $byMember = $loan->beneficiaries->keyBy('member_row_id');
            foreach ($data['beneficiaries'] as $row) {
                $memberId = (int) $row['member_row_id'];
                $beneficiary = $byMember->get($memberId);
                if ($beneficiary !== null) {
                    $beneficiary->update(['allocated_amount' => (float) $row['allocated_amount']]);
                }
            }

            $totalAllocated = collect($data['beneficiaries'])->sum(fn ($row) => (float) $row['allocated_amount']);
            $allocatedPrincipal = (float) ($data['allocated_principal'] ?? $totalAllocated);
            $verification = $loan->statusHistories()
                ->where('to_status', 'verified')
                ->orderByDesc('changed_at')
                ->first();
            $termMonths = $this->optionalInteger($data, 'term_months', (int) ($verification?->term_months ?? $loan->term_months));
            $serviceRateTotal = $this->optionalFloat($data, 'service_rate_total', (float) ($verification?->service_rate_total ?? $loan->service_rate_total));
            $principalFrequency = $data['principal_frequency'] ?? (string) ($verification?->principal_frequency ?? $loan->principal_frequency);
            $interestFrequency = $data['interest_frequency'] ?? (string) ($verification?->interest_frequency ?? $loan->interest_frequency);
            $principalGraceMonths = $this->optionalInteger($data, 'principal_grace_months', (int) ($verification?->principal_grace_months ?? $loan->principal_grace_months));
            $interestGraceMonths = $this->optionalInteger($data, 'interest_grace_months', (int) ($verification?->interest_grace_months ?? $loan->interest_grace_months));

            $loan->update([
                'approved_at' => $data['approved_at'],
                'funded_at' => $data['planned_disbursed_at'],
                'principal_amount' => $totalAllocated,
                'term_months' => $termMonths,
                'service_rate_total' => $serviceRateTotal,
                'principal_frequency' => $principalFrequency,
                'interest_frequency' => $interestFrequency,
                'principal_grace_months' => $principalGraceMonths,
                'interest_grace_months' => $interestGraceMonths,
                'status' => 'waiting',
            ]);

            $this->regenerateInstallmentSchedule($loan, $totalAllocated);

            $defaultNotes = sprintf('Alokasi ditetapkan untuk %d anggota dengan total %s. Rencana pencairan: %s.', count($data['beneficiaries']), number_format($totalAllocated, 2, ',', '.'), $data['planned_disbursed_at']);

            $loan->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => 'waiting',
                'principal_amount' => $totalAllocated,
                'product_row_id' => $loan->loan_product_row_id,
                'term_months' => $termMonths,
                'service_rate_total' => $serviceRateTotal,
                'principal_frequency' => $principalFrequency,
                'interest_frequency' => $interestFrequency,
                'principal_grace_months' => $principalGraceMonths,
                'interest_grace_months' => $interestGraceMonths,
                'notes' => $data['allocation_notes'] ?? $defaultNotes,
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            return $loan->fresh();
        });
    }

    public function disburse(Loan $loan, array $data, int $userId): Loan
    {
        return DB::connection('tenant')->transaction(function () use ($loan, $data, $userId): Loan {
            $fromStatus = $loan->status;
            $totalAllocated = (float) $loan->beneficiaries()->sum('allocated_amount');

            $loan->update([
                'disbursed_at' => $data['disbursed_at'],
                'disbursement_account_row_id' => (int) $data['disbursement_account_row_id'],
                'disbursement_notes' => $data['disbursement_notes'] ?? null,
                'status' => 'active',
            ]);

            $loan->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => 'active',
                'principal_amount' => $totalAllocated,
                'product_row_id' => $loan->loan_product_row_id,
                'term_months' => (int) $loan->term_months,
                'service_rate_total' => (float) $loan->service_rate_total,
                'principal_frequency' => $loan->principal_frequency,
                'interest_frequency' => $loan->interest_frequency,
                'principal_grace_months' => (int) $loan->principal_grace_months,
                'interest_grace_months' => (int) $loan->interest_grace_months,
                'notes' => $data['disbursement_notes'] ?? null,
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            if ($totalAllocated > 0) {
                $cashAccount = Account::on('tenant')
                    ->where('row_id', (int) $data['disbursement_account_row_id'])
                    ->firstOrFail();
                $productCode = (string) ($loan->product?->code ?? '');
                $receivableAccount = $this->resolveReceivableAccount($productCode);
                $disbursedAt = CarbonImmutable::parse((string) $data['disbursed_at'])->toDateString();

                $this->ensureFiscalPeriod($disbursedAt);
                $this->createDisbursementJournal(
                    loan: $loan,
                    cashAccount: $cashAccount,
                    receivableAccount: $receivableAccount,
                    totalAmount: $totalAllocated,
                    disbursedAt: $disbursedAt,
                    userId: $userId,
                );
            }

            return $loan->fresh();
        });
    }

    private function resolveReceivableAccount(string $productCode): Account
    {
        return $this->resolveAccountByKey(
            productCode: $productCode,
            settingKey: 'account.pencairan_',
            defaultCodes: self::DEFAULT_RECEIVABLE_CODES,
            label: 'receivable',
        );
    }

    private function optionalInteger(array $data, string $key, int $fallback): int
    {
        return array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== ''
            ? (int) $data[$key]
            : $fallback;
    }

    private function optionalFloat(array $data, string $key, float $fallback): float
    {
        return array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== ''
            ? (float) $data[$key]
            : $fallback;
    }

    private function resolveRevenueAccount(string $productCode): Account
    {
        return $this->resolveAccountByKey(
            productCode: $productCode,
            settingKey: 'account.penerimaan_jasa_',
            defaultCodes: self::DEFAULT_REVENUE_CODES,
            label: 'revenue',
        );
    }

    private function resolvePenaltyAccount(string $productCode): Account
    {
        return $this->resolveAccountByKey(
            productCode: $productCode,
            settingKey: 'account.penerimaan_denda_',
            defaultCodes: self::DEFAULT_PENALTY_CODES,
            label: 'penalty',
        );
    }

    /**
     * @param  array<string, string>  $defaultCodes
     */
    private function resolveAccountByKey(string $productCode, string $settingKey, array $defaultCodes, string $label): Account
    {
        $normalized = strtolower(trim($productCode));
        $key = $settingKey.$normalized;
        $code = $this->settings->get($key);

        if (! is_string($code) || $code === '') {
            $code = $defaultCodes[$normalized] ?? null;
        }

        if (! is_string($code) || $code === '') {
            throw new RuntimeException("No {$label} account configured for loan product [{$productCode}].");
        }

        return Account::on('tenant')
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOr(function () use ($code, $label): never {
                throw new RuntimeException(ucfirst($label)." account [{$code}] not found or inactive.");
            });
    }

    private function ensureFiscalPeriod(string $date): FiscalPeriod
    {
        $carbon = CarbonImmutable::parse($date);
        $year = (int) $carbon->year;
        $month = (int) $carbon->month;
        $startsAt = $carbon->startOfMonth()->toDateString();
        $endsAt = $carbon->endOfMonth()->toDateString();

        $existing = FiscalPeriod::query()
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return FiscalPeriod::query()->create([
            'fiscal_year' => $year,
            'fiscal_month' => $month,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'open',
        ]);
    }

    private function createDisbursementJournal(
        Loan $loan,
        Account $cashAccount,
        Account $receivableAccount,
        float $totalAmount,
        string $disbursedAt,
        int $userId,
    ): JournalEntry {
        $entry = JournalEntry::query()->create([
            'journal_number' => null,
            'transaction_date' => $disbursedAt,
            'sequence_number' => 0,
            'source_type' => 'loan',
            'source_row_id' => (int) $loan->row_id,
            'description' => sprintf('Pencairan pinjaman #%s', $loan->loan_number ?? (string) $loan->row_id),
            'status' => 'draft',
            'created_by_user_id' => $userId,
        ]);

        $entry->lines()->create([
            'line_number' => 1,
            'account_row_id' => (int) $cashAccount->row_id,
            'organization_unit_row_id' => null,
            'description' => 'Kas/Bank sumber dana',
            'debit' => $totalAmount,
            'credit' => 0,
        ]);

        $entry->lines()->create([
            'line_number' => 2,
            'account_row_id' => (int) $receivableAccount->row_id,
            'organization_unit_row_id' => null,
            'description' => 'Piutang pinjaman',
            'debit' => 0,
            'credit' => $totalAmount,
        ]);

        return $this->journalPosting->post($entry, $userId);
    }

    public function recordInstallmentPayment(array $data, int $userId): JournalEntry
    {
        $loan = Loan::query()->with('product')->where('row_id', $data['loan_id'])->firstOrFail();

        $cashAccount = Account::on('tenant')
            ->where('row_id', $data['cash_account_row_id'])
            ->where('is_active', true)
            ->where('is_postable', true)
            ->firstOrFail();

        $productCode = (string) ($loan->product?->code ?? '');
        $receivableAccount = $this->resolveReceivableAccount($productCode);
        $revenueAccount = $this->resolveRevenueAccount($productCode);

        $principalAmount = (float) $data['principal_amount'];
        $interestAmount = (float) $data['interest_amount'];
        $penaltyAmount = (float) ($data['penalty_amount'] ?? 0);
        $totalAmount = round($principalAmount + $interestAmount + $penaltyAmount, 2);

        if ($totalAmount <= 0) {
            throw new RuntimeException('Total nominal pokok, jasa, dan denda harus lebih dari 0.');
        }

        $transactionDate = CarbonImmutable::parse($data['transaction_date'])->toDateString();
        $this->ensureFiscalPeriod($transactionDate);

        return DB::connection('tenant')->transaction(function () use ($loan, $cashAccount, $receivableAccount, $revenueAccount, $principalAmount, $interestAmount, $penaltyAmount, $totalAmount, $transactionDate, $data, $userId): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $transactionDate,
                'sequence_number' => 0,
                'source_type' => 'loan_installment',
                'source_row_id' => (int) $loan->row_id,
                'transaction_type' => 'angsuran',
                'description' => $data['description'],
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            $entry->lines()->create([
                'line_number' => 1,
                'account_row_id' => (int) $cashAccount->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Kas/Bank sumber dana',
                'debit' => $totalAmount,
                'credit' => 0,
            ]);

            $entry->lines()->create([
                'line_number' => 2,
                'account_row_id' => (int) $receivableAccount->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Piutang pinjaman',
                'debit' => 0,
                'credit' => $principalAmount,
            ]);

            $entry->lines()->create([
                'line_number' => 3,
                'account_row_id' => (int) $revenueAccount->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Pendapatan jasa',
                'debit' => 0,
                'credit' => $interestAmount,
            ]);

            if ($penaltyAmount > 0) {
                $penaltyAccount = $this->resolvePenaltyAccount((string) $loan->product?->code);
                $entry->lines()->create([
                    'line_number' => 4,
                    'account_row_id' => (int) $penaltyAccount->row_id,
                    'organization_unit_row_id' => null,
                    'description' => 'Pendapatan denda',
                    'debit' => 0,
                    'credit' => $penaltyAmount,
                ]);
            }

            $posted = $this->journalPosting->post($entry, $userId);

            $installmentNumber = (int) ($data['installment_number'] ?? 0);
            $allocations = $data['member_allocations'] ?? [];
            if ($installmentNumber > 0 && is_array($allocations) && $allocations !== []) {
                $this->tracking->recordMemberAllocations(
                    (int) $loan->row_id,
                    $installmentNumber,
                    (int) $posted->row_id,
                    $allocations,
                    CarbonImmutable::parse($transactionDate),
                );
            }

            return $posted;
        });
    }

    public function revertToDraft(Loan $loan, int $userId): Loan
    {
        if (! in_array($loan->status, ['verified', 'waiting', 'approved'], true)) {
            throw new RuntimeException('Pinjaman hanya dapat dikembalikan ke draft dari status verified, waiting, atau approved.');
        }

        return DB::connection('tenant')->transaction(function () use ($loan, $userId): Loan {
            $fromStatus = $loan->status;

            $loan->update(['status' => 'draft']);

            $loan->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => 'draft',
                'notes' => 'Dikembalikan ke status proposal.',
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Write off remaining principal (penghapusan piutang kelompok).
     * Mirrors legacy /perguliran/hapus: status → written_off + journal allowance vs receivable.
     */
    public function writeOff(Loan $loan, array $data, int $userId): Loan
    {
        if (! in_array($loan->status, ['active', 'disbursed'], true)) {
            throw new RuntimeException('Penghapusan hanya untuk pinjaman aktif.');
        }

        return DB::connection('tenant')->transaction(function () use ($loan, $data, $userId): Loan {
            $loan->loadMissing(['product', 'installments', 'borrower.group']);

            $principalRemaining = $this->principalRemaining($loan);
            $interestRemaining = $this->interestRemaining($loan);

            if ($principalRemaining <= 0) {
                throw new RuntimeException('Sisa pokok harus lebih dari 0 untuk penghapusan.');
            }

            $writtenOffAt = CarbonImmutable::parse((string) $data['written_off_at']);
            $reason = isset($data['reason']) ? trim((string) $data['reason']) : '';
            $productCode = (string) ($loan->product?->code ?? '');

            $receivable = $this->resolveReceivableAccount($productCode);
            $allowance = $this->resolveAllowanceAccount($productCode);
            $this->ensureFiscalPeriod($writtenOffAt->toDateString());

            $entry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $writtenOffAt->toDateString(),
                'sequence_number' => 0,
                'source_type' => 'loan_write_off',
                'source_row_id' => (int) $loan->row_id,
                'description' => sprintf(
                    'Penghapusan piutang pinjaman #%s%s',
                    $loan->loan_number ?? (string) $loan->row_id,
                    $reason !== '' ? ' — '.$reason : '',
                ),
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            // Debit cadangan, credit piutang (legacy 1.1.04 → 1.1.03).
            $entry->lines()->create([
                'line_number' => 1,
                'account_row_id' => (int) $allowance->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Cadangan kerugian piutang',
                'debit' => $principalRemaining,
                'credit' => 0,
            ]);
            $entry->lines()->create([
                'line_number' => 2,
                'account_row_id' => (int) $receivable->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Piutang pinjaman dihapus',
                'debit' => 0,
                'credit' => $principalRemaining,
            ]);
            $posted = $this->journalPosting->post($entry, $userId);

            LoanWriteOff::query()->create([
                'loan_row_id' => $loan->row_id,
                'principal_balance' => $principalRemaining,
                'interest_balance' => $interestRemaining,
                'written_off_at' => $writtenOffAt,
                'reason' => $reason !== '' ? $reason : null,
                'journal_entry_row_id' => $posted->row_id,
                'approved_by_user_id' => $userId,
            ]);

            $fromStatus = $loan->status;
            $loan->update([
                'status' => 'written_off',
                'completed_at' => $writtenOffAt->toDateString(),
                'guidance_notes' => $reason !== '' ? $reason : $loan->guidance_notes,
            ]);

            $loan->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => 'written_off',
                'principal_amount' => $principalRemaining,
                'product_row_id' => $loan->loan_product_row_id,
                'term_months' => (int) $loan->term_months,
                'notes' => $reason !== '' ? $reason : 'Penghapusan piutang.',
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Write off a single beneficiary's remaining principal in an active loan.
     *
     * Use case: anggota meninggal/keluar mid-loan. Pinjaman kelompok tetap aktif,
     * hanya piutang anggota tersebut yang dihapusbukukan. Tercatat sebagai jurnal
     * (debit cadangan, kredit piutang) dan tambahan baris write_off pada rencana
     * angsuran di posisi N+1, dengan sisa angsuran setelahnya direduksi proporsional.
     */
    public function writeOffBeneficiary(Loan $loan, int $memberRowId, array $data, int $userId): Loan
    {
        if (! in_array($loan->status, ['active', 'disbursed'], true)) {
            throw new RuntimeException('Penghapusan pemanfaatan hanya untuk pinjaman aktif.');
        }

        return DB::connection('tenant')->transaction(function () use ($loan, $memberRowId, $data, $userId): Loan {
            $loan->loadMissing(['product', 'installments', 'beneficiaries']);

            $beneficiary = $loan->beneficiaries->firstWhere('member_row_id', $memberRowId);
            if ($beneficiary === null) {
                throw new RuntimeException('Pemanfaat tidak ditemukan pada pinjaman ini.');
            }
            if ($beneficiary->written_off_at !== null) {
                throw new RuntimeException('Pemanfaat sudah dihapusbukukan sebelumnya.');
            }

            // 1) Compute write-off amount from tracking.
            $principalPaid = (float) DB::connection('tenant')
                ->table('loan_installment_tracking')
                ->where('loan_row_id', $loan->row_id)
                ->where('member_row_id', $memberRowId)
                ->sum('principal_paid');

            $allocated = (float) $beneficiary->allocated_amount;
            $writeOffAmount = round($allocated - $principalPaid, 2);

            if ($writeOffAmount <= 0) {
                throw new RuntimeException('Tidak ada sisa pokok yang dapat dihapusbukukan (sudah lunas atau lebih).');
            }

            $N = (int) $data['installment_number'];
            $writtenOffAt = CarbonImmutable::parse((string) $data['written_off_at']);
            $reason = trim((string) ($data['reason'] ?? ''));

            // 2) Identify remaining principal periods after write-off insertion point.
            $remainingPrincipalRows = $loan->installments
                ->where('component', 'principal')
                ->where('installment_number', '>', $N)
                ->sortBy('installment_number')
                ->values();

            $R = $remainingPrincipalRows->count();

            if ($R === 0) {
                throw new RuntimeException('Tidak ada periode tersisa setelah angsuran ke-'.$N.' untuk menyerap pengurangan.');
            }

            // Validate N is within the actual schedule.
            $totalPrincipalPeriods = $loan->installments
                ->where('component', 'principal')
                ->count();
            if ($N < 1 || $N >= $totalPrincipalPeriods) {
                throw new RuntimeException('Angsuran ke-'.$N.' di luar jangkauan jadwal pinjaman (1-'.($totalPrincipalPeriods - 1).').');
            }

            $productCode = (string) ($loan->product?->code ?? '');
            $receivable = $this->resolveReceivableAccount($productCode);
            $allowance = $this->resolveAllowanceAccount($productCode);
            $this->ensureFiscalPeriod($writtenOffAt->toDateString());

            // 3) Journal entry: debit allowance, credit receivable.
            $entry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $writtenOffAt->toDateString(),
                'sequence_number' => 0,
                'source_type' => 'loan_beneficiary_write_off',
                'source_row_id' => (int) $loan->row_id,
                'transaction_type' => 'hapus_bukuan_pemanfaat',
                'description' => sprintf(
                    'Hapus bukukan pemanfaat #%d (%s) pada pinjaman #%s%s',
                    $memberRowId,
                    $beneficiary->member?->person?->full_name ?? '—',
                    $loan->loan_number ?? (string) $loan->row_id,
                    $reason !== '' ? ' — '.$reason : ''
                ),
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);
            $entry->lines()->create([
                'line_number' => 1,
                'account_row_id' => (int) $allowance->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Cadangan kerugian piutang (pemanfaat)',
                'debit' => $writeOffAmount,
                'credit' => 0,
            ]);
            $entry->lines()->create([
                'line_number' => 2,
                'account_row_id' => (int) $receivable->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Piutang pemanfaat dihapusbukukan',
                'debit' => 0,
                'credit' => $writeOffAmount,
            ]);
            $posted = $this->journalPosting->post($entry, $userId);

            // 4) UNIQUE-safe renumber via temp-offset pattern (installments > N).
            DB::connection('tenant')->statement(
                'UPDATE loan_installments SET installment_number = -(installment_number + 1) '
                ."WHERE loan_row_id = ? AND component IN ('principal', 'interest') AND installment_number > ?",
                [$loan->row_id, $N]
            );
            DB::connection('tenant')->statement(
                'UPDATE loan_installments SET installment_number = -installment_number '
                .'WHERE loan_row_id = ? AND installment_number < 0',
                [$loan->row_id]
            );

            // Same for tracking table.
            DB::connection('tenant')->statement(
                'UPDATE loan_installment_tracking SET installment_number = -(installment_number + 1) '
                .'WHERE loan_row_id = ? AND installment_number > ?',
                [$loan->row_id, $N]
            );
            DB::connection('tenant')->statement(
                'UPDATE loan_installment_tracking SET installment_number = -installment_number '
                .'WHERE loan_row_id = ? AND installment_number < 0',
                [$loan->row_id]
            );

            // 5) Proportional reduction on remaining principal rows (now at N+2..end+1).
            $perPeriod = round($writeOffAmount / $R, 2);
            $assigned = 0.0;
            $count = $R;
            foreach ($remainingPrincipalRows as $index => $row) {
                $newNumber = $row->installment_number + 1;
                if ($index === $count - 1) {
                    $reduction = round($writeOffAmount - $assigned, 2);
                } else {
                    $reduction = $perPeriod;
                    $assigned = round($assigned + $reduction, 2);
                }
                DB::connection('tenant')->table('loan_installments')
                    ->where('loan_row_id', $loan->row_id)
                    ->where('component', 'principal')
                    ->where('installment_number', $newNumber)
                    ->update([
                        'principal_due' => round((float) $row->principal_due - $reduction, 2),
                    ]);
            }

            // 6) Insert write_off row at installment_number = N+1.
            $writeOffDueDate = $loan->installments
                ->where('component', 'principal')
                ->where('installment_number', $N)
                ->first()?->due_date ?? $writtenOffAt->toDateString();

            LoanInstallment::query()->create([
                'loan_row_id' => $loan->row_id,
                'component' => 'write_off',
                'installment_number' => $N + 1,
                'due_date' => $writeOffDueDate,
                'principal_due' => $writeOffAmount,
                'interest_due' => 0,
                'principal_paid' => $writeOffAmount,
                'interest_paid' => 0,
                'penalty_due' => 0,
                'penalty_paid' => 0,
                'status' => 'paid',
                'paid_at' => $writtenOffAt,
            ]);

            // 7) Mark beneficiary.
            $beneficiary->update([
                'written_off_at' => $writtenOffAt,
                'written_off_amount' => $writeOffAmount,
            ]);

            // 8) Insert audit row.
            LoanBeneficiaryWriteOff::query()->create([
                'loan_row_id' => $loan->row_id,
                'member_row_id' => $memberRowId,
                'principal_balance' => $writeOffAmount,
                'installment_number' => $N,
                'written_off_at' => $writtenOffAt,
                'reason' => $reason !== '' ? $reason : null,
                'journal_entry_row_id' => $posted->row_id,
                'approved_by_user_id' => $userId,
            ]);

            return $loan->fresh(['installments', 'beneficiaries', 'product']);
        });
    }

    /**
     * Reschedule active loan: close old as rescheduled, open new loan with remaining principal.
     * Mirrors legacy /perguliran/rescedule.
     */
    public function reschedule(Loan $loan, array $data, int $userId): Loan
    {
        if (! in_array($loan->status, ['active', 'disbursed'], true)) {
            throw new RuntimeException('Reschedule hanya untuk pinjaman aktif.');
        }

        return DB::connection('tenant')->transaction(function () use ($loan, $data, $userId): Loan {
            $loan->loadMissing(['product', 'installments', 'borrower', 'committee', 'beneficiaries']);

            $principalRemaining = $this->principalRemaining($loan);
            if ($principalRemaining <= 0) {
                throw new RuntimeException('Sisa pokok harus lebih dari 0 untuk reschedule.');
            }

            $rescheduledAt = CarbonImmutable::parse((string) $data['rescheduled_at']);
            $term = (int) $data['term_months'];
            $serviceRateTotal = (float) $data['service_rate_total'];
            $method = (string) $data['installment_method'];
            $principalFreq = (string) $data['principal_frequency'];
            $interestFreq = (string) $data['interest_frequency'];
            $principalGraceMonths = (int) ($data['principal_grace_months'] ?? $loan->principal_grace_months);
            $interestGraceMonths = (int) ($data['interest_grace_months'] ?? $loan->interest_grace_months);

            $principalPeriods = $this->periods($principalFreq, $term, $principalGraceMonths);
            $interestPeriods = $this->periods($interestFreq, $term, $interestGraceMonths);
            $principalRatePerPeriod = $principalPeriods > 0 ? round($serviceRateTotal / $principalPeriods, 4) : 0.0;
            $interestRatePerPeriod = $interestPeriods > 0 ? round($serviceRateTotal / $interestPeriods, 4) : 0.0;

            $productCode = (string) ($loan->product?->code ?? '');
            $receivable = $this->resolveReceivableAccount($productCode);
            $cashAccountRowId = (int) ($loan->disbursement_account_row_id ?? 0);
            if ($cashAccountRowId <= 0) {
                throw new RuntimeException('Akun sumber dana pinjaman tidak ditemukan. Tidak dapat reschedule.');
            }
            $cashAccount = Account::on('tenant')->where('row_id', $cashAccountRowId)->firstOrFail();
            $this->ensureFiscalPeriod($rescheduledAt->toDateString());

            // 1) Close remaining principal as artificial settlement (legacy Angs. Resc.).
            $closeEntry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $rescheduledAt->toDateString(),
                'sequence_number' => 0,
                'source_type' => 'loan_reschedule_close',
                'source_row_id' => (int) $loan->row_id,
                'description' => sprintf('Angs. Resc. pinjaman #%s', $loan->loan_number ?? (string) $loan->row_id),
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);
            // Match legacy: debit kas, credit piutang.
            $closeEntry->lines()->create([
                'line_number' => 1,
                'account_row_id' => (int) $cashAccount->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Pelunasan reschedule',
                'debit' => $principalRemaining,
                'credit' => 0,
            ]);
            $closeEntry->lines()->create([
                'line_number' => 2,
                'account_row_id' => (int) $receivable->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Piutang dilunasi reschedule',
                'debit' => 0,
                'credit' => $principalRemaining,
            ]);
            $this->journalPosting->post($closeEntry, $userId);

            $fromStatus = $loan->status;
            $loan->update([
                'status' => 'rescheduled',
                'completed_at' => $rescheduledAt->toDateString(),
            ]);
            $loan->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => 'rescheduled',
                'principal_amount' => $principalRemaining,
                'product_row_id' => $loan->loan_product_row_id,
                'term_months' => (int) $loan->term_months,
                'notes' => sprintf('Reschedule. Sisa pokok %s dialihkan ke pinjaman baru.', number_format($principalRemaining, 2, ',', '.')),
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            // 2) New loan active with remaining principal.
            $newLoan = Loan::query()->create([
                'legacy_source' => $loan->legacy_source ?: 'group_loan',
                'loan_product_row_id' => $loan->loan_product_row_id,
                'sequence_number' => ((int) $loan->sequence_number) + 1,
                'proposed_at' => $rescheduledAt->toDateString(),
                'verified_at' => $rescheduledAt->toDateString(),
                'approved_at' => $rescheduledAt->toDateString(),
                'funded_at' => $rescheduledAt->toDateString(),
                'disbursed_at' => $rescheduledAt->toDateString(),
                'principal_amount' => $principalRemaining,
                'interest_rate' => $principalRatePerPeriod,
                'service_rate_total' => $serviceRateTotal,
                'term_months' => $term,
                'installment_method' => $method,
                'principal_frequency' => $principalFreq,
                'interest_frequency' => $interestFreq,
                'principal_grace_months' => $principalGraceMonths,
                'interest_grace_months' => $interestGraceMonths,
                'rounding_step' => isset($data['rounding_step']) && $data['rounding_step'] !== '' ? (int) $data['rounding_step'] : $loan->rounding_step,
                'status' => 'active',
                'verification_notes' => $loan->verification_notes,
                'disbursement_account_row_id' => $cashAccountRowId,
                'rescheduled_from_loan_row_id' => (int) $loan->row_id,
                'disbursement_notes' => sprintf('Pencairan reschedule dari pinjaman #%s', $loan->loan_number ?? (string) $loan->row_id),
                'created_by_user_id' => $userId,
            ]);

            $newLoan->borrower()->create([
                'member_row_id' => $loan->borrower?->member_row_id,
                'group_row_id' => $loan->borrower?->group_row_id,
            ]);

            foreach ($loan->committee as $member) {
                LoanCommittee::query()->create([
                    'loan_row_id' => $newLoan->row_id,
                    'position' => $member->position,
                    'member_row_id' => $member->member_row_id,
                    'member_name_snapshot' => $member->member_name_snapshot,
                    'snapshot_at' => $rescheduledAt->toDateString(),
                ]);
            }

            $totalAllocated = (float) $loan->beneficiaries
                ->filter(fn ($b) => $b->written_off_at === null)
                ->sum(fn ($b) => (float) $b->allocated_amount);
            $assigned = 0.0;
            $beneficiaries = $loan->beneficiaries
                ->filter(fn ($b) => $b->written_off_at === null)
                ->values();
            $count = $beneficiaries->count();
            foreach ($beneficiaries as $index => $beneficiary) {
                if ($totalAllocated > 0) {
                    $share = $index === $count - 1
                        ? round($principalRemaining - $assigned, 2)
                        : round($principalRemaining * ((float) $beneficiary->allocated_amount / $totalAllocated), 2);
                } else {
                    $share = $index === $count - 1
                        ? round($principalRemaining - $assigned, 2)
                        : round($principalRemaining / max(1, $count), 2);
                }
                $assigned = round($assigned + $share, 2);
                LoanBeneficiary::query()->create([
                    'loan_row_id' => $newLoan->row_id,
                    'member_row_id' => $beneficiary->member_row_id,
                    'proposed_amount' => $share,
                    'verified_amount' => $share,
                    'allocated_amount' => $share,
                ]);
            }

            $this->generatePrincipalSchedule($newLoan, $principalRemaining, $principalPeriods, $principalRatePerPeriod, $principalFreq, $rescheduledAt->toDateString(), $principalGraceMonths);
            $this->generateInterestSchedule($newLoan, $principalRemaining, $interestPeriods, $interestRatePerPeriod, $method, $interestFreq, $rescheduledAt->toDateString(), $interestGraceMonths);

            foreach (['draft', 'verified', 'waiting', 'active'] as $to) {
                $newLoan->statusHistories()->create([
                    'from_status' => $to === 'draft' ? null : match ($to) {
                        'verified' => 'draft',
                        'waiting' => 'verified',
                        'active' => 'waiting',
                        default => null,
                    },
                    'to_status' => $to,
                    'principal_amount' => $principalRemaining,
                    'product_row_id' => $newLoan->loan_product_row_id,
                    'term_months' => $term,
                    'notes' => $to === 'draft'
                        ? sprintf('Pinjaman reschedule dari #%s.', $loan->loan_number ?? (string) $loan->row_id)
                        : ($to === 'active' ? 'Pencairan reschedule.' : null),
                    'changed_by_user_id' => $userId,
                    'changed_at' => now(),
                ]);
            }

            // 3) Redisburse new loan (legacy Pencairan Resc.: debit piutang, credit kas).
            $openEntry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $rescheduledAt->toDateString(),
                'sequence_number' => 0,
                'source_type' => 'loan_reschedule_open',
                'source_row_id' => (int) $newLoan->row_id,
                'description' => sprintf('Pencairan Resc. pinjaman #%s', $newLoan->loan_number ?? (string) $newLoan->row_id),
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);
            $openEntry->lines()->create([
                'line_number' => 1,
                'account_row_id' => (int) $receivable->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Piutang pinjaman reschedule',
                'debit' => $principalRemaining,
                'credit' => 0,
            ]);
            $openEntry->lines()->create([
                'line_number' => 2,
                'account_row_id' => (int) $cashAccount->row_id,
                'organization_unit_row_id' => null,
                'description' => 'Kas/Bank sumber dana reschedule',
                'debit' => 0,
                'credit' => $principalRemaining,
            ]);
            $this->journalPosting->post($openEntry, $userId);

            return $newLoan->fresh(['product', 'borrower.group', 'committee', 'beneficiaries', 'installments']);
        });
    }

    public function cancelReschedule(Loan $newLoan, array $data, int $userId): Loan
    {
        if ($newLoan->rescheduled_from_loan_row_id === null) {
            throw new RuntimeException('Pinjaman ini bukan hasil reschedule.');
        }
        if (! in_array($newLoan->status, ['active', 'disbursed'], true)) {
            throw new RuntimeException('Cancel reschedule hanya untuk pinjaman aktif.');
        }

        return DB::connection('tenant')->transaction(function () use ($newLoan, $data, $userId): Loan {
            $newLoan->loadMissing(['installments', 'payments']);
            $oldLoan = Loan::query()->where('row_id', $newLoan->rescheduled_from_loan_row_id)->firstOrFail();

            // 1) Precondition: no payments on new loan.
            $principalPaid = (float) $newLoan->installments->sum('principal_paid');
            if ($principalPaid > 0.0) {
                throw new RuntimeException('Tidak dapat membatalkan reschedule: sudah ada angsuran dibayar.');
            }

            // 2) Find original status of old loan (from history).
            $historyEntry = $oldLoan->statusHistories()
                ->where('to_status', 'rescheduled')
                ->orderByDesc('changed_at')
                ->first();
            if ($historyEntry === null) {
                throw new RuntimeException('Histori reschedule loan asal tidak ditemukan.');
            }
            $restoreTo = (string) $historyEntry->from_status;
            if (! in_array($restoreTo, ['active', 'disbursed'], true)) {
                throw new RuntimeException("Status asal loan tidak valid: {$restoreTo}");
            }

            // 3) Hard-delete the 2 offsetting journal entries.
            // Posted jurnal normally immutable (JournalEntry booted hooks), but cancel
            // reschedule is a legitimate reverse — bypass hooks via raw query.
            $closeEntry = JournalEntry::query()
                ->where('source_type', 'loan_reschedule_close')
                ->where('source_row_id', $oldLoan->row_id)
                ->first();
            $openEntry = JournalEntry::query()
                ->where('source_type', 'loan_reschedule_open')
                ->where('source_row_id', $newLoan->row_id)
                ->first();

            if ($closeEntry === null || $openEntry === null) {
                throw new RuntimeException('Jurnal reschedule tidak ditemukan. Cancel gagal.');
            }
            DB::connection('tenant')->table('journal_entries')->where('row_id', $closeEntry->row_id)->delete();
            DB::connection('tenant')->table('journal_entries')->where('row_id', $openEntry->row_id)->delete();

            // 4) Hard-delete new loan (cascade beneficiaries/installments/committees/borrower).
            // Note: loan_payments restrictOnDelete, but precondition guarantees none exist.
            $newLoanNumber = $newLoan->loan_number ?? (string) $newLoan->row_id;
            $newLoan->delete();

            // 5) Restore old loan.
            $oldLoan->update([
                'status' => $restoreTo,
                'completed_at' => null,
            ]);
            $reason = trim((string) ($data['reason'] ?? ''));
            $oldLoan->statusHistories()->create([
                'from_status' => 'rescheduled',
                'to_status' => $restoreTo,
                'principal_amount' => $oldLoan->principal_amount,
                'product_row_id' => $oldLoan->loan_product_row_id,
                'term_months' => (int) $oldLoan->term_months,
                'notes' => sprintf('Cancel reschedule ke pinjaman #%s.%s', $newLoanNumber, $reason !== '' ? ' Alasan: '.$reason : ''),
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            return $oldLoan->fresh(['product', 'borrower.group', 'committee', 'beneficiaries', 'installments']);
        });
    }

    private function principalRemaining(Loan $loan): float
    {
        $remaining = 0.0;
        foreach ($loan->installments as $installment) {
            $remaining += (float) $installment->principal_due - (float) $installment->principal_paid;
        }

        return round(max(0, $remaining), 2);
    }

    private function interestRemaining(Loan $loan): float
    {
        $remaining = 0.0;
        foreach ($loan->installments as $installment) {
            $remaining += (float) $installment->interest_due - (float) $installment->interest_paid;
        }

        return round(max(0, $remaining), 2);
    }

    private function resolveAllowanceAccount(string $productCode): Account
    {
        return $this->resolveAccountByKey(
            productCode: $productCode,
            settingKey: 'account.cadangan_kerugian_',
            defaultCodes: self::DEFAULT_ALLOWANCE_CODES,
            label: 'allowance',
        );
    }

    public static function intervalMonths(string $frequency): int
    {
        return match ($frequency) {
            'bimonthly' => 2,
            'quarterly' => 3,
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
            default => 1,
        };
    }

    private function periods(string $frequency, int $term, int $graceMonths = 0): int
    {
        if ($frequency === 'at_maturity') {
            return 1;
        }

        $interval = self::intervalMonths($frequency);
        $firstPeriod = $graceMonths + $interval;

        return max(1, (int) floor(($term - $firstPeriod) / $interval) + 1);
    }

    public function resolveRoundingStep(Loan $loan): int
    {
        if ($loan->rounding_step !== null) {
            return (int) $loan->rounding_step;
        }

        $loan->loadMissing('product');
        $productRounding = (string) ($loan->product?->rounding_method ?? '0');
        if (is_numeric($productRounding)) {
            return (int) $productRounding;
        }

        return match ($productRounding) {
            'ceil_100', 'floor_100' => 100,
            'rupiah_bersih' => 1,
            default => 0,
        };
    }

    public function applyRounding(float $amount, int $step): float
    {
        if ($step <= 0) {
            return round($amount, 2);
        }

        return (float) (round($amount / $step) * $step);
    }

    private function generatePrincipalSchedule(Loan $loan, float $principal, int $periods, float $ratePerPeriod, string $frequency, string $startDate, int $graceMonths = 0): void
    {
        $start = CarbonImmutable::parse($startDate);
        $roundingStep = $this->resolveRoundingStep($loan);
        $rawPerPeriod = $principal / max(1, $periods);
        $roundedPerPeriod = $this->applyRounding($rawPerPeriod, $roundingStep);
        $totalScheduled = 0.0;

        for ($i = 1; $i <= $periods; $i++) {
            if ($i === $periods) {
                $amount = round($principal - $totalScheduled, 2);
            } else {
                $amount = $roundedPerPeriod;
                $totalScheduled = round($totalScheduled + $amount, 2);
            }

            $due = $frequency === 'at_maturity'
                ? $start->addMonths((int) $loan->term_months)
                : $this->advance($start, $frequency, $i + $graceMonths);

            LoanInstallment::query()->create([
                'loan_row_id' => $loan->row_id,
                'component' => 'principal',
                'installment_number' => $i,
                'due_date' => $due->toDateString(),
                'principal_due' => $amount,
                'interest_due' => 0,
                'status' => 'pending',
            ]);
        }
    }

    private function generateInterestSchedule(Loan $loan, float $principal, int $periods, float $ratePerPeriod, string $method, string $frequency, string $startDate, int $graceMonths = 0): void
    {
        $start = CarbonImmutable::parse($startDate);
        $roundingStep = $this->resolveRoundingStep($loan);

        if ($method === 'flat') {
            $rawPerPeriod = $principal * ($ratePerPeriod / 100);
            $perPeriod = $this->applyRounding($rawPerPeriod, $roundingStep);

            for ($i = 1; $i <= $periods; $i++) {
                LoanInstallment::query()->create([
                    'loan_row_id' => $loan->row_id,
                    'component' => 'interest',
                    'installment_number' => $i,
                    'due_date' => $this->advance($start, $frequency, $i + $graceMonths)->toDateString(),
                    'principal_due' => 0,
                    'interest_due' => $perPeriod,
                    'status' => 'pending',
                ]);
            }
        } elseif ($method === 'annuity') {
            $r = $ratePerPeriod / 100;
            $rawInstallment = $r > 0
                ? $principal * ($r * (1 + $r) ** $periods) / ((1 + $r) ** $periods - 1)
                : $principal / $periods;
            $installment = $this->applyRounding($rawInstallment, $roundingStep);
            $balance = $principal;

            for ($i = 1; $i <= $periods; $i++) {
                $rawInterestPart = $balance * $r;
                $interestPart = $this->applyRounding($rawInterestPart, $roundingStep);
                $principalPart = round($installment - $interestPart, 2);

                if ($i === $periods) {
                    $principalPart = round($balance, 2);
                    $installment = round($principalPart + $interestPart, 2);
                }

                LoanInstallment::query()->create([
                    'loan_row_id' => $loan->row_id,
                    'component' => 'interest',
                    'installment_number' => $i,
                    'due_date' => $this->advance($start, $frequency, $i + $graceMonths)->toDateString(),
                    'principal_due' => 0,
                    'interest_due' => $interestPart,
                    'status' => 'pending',
                ]);
                $balance -= $principalPart;
            }
        } else {
            $r = $ratePerPeriod / 100;
            $balance = $principal;
            $rawPerPeriod = $principal / max(1, $periods);
            $perPeriod = $this->applyRounding($rawPerPeriod, $roundingStep);

            for ($i = 1; $i <= $periods; $i++) {
                $rawInterestPart = $balance * $r;
                $interestPart = $this->applyRounding($rawInterestPart, $roundingStep);

                if ($i === $periods) {
                    $perPeriod = round($balance, 2);
                }

                LoanInstallment::query()->create([
                    'loan_row_id' => $loan->row_id,
                    'component' => 'interest',
                    'installment_number' => $i,
                    'due_date' => $this->advance($start, $frequency, $i + $graceMonths)->toDateString(),
                    'principal_due' => 0,
                    'interest_due' => $interestPart,
                    'status' => 'pending',
                ]);
                $balance -= $perPeriod;
            }
        }
    }

    private function advance(CarbonImmutable $start, string $frequency, int $multiplier): CarbonImmutable
    {
        $step = self::FREQUENCY_STEP[$frequency];
        $date = $start;
        foreach ($step as $unit => $value) {
            $date = $date->add($unit, $value * $multiplier);
        }

        return $date;
    }

    public function complete(Loan $loan, array $data, int $userId): Loan
    {
        return DB::connection('tenant')->transaction(function () use ($loan, $data, $userId): Loan {
            if (! in_array($loan->status, ['active', 'disbursed', 'completed'], true)) {
                throw new DomainException('Hanya pinjaman aktif/cair yang dapat divalidasi lunas.');
            }

            $fromStatus = $loan->status;
            $completedAt = $data['completed_at'] ?? now()->toDateString();
            $notes = ! empty($data['notes']) ? $data['notes'] : 'Validasi pelunasan pinjaman.';

            $loan->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
            ]);

            $loan->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => 'completed',
                'principal_amount' => (float) $loan->principal_amount,
                'product_row_id' => $loan->loan_product_row_id,
                'term_months' => (int) $loan->term_months,
                'notes' => $notes,
                'changed_by_user_id' => $userId,
                'changed_at' => now(),
            ]);

            return $loan->fresh();
        });
    }

    private function regenerateInstallmentSchedule(Loan $loan, float $principal): void
    {
        $principalFreq = (string) ($loan->principal_frequency ?: 'monthly');
        $interestFreq = (string) ($loan->interest_frequency ?: $principalFreq);
        $method = (string) ($loan->installment_method ?: 'flat');
        $term = (int) $loan->term_months;
        $serviceRateTotal = (float) ($loan->service_rate_total ?? $loan->interest_rate ?? 0);

        $principalGraceMonths = (int) ($loan->principal_grace_months ?? 0);
        $interestGraceMonths = (int) ($loan->interest_grace_months ?? 0);
        $principalPeriods = $this->periods($principalFreq, $term, $principalGraceMonths);
        $interestPeriods = $this->periods($interestFreq, $term, $interestGraceMonths);
        $principalRatePerPeriod = $principalPeriods > 0 ? round($serviceRateTotal / $principalPeriods, 4) : 0.0;
        $interestRatePerPeriod = $interestPeriods > 0 ? round($serviceRateTotal / $interestPeriods, 4) : 0.0;

        $startDate = $loan->approved_at?->format('Y-m-d')
            ?? $loan->verified_at?->format('Y-m-d')
            ?? $loan->proposed_at?->format('Y-m-d')
            ?? now()->toDateString();

        $loan->installments()->delete();
        $loan->refresh();

        if ($principal > 0) {
            $this->generatePrincipalSchedule($loan, $principal, $principalPeriods, $principalRatePerPeriod, $principalFreq, $startDate, $principalGraceMonths);
            $this->generateInterestSchedule($loan, $principal, $interestPeriods, $interestRatePerPeriod, $method, $interestFreq, $startDate, $interestGraceMonths);
        }
    }

    /**
     * Sync rounding from product defaults to all draft/verified loans and regenerate their schedules.
     *
     * @return int Number of loans updated.
     */
    public function syncRoundingFromProducts(): int
    {
        return DB::connection('tenant')->transaction(function (): int {
            $loans = Loan::query()
                ->with('product')
                ->whereIn('status', ['draft', 'verified'])
                ->get();

            $count = 0;

            foreach ($loans as $loan) {
                $productRounding = (string) ($loan->product?->rounding_method ?? '0');
                $step = is_numeric($productRounding) ? (int) $productRounding : match ($productRounding) {
                    'ceil_100', 'floor_100' => 100,
                    'rupiah_bersih' => 1,
                    default => 0,
                };

                $loan->update(['rounding_step' => $step > 0 ? $step : null]);
                $this->regenerateInstallmentSchedule($loan, (float) $loan->principal_amount);
                $count++;
            }

            return $count;
        });
    }
}
