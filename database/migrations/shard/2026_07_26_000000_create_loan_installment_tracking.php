<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
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

    private function addTenantIdentity(Blueprint $table): void
    {
        $table->bigIncrements('row_id');
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('id');

        $table->unique(['tenant_id', 'row_id']);
        $table->unique(['tenant_id', 'id']);
        $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
    }

    public function up(): void
    {
        $schema = $this->schema();

        $schema->create('loan_installment_tracking', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('loan_row_id');
            $table->unsignedSmallInteger('installment_number');
            $table->unsignedBigInteger('member_row_id');
            $table->decimal('principal_paid', 19, 2)->default(0);
            $table->decimal('interest_paid', 19, 2)->default(0);
            $table->decimal('penalty_paid', 19, 2)->default(0);
            $table->unsignedBigInteger('journal_entry_row_id')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'loan_row_id', 'installment_number', 'member_row_id'],
                'uq_tracking_member_installment'
            );
            $table->index(['tenant_id', 'loan_row_id', 'member_row_id'], 'ix_tracking_member');
            $table->foreign(['tenant_id', 'loan_row_id'], 'fk_tracking_loan')
                ->references(['tenant_id', 'row_id'])->on('loans')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'member_row_id'], 'fk_tracking_member')
                ->references(['tenant_id', 'row_id'])->on('members')->restrictOnDelete();
            // Composite FK cannot use ON DELETE SET NULL while tenant_id is NOT NULL (MySQL 1830).
            // App clears journal_entry_row_id on reversal; restrict keeps tenant isolation.
            $table->foreign(['tenant_id', 'journal_entry_row_id'], 'fk_tracking_journal')
                ->references(['tenant_id', 'row_id'])->on('journal_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('loan_installment_tracking');
    }
};
