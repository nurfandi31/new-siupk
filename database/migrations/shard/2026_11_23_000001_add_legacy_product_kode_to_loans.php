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
            // Kode produk legacy (`jenis_produk_pinjaman.kode`). Dipakai untuk
            // memilih akun pacuan 1.1.03.xx / 4.1.01.xx / 4.1.02.xx pada
            // pinjaman hasil migrasi yang produk Next-nya digabung (mis. PI).
            $table->string('legacy_product_kode', 8)->nullable()->after('loan_product_row_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('loans', function (Blueprint $table): void {
            $table->dropColumn('legacy_product_kode');
        });
    }
};
