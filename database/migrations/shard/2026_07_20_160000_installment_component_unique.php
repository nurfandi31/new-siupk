<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    public function up(): void
    {
        $conn = DB::connection($this->connectionName());
        if ($conn->getDriverName() !== 'sqlite') {
            $conn->statement('ALTER TABLE loan_installments DROP FOREIGN KEY fk_installments_loan');
        }
        Schema::connection($this->connectionName())->table('loan_installments', function (Blueprint $table): void {
            $table->dropUnique('uq_loan_installment_number');
            $table->unique(['tenant_id', 'loan_row_id', 'component', 'installment_number'], 'uq_loan_installment_component_number');
        });
        if ($conn->getDriverName() !== 'sqlite') {
            $conn->statement('ALTER TABLE loan_installments ADD CONSTRAINT fk_installments_loan FOREIGN KEY (tenant_id, loan_row_id) REFERENCES loans(tenant_id, row_id) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        $conn = DB::connection($this->connectionName());
        if ($conn->getDriverName() !== 'sqlite') {
            $conn->statement('ALTER TABLE loan_installments DROP FOREIGN KEY fk_installments_loan');
        }
        Schema::connection($this->connectionName())->table('loan_installments', function (Blueprint $table): void {
            $table->dropUnique('uq_loan_installment_component_number');
            $table->unique(['tenant_id', 'loan_row_id', 'installment_number'], 'uq_loan_installment_number');
        });
        if ($conn->getDriverName() !== 'sqlite') {
            $conn->statement('ALTER TABLE loan_installments ADD CONSTRAINT fk_installments_loan FOREIGN KEY (tenant_id, loan_row_id) REFERENCES loans(tenant_id, row_id) ON DELETE CASCADE');
        }
    }
};
