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

        $schema->create('loan_committee', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('loan_row_id');
            $table->string('position', 30);
            $table->unsignedBigInteger('member_row_id');
            $table->string('member_name_snapshot', 180)->nullable();
            $table->date('snapshot_at');
            $table->timestamps();

            $table->index(['tenant_id', 'loan_row_id'], 'ix_loan_committee_loan');
            $table->unique(['tenant_id', 'loan_row_id', 'position'], 'uq_loan_committee_position');
            $table->foreign(['tenant_id', 'loan_row_id'], 'fk_loan_committee_loan')
                ->references(['tenant_id', 'row_id'])->on('loans')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'member_row_id'], 'fk_loan_committee_member')
                ->references(['tenant_id', 'row_id'])->on('members')->restrictOnDelete();
        });

        $schema->create('loan_beneficiaries', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('loan_row_id');
            $table->unsignedBigInteger('member_row_id');
            $table->decimal('allocated_amount', 19, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'loan_row_id', 'member_row_id'], 'uq_loan_beneficiary');
            $table->foreign(['tenant_id', 'loan_row_id'], 'fk_loan_beneficiaries_loan')
                ->references(['tenant_id', 'row_id'])->on('loans')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'member_row_id'], 'fk_loan_beneficiaries_member')
                ->references(['tenant_id', 'row_id'])->on('members')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $schema = $this->schema();
        $schema->dropIfExists('loan_beneficiaries');
        $schema->dropIfExists('loan_committee');
    }
};
