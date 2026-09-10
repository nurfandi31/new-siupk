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
            $table->unsignedBigInteger('rescheduled_from_loan_row_id')->nullable()->after('disbursement_account_row_id');
            $table->foreign(['tenant_id', 'rescheduled_from_loan_row_id'], 'fk_loans_rescheduled_from')
                ->references(['tenant_id', 'row_id'])->on('loans')->restrictOnDelete();
            $table->index(['tenant_id', 'rescheduled_from_loan_row_id'], 'ix_loans_rescheduled_from');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('loans', function (Blueprint $table): void {
            $table->dropForeign('fk_loans_rescheduled_from');
            $table->dropIndex('ix_loans_rescheduled_from');
            $table->dropColumn('rescheduled_from_loan_row_id');
        });
    }
};
