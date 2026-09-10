<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    private function schema(): Builder
    {
        return Schema::connection($this->connectionName());
    }

    private function addTenantIdentity(Blueprint $table, bool $publicId = false): void
    {
        $table->bigIncrements('row_id');
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('id');

        if ($publicId) {
            $table->char('public_id', 26)->unique();
        }

        $table->unique(['tenant_id', 'row_id']);
        $table->unique(['tenant_id', 'id']);
        $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
    }

    public function up(): void
    {
        $schema = $this->schema();

        $schema->create('loan_products', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->string('code', 50);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('interest_method', 30);
            $table->decimal('default_interest_rate', 9, 4)->default(0);
            $table->unsignedSmallInteger('default_term_months')->nullable();
            $table->decimal('minimum_amount', 19, 2)->nullable();
            $table->decimal('maximum_amount', 19, 2)->nullable();
            $table->string('borrower_scope', 20)->default('both');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_loan_products_code');
            $table->index(['tenant_id', 'is_active'], 'ix_loan_products_active');
        });

        $schema->create('loans', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id');
            $table->string('legacy_source', 30);
            $table->char('public_id', 26)->unique();
            $table->string('loan_number', 80)->nullable();
            $table->unsignedBigInteger('loan_product_row_id');
            $table->unsignedInteger('sequence_number')->default(1);
            $table->date('proposed_at')->nullable();
            $table->date('verified_at')->nullable();
            $table->date('approved_at')->nullable();
            $table->date('funded_at')->nullable();
            $table->date('disbursed_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->decimal('principal_amount', 19, 2)->default(0);
            $table->decimal('interest_rate', 9, 4)->default(0);
            $table->unsignedSmallInteger('term_months')->default(0);
            $table->string('installment_method', 30)->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('verification_notes')->nullable();
            $table->text('guidance_notes')->nullable();
            $table->time('verification_time')->nullable();
            $table->string('disbursement_schedule_text', 200)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'row_id']);
            $table->unique(['tenant_id', 'legacy_source', 'id'], 'uq_loans_legacy_identity');
            $table->unique(['tenant_id', 'loan_number'], 'uq_loans_number');
            $table->index(['tenant_id', 'status', 'disbursed_at'], 'ix_loans_status_date');
            $table->index(['tenant_id', 'loan_product_row_id', 'status'], 'ix_loans_product_status');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
            $table->foreign(['tenant_id', 'loan_product_row_id'], 'fk_loans_product')
                ->references(['tenant_id', 'row_id'])->on('loan_products')->restrictOnDelete();
        });

        $schema->create('loan_borrowers', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('loan_row_id');
            $table->unsignedBigInteger('member_row_id')->nullable();
            $table->unsignedBigInteger('group_row_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'loan_row_id'], 'uq_loan_borrowers_loan');
            $table->foreign(['tenant_id', 'loan_row_id'], 'fk_loan_borrowers_loan')
                ->references(['tenant_id', 'row_id'])->on('loans')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'member_row_id'], 'fk_loan_borrowers_member')
                ->references(['tenant_id', 'row_id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'group_row_id'], 'fk_loan_borrowers_group')
                ->references(['tenant_id', 'row_id'])->on('groups')->restrictOnDelete();
        });

        $schema->create('loan_status_histories', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('loan_row_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->dateTime('changed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'loan_row_id', 'changed_at'], 'ix_loan_status_history');
            $table->foreign(['tenant_id', 'loan_row_id'], 'fk_loan_status_loan')
                ->references(['tenant_id', 'row_id'])->on('loans')->cascadeOnDelete();
        });

        $schema->create('loan_installments', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('loan_row_id');
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('principal_due', 19, 2)->default(0);
            $table->decimal('interest_due', 19, 2)->default(0);
            $table->decimal('principal_paid', 19, 2)->default(0);
            $table->decimal('interest_paid', 19, 2)->default(0);
            $table->decimal('penalty_due', 19, 2)->default(0);
            $table->decimal('penalty_paid', 19, 2)->default(0);
            $table->string('status', 30)->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'loan_row_id', 'installment_number'], 'uq_loan_installment_number');
            $table->index(['tenant_id', 'due_date', 'status'], 'ix_installments_due');
            $table->foreign(['tenant_id', 'loan_row_id'], 'fk_installments_loan')
                ->references(['tenant_id', 'row_id'])->on('loans')->cascadeOnDelete();
        });

        $schema->create('loan_payments', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->unsignedBigInteger('loan_row_id');
            $table->string('payment_number', 80)->nullable();
            $table->dateTime('paid_at');
            $table->decimal('amount', 19, 2);
            $table->string('payment_method', 30)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->unsignedBigInteger('journal_entry_row_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'payment_number'], 'uq_loan_payments_number');
            $table->index(['tenant_id', 'loan_row_id', 'paid_at'], 'ix_loan_payments_loan_date');
            $table->foreign(['tenant_id', 'loan_row_id'], 'fk_loan_payments_loan')
                ->references(['tenant_id', 'row_id'])->on('loans')->restrictOnDelete();
            $table->foreign(['tenant_id', 'journal_entry_row_id'], 'fk_loan_payments_journal')
                ->references(['tenant_id', 'row_id'])->on('journal_entries')->restrictOnDelete();
        });

        $schema->create('loan_payment_allocations', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('payment_row_id');
            $table->unsignedBigInteger('installment_row_id')->nullable();
            $table->string('component', 30);
            $table->decimal('amount', 19, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'payment_row_id'], 'ix_payment_allocations_payment');
            $table->foreign(['tenant_id', 'payment_row_id'], 'fk_allocations_payment')
                ->references(['tenant_id', 'row_id'])->on('loan_payments')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'installment_row_id'], 'fk_allocations_installment')
                ->references(['tenant_id', 'row_id'])->on('loan_installments')->restrictOnDelete();
        });

        $schema->create('loan_write_offs', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('loan_row_id');
            $table->decimal('principal_balance', 19, 2)->default(0);
            $table->decimal('interest_balance', 19, 2)->default(0);
            $table->dateTime('written_off_at');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('journal_entry_row_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'loan_row_id', 'written_off_at'], 'ix_write_offs_loan');
            $table->foreign(['tenant_id', 'loan_row_id'], 'fk_write_offs_loan')
                ->references(['tenant_id', 'row_id'])->on('loans')->restrictOnDelete();
            $table->foreign(['tenant_id', 'journal_entry_row_id'], 'fk_write_offs_journal')
                ->references(['tenant_id', 'row_id'])->on('journal_entries')->restrictOnDelete();
        });

        if (DB::connection($this->connectionName())->getDriverName() !== 'sqlite') {
            DB::connection($this->connectionName())->statement(
                "ALTER TABLE loan_products ADD CONSTRAINT chk_loan_product_borrower_scope CHECK (borrower_scope IN ('member', 'group', 'both'))"
            );
            DB::connection($this->connectionName())->statement(
                "ALTER TABLE loans ADD CONSTRAINT chk_loans_legacy_source CHECK (legacy_source IN ('member_loan', 'group_loan'))"
            );
            DB::connection($this->connectionName())->statement(
                'ALTER TABLE loan_borrowers ADD CONSTRAINT chk_loan_borrower_one_owner CHECK ((member_row_id IS NOT NULL AND group_row_id IS NULL) OR (member_row_id IS NULL AND group_row_id IS NOT NULL))'
            );
            DB::connection($this->connectionName())->statement(
                "ALTER TABLE loan_payment_allocations ADD CONSTRAINT chk_payment_component CHECK (component IN ('principal', 'interest', 'penalty', 'insurance', 'other'))"
            );
        }
    }

    public function down(): void
    {
        $schema = $this->schema();

        foreach ([
            'loan_write_offs',
            'loan_payment_allocations',
            'loan_payments',
            'loan_installments',
            'loan_status_histories',
            'loan_borrowers',
            'loans',
            'loan_products',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
