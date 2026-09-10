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
            $table->decimal('principal_amount', 19, 2)->nullable()->after('to_status');
            $table->unsignedBigInteger('product_row_id')->nullable()->after('principal_amount');
            $table->unsignedSmallInteger('term_months')->nullable()->after('product_row_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('loan_status_histories', function (Blueprint $table): void {
            $table->dropColumn(['principal_amount', 'product_row_id', 'term_months']);
        });
    }
};
