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

        $schema->create('accounts', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->unsignedBigInteger('parent_row_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 180);
            $table->string('account_type', 30);
            $table->char('normal_balance', 1);
            $table->unsignedSmallInteger('level')->default(1);
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->date('deactivated_at')->nullable();
            $table->string('legacy_parent_code', 50)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_accounts_code');
            $table->index(['tenant_id', 'parent_row_id'], 'ix_accounts_parent');
            $table->index(['tenant_id', 'account_type', 'is_active'], 'ix_accounts_type');
            $table->foreign(['tenant_id', 'parent_row_id'], 'fk_accounts_parent')
                ->references(['tenant_id', 'row_id'])->on('accounts')->restrictOnDelete();
        });

        $schema->create('fiscal_periods', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('fiscal_month');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status', 20)->default('open');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'fiscal_year', 'fiscal_month'], 'uq_fiscal_period');
            $table->index(['tenant_id', 'status', 'starts_at', 'ends_at'], 'ix_fiscal_period_status');
        });

        $schema->create('journal_entries', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->string('journal_number', 80)->nullable();
            $table->date('transaction_date');
            $table->unsignedInteger('sequence_number')->default(0);
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_row_id')->nullable();
            $table->text('description')->nullable();
            $table->longText('legacy_relation')->nullable();
            $table->integer('legacy_transaction_type_id')->nullable();
            $table->integer('legacy_loan_id')->nullable();
            $table->integer('legacy_loan_item_id')->nullable();
            $table->string('legacy_debit_account_code', 50)->nullable();
            $table->string('legacy_credit_account_code', 50)->nullable();
            $table->string('legacy_amount_raw', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('reversed_entry_row_id')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by_user_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'journal_number'], 'uq_journal_number');
            $table->index(['tenant_id', 'transaction_date', 'status'], 'ix_journal_date_status');
            $table->index(['tenant_id', 'transaction_date', 'sequence_number', 'id'], 'ix_journal_report_order');
            $table->index(['tenant_id', 'source_type', 'source_row_id'], 'ix_journal_source');
            $table->foreign(['tenant_id', 'reversed_entry_row_id'], 'fk_journal_reversal')
                ->references(['tenant_id', 'row_id'])->on('journal_entries')->restrictOnDelete();
        });

        $schema->create('journal_lines', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('journal_entry_row_id');
            $table->unsignedSmallInteger('line_number');
            $table->unsignedBigInteger('account_row_id');
            $table->unsignedBigInteger('organization_unit_row_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('debit', 19, 2)->default(0);
            $table->decimal('credit', 19, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'journal_entry_row_id', 'line_number'], 'uq_journal_line_number');
            $table->index(['tenant_id', 'account_row_id', 'journal_entry_row_id'], 'ix_journal_lines_account');
            $table->foreign(['tenant_id', 'journal_entry_row_id'], 'fk_journal_lines_entry')
                ->references(['tenant_id', 'row_id'])->on('journal_entries')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'account_row_id'], 'fk_journal_lines_account')
                ->references(['tenant_id', 'row_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'organization_unit_row_id'], 'fk_journal_lines_unit')
                ->references(['tenant_id', 'row_id'])->on('organization_units')->restrictOnDelete();
        });

        $schema->create('account_opening_balances', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('account_row_id');
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('debit', 19, 2)->default(0);
            $table->decimal('credit', 19, 2)->default(0);
            $table->string('source', 30)->default('migration');
            $table->timestamps();

            $table->unique(['tenant_id', 'account_row_id', 'fiscal_year'], 'uq_account_opening_year');
            $table->foreign(['tenant_id', 'account_row_id'], 'fk_opening_account')
                ->references(['tenant_id', 'row_id'])->on('accounts')->restrictOnDelete();
        });

        $schema->create('account_monthly_balances', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('account_row_id');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('fiscal_month');
            $table->decimal('opening_debit', 19, 2)->default(0);
            $table->decimal('opening_credit', 19, 2)->default(0);
            $table->decimal('movement_debit', 19, 2)->default(0);
            $table->decimal('movement_credit', 19, 2)->default(0);
            $table->decimal('closing_debit', 19, 2)->default(0);
            $table->decimal('closing_credit', 19, 2)->default(0);
            $table->dateTime('recalculated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'row_id']);
            $table->unique(['tenant_id', 'account_row_id', 'fiscal_year', 'fiscal_month'], 'uq_account_month_balance');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
            $table->foreign(['tenant_id', 'account_row_id'], 'fk_monthly_balance_account')
                ->references(['tenant_id', 'row_id'])->on('accounts')->restrictOnDelete();
        });

        if (DB::connection($this->connectionName())->getDriverName() !== 'sqlite') {
            DB::connection($this->connectionName())->statement(
                "ALTER TABLE accounts ADD CONSTRAINT chk_accounts_normal_balance CHECK (normal_balance IN ('D', 'C'))"
            );
            DB::connection($this->connectionName())->statement(
                'ALTER TABLE fiscal_periods ADD CONSTRAINT chk_fiscal_month CHECK (fiscal_month BETWEEN 1 AND 12)'
            );
            DB::connection($this->connectionName())->statement(
                'ALTER TABLE journal_lines ADD CONSTRAINT chk_journal_line_amount CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))'
            );
            DB::connection($this->connectionName())->statement(
                'ALTER TABLE account_monthly_balances ADD CONSTRAINT chk_monthly_balance_month CHECK (fiscal_month BETWEEN 1 AND 12)'
            );
        }
    }

    public function down(): void
    {
        $schema = $this->schema();

        foreach ([
            'account_monthly_balances',
            'account_opening_balances',
            'journal_lines',
            'journal_entries',
            'fiscal_periods',
            'accounts',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
