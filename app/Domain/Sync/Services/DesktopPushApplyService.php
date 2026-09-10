<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanPayment;
use App\Domain\Lending\Models\LoanStatusHistory;
use App\Domain\Lending\Services\LoanService;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Throwable;

final class DesktopPushApplyService
{
    private const MASTER_TABLES = [
        'people',
        'members',
        'member_addresses',
        'member_businesses',
        'member_guarantors',
        'groups',
        'group_members',
        'group_officers',
        'business_types',
        'activity_types',
        'group_levels',
        'group_functions',
        'village_namings',
        'organization_profiles',
        'organization_units',
    ];

    public const DESKTOP_TABLES = TenantSnapshotService::TABLES_IN_ORDER;

    /**
     * @var array<string, array<int, string>>
     */
    public static function mobileTableOperations(): array
    {
        return [
            'loan_payments' => ['insert'],
            'loans' => ['update'],
        ];
    }

    public function __construct(
        private readonly TenantWorkbench $workbench,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $mutations
     * @return array{accepted: array<int, string>, conflicts: array<int, array<string, mixed>>, rejected: array<int, array<string, mixed>>}
     */
    public function apply(
        Tenant $tenant,
        array $mutations,
        ?string $lastPulledAt,
        ?array $tableOperations = null,
        ?int $actorUserId = null,
    ): array {
        if ($tableOperations === null) {
            $tableOperations = array_fill_keys(self::DESKTOP_TABLES, ['insert', 'update', 'delete']);
        }

        return $this->applyWithPolicy($tenant, $mutations, $lastPulledAt, $tableOperations, $actorUserId);
    }

    /**
     * @param  array<string, array<int, string>>  $tableOperations
     * @param  array<int, array<string, mixed>>  $mutations
     * @return array{accepted: array<int, string>, conflicts: array<int, array<string, mixed>>, rejected: array<int, array<string, mixed>>}
     */
    public function applyWithPolicy(
        Tenant $tenant,
        array $mutations,
        ?string $lastPulledAt,
        array $tableOperations,
        ?int $actorUserId = null,
    ): array {
        $lastPulled = $this->timestamp($lastPulledAt);

        $result = $this->workbench->run($tenant, function (Tenant $workbenchTenant) use ($mutations, $lastPulled, $tableOperations, $actorUserId): array {
            $accepted = [];
            $conflicts = [];
            $rejected = [];
            $tenantId = (int) $workbenchTenant->row_id;
            $connection = (string) config('tenancy.tenant_connection', 'tenant');
            $schema = Schema::connection($connection);

            foreach ($mutations as $mutation) {
                $mutationUuid = (string) ($mutation['mutation_uuid'] ?? '');
                $tableName = (string) ($mutation['table_name'] ?? '');
                $operation = strtolower((string) ($mutation['operation'] ?? ''));
                $rowPublicId = (string) ($mutation['row_public_id'] ?? '');
                $payload = (array) ($mutation['payload'] ?? []);
                $payload = Arr::except($payload, ['_error']);

                if (! $this->isValidMutation($mutationUuid, $tableName, $operation, $rowPublicId)
                    || ! in_array($tableName, array_keys($tableOperations), true)
                    || ! in_array($operation, $tableOperations[$tableName], true)
                    || ! $schema->hasTable($tableName)) {
                    $rejected[] = $this->result($mutation, 'invalid_mutation_or_table');

                    continue;
                }

                if (DB::connection($connection)->table('sync_mutations')->where([
                    'tenant_id' => $tenantId,
                    'mutation_uuid' => $mutationUuid,
                ])->exists()) {
                    $accepted[] = $mutationUuid;

                    continue;
                }

                $clientUpdatedAt = $this->timestamp($mutation['client_updated_at'] ?? $payload['updated_at'] ?? null);
                if ($clientUpdatedAt !== null) {
                    $clientUpdatedAt = $clientUpdatedAt->setTimezone(config('app.timezone'));
                }
                if ($clientUpdatedAt === null && $operation !== 'delete') {
                    $rejected[] = $this->result($mutation, 'client_updated_at_required');

                    continue;
                }

                try {
                    $outcome = DB::connection($connection)->transaction(function () use (
                        $connection,
                        $schema,
                        $tenantId,
                        $tableName,
                        $operation,
                        $rowPublicId,
                        $payload,
                        $clientUpdatedAt,
                        $lastPulled,
                        $mutationUuid,
                        $actorUserId,
                        $workbenchTenant,
                    ): string {
                        $row = DB::connection($connection)->table($tableName)->where([
                            'tenant_id' => $tenantId,
                            'id' => $rowPublicId,
                        ])->first();

                        if ($this->shouldConflict($tableName, $operation, $row, $clientUpdatedAt, $lastPulled)) {
                            $this->recordConflict(
                                $connection,
                                $tenantId,
                                $tableName,
                                $rowPublicId,
                                $operation,
                                $operation === 'delete' ? 'delete_conflict' : 'server_wins',
                                $payload,
                                $clientUpdatedAt,
                            );

                            return 'conflict';
                        }

                        $columns = $schema->getColumnListing($tableName);
                        $values = array_intersect_key($payload, array_flip($columns));
                        $values['tenant_id'] = $tenantId;
                        $values['id'] = (int) $rowPublicId;
                        unset($values['row_id']);

                        if ($tableName === 'loans' && $operation === 'update') {
                            $allowedValues = Arr::only($values, ['status', 'verified_at', 'verification_notes']);

                            return $this->applyLoanVerification(
                                $connection,
                                $workbenchTenant,
                                (int) $rowPublicId,
                                $allowedValues,
                                $actorUserId,
                                $mutationUuid,
                            );
                        }

                        if ($tableName === 'loan_payments' && $operation === 'insert') {
                            return $this->applyMobileLoanPayment(
                                $connection,
                                $workbenchTenant,
                                $payload,
                                $actorUserId,
                                $mutationUuid,
                            );
                        }

                        if ($row === null) {
                            if ($operation === 'delete') {
                                DB::connection($connection)->table('sync_mutations')->insert([
                                    'tenant_id' => $tenantId,
                                    'mutation_uuid' => $mutationUuid,
                                    'table_name' => $tableName,
                                    'row_public_id' => $rowPublicId,
                                    'applied_at' => now()->toDateTimeString(),
                                ]);
                                $this->recordAudit($connection, $schema->hasTable('audit_logs'), $tenantId, $tableName, $rowPublicId, $operation, $mutationUuid);

                                return 'accepted';
                            }

                            if ($schema->hasColumn($tableName, 'created_at') && ! isset($values['created_at'])) {
                                $values['created_at'] = ($clientUpdatedAt ?? now())->toDateTimeString();
                            }
                            if ($schema->hasColumn($tableName, 'updated_at') && $clientUpdatedAt !== null) {
                                $values['updated_at'] = $clientUpdatedAt->toDateTimeString();
                            }

                            DB::connection($connection)->table($tableName)->insert($values);
                        } elseif ($operation === 'delete') {
                            DB::connection($connection)->table($tableName)->where([
                                'tenant_id' => $tenantId,
                                'id' => $rowPublicId,
                            ])->delete();
                        } else {
                            if ($schema->hasColumn($tableName, 'updated_at') && $clientUpdatedAt !== null) {
                                $values['updated_at'] = $clientUpdatedAt->toDateTimeString();
                            }

                            DB::connection($connection)->table($tableName)->where([
                                'tenant_id' => $tenantId,
                                'id' => (int) $rowPublicId,
                            ])->update($values);
                        }

                        DB::connection($connection)->table('sync_mutations')->insert([
                            'tenant_id' => $tenantId,
                            'mutation_uuid' => $mutationUuid,
                            'table_name' => $tableName,
                            'row_public_id' => $rowPublicId,
                            'applied_at' => now()->toDateTimeString(),
                        ]);

                        $this->recordAudit($connection, $schema->hasTable('audit_logs'), $tenantId, $tableName, $rowPublicId, $operation, $mutationUuid, $actorUserId);

                        return 'accepted';
                    });
                } catch (Throwable $exception) {
                    $this->recordConflict(
                        $connection,
                        $tenantId,
                        $tableName,
                        $rowPublicId,
                        $operation,
                        'apply_failed',
                        $payload,
                        $clientUpdatedAt,
                        $exception->getMessage(),
                    );
                    $rejected[] = $this->result($mutation, 'apply_failed', $exception->getMessage());

                    continue;
                }

                if ($outcome === 'accepted') {
                    $accepted[] = $mutationUuid;
                } else {
                    $conflicts[] = $this->result($mutation, $operation === 'delete' ? 'delete_conflict' : 'server_wins');
                }
            }

            return ['accepted' => $accepted, 'conflicts' => $conflicts, 'rejected' => $rejected];
        });

        return $result;
    }

    private function shouldConflict(
        string $tableName,
        string $operation,
        ?object $row,
        ?Carbon $clientUpdatedAt,
        ?Carbon $lastPulled,
    ): bool {
        if (in_array($tableName, self::MASTER_TABLES, true)) {
            return $row !== null
                && $operation !== 'insert'
                && $clientUpdatedAt !== null
                && $row->updated_at !== null
                && Carbon::parse((string) $row->updated_at, config('app.timezone'))->greaterThan($clientUpdatedAt);
        }

        if ($row === null) {
            return false;
        }

        if ($row->updated_at !== null && $clientUpdatedAt !== null
            && Carbon::parse((string) $row->updated_at, config('app.timezone'))->greaterThan($clientUpdatedAt)) {
            return true;
        }

        return $lastPulled !== null
            && $row->updated_at !== null
            && Carbon::parse((string) $row->updated_at, config('app.timezone'))->greaterThan($lastPulled);
    }

    private function recordConflict(
        string $connection,
        int $tenantId,
        string $tableName,
        string $rowPublicId,
        string $operation,
        string $reason,
        array $payload,
        ?Carbon $clientUpdatedAt,
        ?string $error = null,
    ): void {
        if ($error !== null) {
            $payload['_error'] = $error;
        }

        DB::connection($connection)->table('sync_conflicts')->insert([
            'tenant_id' => $tenantId,
            'table_name' => $tableName,
            'row_public_id' => $rowPublicId,
            'operation' => $operation,
            'reason' => $reason,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'client_updated_at' => $clientUpdatedAt?->toDateTimeString(),
            'last_pulled_at' => null,
            'status' => 'pending_review',
        ]);
    }

    private function recordAudit(
        string $connection,
        bool $hasAuditTable,
        int $tenantId,
        string $tableName,
        string $rowPublicId,
        string $operation,
        string $mutationUuid,
        ?int $actorUserId = null,
    ): void {
        if (! $hasAuditTable) {
            return;
        }

        $nextId = (int) (DB::connection($connection)->table('audit_logs')->where('tenant_id', $tenantId)
            ->max('id') ?? 0) + 1;

        DB::connection($connection)->table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'id' => $nextId,
            'actor_user_id' => $actorUserId,
            'action' => ($actorUserId === null ? 'desktop_sync.' : 'mobile_sync.').$operation,
            'auditable_type' => $tableName,
            'auditable_row_id' => (int) $rowPublicId,
            'before_values' => null,
            'after_values' => json_encode(['mutation_uuid' => $mutationUuid], JSON_THROW_ON_ERROR),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'occurred_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    private function isValidMutation(
        string $mutationUuid,
        string $tableName,
        string $operation,
        string $rowPublicId,
    ): bool {
        return $mutationUuid !== ''
            && Uuid::isValid($mutationUuid)
            && $tableName !== ''
            && in_array($operation, ['insert', 'update', 'delete'], true)
            && ctype_digit($rowPublicId);
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function applyLoanVerification(
        string $connection,
        Tenant $tenant,
        int $loanPublicId,
        array $values,
        ?int $actorUserId,
        string $mutationUuid,
    ): string {
        $loan = app(LoanService::class)->verify(
            Loan::query()->with('beneficiaries')->where([
                'tenant_id' => (int) $tenant->row_id,
                'id' => $loanPublicId,
            ])->firstOrFail(),
            [
                'verified_at' => $values['verified_at'] ?? now()->toDateString(),
                'verification_notes' => $values['verification_notes'] ?? 'Diverifikasi via Mobile Sync',
            ],
            (int) ($actorUserId ?? 0),
        );

        $this->recordAudit(
            $connection,
            Schema::connection($connection)->hasTable('audit_logs'),
            (int) $tenant->row_id,
            'loans',
            (string) $loanPublicId,
            'update',
            $mutationUuid,
            $actorUserId,
        );

        $statusHistory = new LoanStatusHistory;
        $statusHistory->setConnection($connection);
        $statusHistory->fill([
            'tenant_id' => (int) $tenant->row_id,
            'id' => $this->nextTenantId($connection, 'loan_status_histories'),
            'public_id' => (string) Str::ulid(),
            'loan_row_id' => (int) $loanPublicId,
            'from_status' => 'proposed',
            'to_status' => 'verified',
            'principal_amount' => 0,
            'notes' => $values['verification_notes'] ?? 'Diverifikasi via Mobile Sync',
            'changed_by_user_id' => $actorUserId,
            'changed_at' => now()->toDateTimeString(),
        ]);
        $statusHistory->save();

        return 'accepted';
    }

    private function nextTenantId(string $connection, string $tableName): int
    {
        return (int) (DB::connection($connection)->table($tableName)
            ->max('id') ?? 0) + 1;
    }

    private function applyMobileLoanPayment(
        string $connection,
        Tenant $tenant,
        array $payload,
        ?int $actorUserId,
        string $mutationUuid,
    ): string {
        $tenantId = (int) $tenant->row_id;
        $loan = Loan::query()->where([
            'tenant_id' => $tenantId,
            'id' => (int) ($payload['loan_row_id'] ?? 0),
        ])->first();

        if ($loan === null) {
            throw new RuntimeException('Loan not found.');
        }

        $principal = (float) ($payload['principal_amount'] ?? 0);
        $interest = (float) ($payload['interest_amount'] ?? 0);
        $penalty = (float) ($payload['penalty_amount'] ?? 0);
        if ($principal + $interest + $penalty <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $posted = app(LoanService::class)->recordInstallmentPayment([
            'loan_id' => (int) $loan->row_id,
            'cash_account_row_id' => (int) ($payload['cash_account_row_id'] ?? 0),
            'reference' => (int) ($payload['member_id'] ?? 0),
            'transaction_date' => (string) ($payload['transaction_date'] ?? now()->toDateString()),
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'penalty_amount' => $penalty,
            'description' => (string) ($payload['description'] ?? 'Pembayaran via Mobile Sync'),
        ], (int) ($actorUserId ?? 0));

        $payment = new LoanPayment;
        $payment->setConnection($connection);
        $payment->fill([
            'tenant_id' => $tenantId,
            'loan_row_id' => (int) $loan->row_id,
            'payment_number' => 'MOB-'.$mutationUuid,
            'paid_at' => $payload['transaction_date'] ?? now()->toDateTimeString(),
            'amount' => round($principal + $interest + $penalty, 2),
            'payment_method' => 'mobile_sync',
            'reference_number' => $mutationUuid,
            'journal_entry_row_id' => (int) $posted->row_id,
            'created_by_user_id' => $actorUserId,
        ]);
        $payment->save();

        $this->recordAudit(
            $connection,
            Schema::connection($connection)->hasTable('audit_logs'),
            $tenantId,
            'loan_payments',
            $mutationUuid,
            'insert',
            $mutationUuid,
            $actorUserId,
        );

        return 'accepted';
    }

    private function result(array $mutation, string $reason, ?string $message = null): array
    {
        return [
            'mutation_uuid' => (string) ($mutation['mutation_uuid'] ?? ''),
            'table_name' => (string) ($mutation['table_name'] ?? ''),
            'row_public_id' => (string) ($mutation['row_public_id'] ?? ''),
            'reason' => $reason,
            'message' => $message,
        ];
    }
}
