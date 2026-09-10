<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    private function dropRoundingCheckConstraint($conn): void
    {
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

    public function up(): void
    {
        $conn = DB::connection($this->connectionName());

        if ($conn->getDriverName() !== 'sqlite') {
            $this->dropRoundingCheckConstraint($conn);
            $conn->statement(
                "ALTER TABLE loan_products ADD CONSTRAINT chk_loan_products_rounding CHECK (rounding_method IN ('decimal_2', 'rupiah_bersih', 'ceil_100', 'floor_100', '0', '100', '500', '1000', '5000', '10000', '50000'))"
            );
        }
    }

    public function down(): void
    {
        $conn = DB::connection($this->connectionName());

        if ($conn->getDriverName() !== 'sqlite') {
            $this->dropRoundingCheckConstraint($conn);
            $conn->statement(
                "ALTER TABLE loan_products ADD CONSTRAINT chk_loan_products_rounding CHECK (rounding_method IN ('decimal_2', 'rupiah_bersih', 'ceil_100', 'floor_100'))"
            );
        }
    }
};
