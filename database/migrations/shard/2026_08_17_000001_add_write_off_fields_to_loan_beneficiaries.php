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

    public function up(): void
    {
        $this->schema()->table('loan_beneficiaries', function (Blueprint $table): void {
            $table->decimal('written_off_amount', 19, 2)->nullable()->after('verified_amount');
            $table->dateTime('written_off_at')->nullable()->after('written_off_amount');
            $table->text('written_off_reason')->nullable()->after('written_off_at');

            $table->index(['tenant_id', 'loan_row_id', 'written_off_at'], 'ix_loan_beneficiaries_write_off');
        });
    }

    public function down(): void
    {
        $this->schema()->table('loan_beneficiaries', function (Blueprint $table): void {
            $table->dropIndex('ix_loan_beneficiaries_write_off');
            $table->dropColumn(['written_off_amount', 'written_off_at', 'written_off_reason']);
        });
    }
};
