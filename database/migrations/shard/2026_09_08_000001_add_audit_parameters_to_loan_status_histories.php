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
        Schema::connection($this->connectionName())->table('loan_status_histories', function (Blueprint $table): void {
            $table->decimal('service_rate_total', 9, 4)->nullable()->after('term_months');
            $table->string('principal_frequency', 30)->nullable()->after('service_rate_total');
            $table->string('interest_frequency', 30)->nullable()->after('principal_frequency');
            $table->unsignedSmallInteger('principal_grace_months')->default(0)->after('interest_frequency');
            $table->unsignedSmallInteger('interest_grace_months')->default(0)->after('principal_grace_months');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('loan_status_histories', function (Blueprint $table): void {
            $table->dropColumn([
                'service_rate_total',
                'principal_frequency',
                'interest_frequency',
                'principal_grace_months',
                'interest_grace_months',
            ]);
        });
    }
};
