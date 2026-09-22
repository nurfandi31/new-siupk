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
        Schema::connection($this->connectionName())->table('loan_installments', function (Blueprint $table): void {
            // Kolom paritas pacuan: RencanaAngsuranI.target_pokok & target_jasa (running total kumulatif).
            $table->decimal('running_principal', 19, 2)->default(0)->after('interest_due');
            $table->decimal('running_interest', 19, 2)->default(0)->after('running_principal');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('loan_installments', function (Blueprint $table): void {
            $table->dropColumn(['running_principal', 'running_interest']);
        });
    }
};
