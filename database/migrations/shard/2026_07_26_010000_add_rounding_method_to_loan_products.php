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

        Schema::connection($this->connectionName())->table('loan_products', function (Blueprint $table): void {
            $table->string('rounding_method', 20)->default('decimal_2')->after('default_term_months');
        });

        if ($conn->getDriverName() !== 'sqlite') {
            $conn->statement(
                "ALTER TABLE loan_products ADD CONSTRAINT chk_loan_products_rounding CHECK (rounding_method IN ('decimal_2', 'rupiah_bersih', 'ceil_100', 'floor_100'))"
            );
        }
    }

    public function down(): void
    {
        $conn = DB::connection($this->connectionName());

        if ($conn->getDriverName() !== 'sqlite') {
            try {
                $conn->statement('ALTER TABLE loan_products DROP CHECK chk_loan_products_rounding');
            } catch (Throwable) {
                try {
                    $conn->statement('ALTER TABLE loan_products DROP CONSTRAINT chk_loan_products_rounding');
                } catch (Throwable) {
                    // Ignore if constraint does not exist
                }
            }
        }

        Schema::connection($this->connectionName())->table('loan_products', function (Blueprint $table): void {
            $table->dropColumn('rounding_method');
        });
    }
};
