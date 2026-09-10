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
        $this->schema()->create('loan_beneficiary_write_offs', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('loan_row_id');
            $table->unsignedBigInteger('member_row_id');
            $table->decimal('principal_balance', 19, 2)->default(0);
            $table->unsignedSmallInteger('installment_number');
            $table->dateTime('written_off_at');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('journal_entry_row_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'loan_row_id', 'member_row_id'], 'uq_loan_beneficiary_write_off');
            $table->index(['tenant_id', 'loan_row_id', 'written_off_at'], 'ix_beneficiary_write_off_loan');
            $table->index(['tenant_id', 'member_row_id'], 'ix_beneficiary_write_off_member');
            $table->foreign(['tenant_id', 'loan_row_id'], 'fk_beneficiary_write_off_loan')
                ->references(['tenant_id', 'row_id'])->on('loans')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'member_row_id'], 'fk_beneficiary_write_off_member')
                ->references(['tenant_id', 'row_id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'journal_entry_row_id'], 'fk_beneficiary_write_off_journal')
                ->references(['tenant_id', 'row_id'])->on('journal_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('loan_beneficiary_write_offs');
    }
};
