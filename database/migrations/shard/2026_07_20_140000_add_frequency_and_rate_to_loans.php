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
            $table->string('installment_frequency', 20)->default('monthly')->after('installment_method');
            $table->decimal('service_rate_total', 9, 4)->default(0)->after('installment_frequency');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('loans', function (Blueprint $table): void {
            $table->dropColumn(['installment_frequency', 'service_rate_total']);
        });
    }
};
