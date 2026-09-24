<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function schema(): Builder
    {
        return Schema::connection((string) config('tenancy.tenant_connection', 'tenant'));
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

        $schema->create('loans_received', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->string('creditor_name', 200);
            $table->string('creditor_type', 60)->nullable();
            $table->string('contract_number', 100)->nullable();
            $table->date('contract_date')->nullable();
            $table->decimal('principal_amount', 19, 2)->default(0);
            $table->decimal('principal_remaining', 19, 2)->default(0);
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->string('interest_type', 30)->default('flat');
            $table->unsignedSmallInteger('tenor_months')->default(0);
            $table->unsignedSmallInteger('tenor_remaining_months')->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('purpose', 255)->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'ix_loans_received_status');
            $table->index(['tenant_id', 'creditor_type'], 'ix_loans_received_creditor_type');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('loans_received');
    }
};
