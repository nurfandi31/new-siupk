<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    private function schema(): Builder
    {
        return Schema::connection($this->connectionName());
    }

    public function up(): void
    {
        $this->schema()->table('loans', function (Blueprint $table): void {
            $table->unsignedBigInteger('disbursement_account_row_id')->nullable()->after('completed_at');
            $table->text('disbursement_notes')->nullable()->after('disbursement_account_row_id');

            $table->foreign('disbursement_account_row_id', 'fk_loans_disbursement_account')
                ->references('row_id')->on('accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema()->table('loans', function (Blueprint $table): void {
            $table->dropForeign('fk_loans_disbursement_account');
            $table->dropColumn(['disbursement_account_row_id', 'disbursement_notes']);
        });
    }
};
