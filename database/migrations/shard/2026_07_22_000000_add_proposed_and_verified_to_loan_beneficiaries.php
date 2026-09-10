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
            $table->decimal('proposed_amount', 19, 2)->nullable()->after('allocated_amount');
            $table->decimal('verified_amount', 19, 2)->nullable()->after('proposed_amount');
        });
    }

    public function down(): void
    {
        $this->schema()->table('loan_beneficiaries', function (Blueprint $table): void {
            $table->dropColumn(['proposed_amount', 'verified_amount']);
        });
    }
};
