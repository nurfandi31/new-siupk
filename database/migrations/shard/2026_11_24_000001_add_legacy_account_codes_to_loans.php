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
            // Kode akun pacuan yang benar-benar ada di chart legacy:
            // {"principal":"1.1.03.07","revenue":"4.1.01.07","penalty":"4.1.02.07"}.
            // Disimpan eksplisit agar kode produk yang tidak punya akun legacy
            // (mis. kode 10) tidak jatuh ke akun Next yang kebetulan sama kodenya.
            $table->json('legacy_account_codes')->nullable()->after('legacy_product_kode');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('loans', function (Blueprint $table): void {
            $table->dropColumn('legacy_account_codes');
        });
    }
};
