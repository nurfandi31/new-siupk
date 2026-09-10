<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    public function up(): void
    {
        Schema::connection($this->connectionName())->table('loans', function (Blueprint $table): void {
            $table->dropColumn('installment_frequency');
            $table->string('principal_frequency', 20)->default('monthly')->after('installment_method');
            $table->string('interest_frequency', 20)->default('monthly')->after('principal_frequency');
        });

        Schema::connection($this->connectionName())->table('loan_installments', function (Blueprint $table): void {
            $table->string('component', 20)->default('combined')->after('loan_row_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('loan_installments', function (Blueprint $table): void {
            $table->dropColumn('component');
        });

        Schema::connection($this->connectionName())->table('loans', function (Blueprint $table): void {
            $table->dropColumn(['principal_frequency', 'interest_frequency']);
            $table->string('installment_frequency', 20)->default('monthly')->after('installment_method');
        });
    }
};
