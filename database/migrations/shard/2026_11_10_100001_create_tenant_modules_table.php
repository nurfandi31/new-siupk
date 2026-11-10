<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Registry modul opsional per tenant.
 *
 * Tabel ini selalu ada di setiap shard (ukurannya kecil) dan menjadi satu-
 * satunya sumber kebenaran apakah sebuah modul (mis. simpanan) aktif untuk
 * tenant tertentu. Tabel data modul itu sendiri dibuat/dihapus secara dinamis
 * oleh module manager sehingga lokasi yang tidak memakai modul tidak menyimpan
 * tabel/data apa pun.
 */
return new class extends Migration
{
    private function schema(): Builder
    {
        return Schema::connection((string) config('tenancy.tenant_connection', 'tenant'));
    }

    public function up(): void
    {
        $schema = $this->schema();

        if ($schema->hasTable('tenant_modules')) {
            return;
        }

        $schema->create('tenant_modules', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('module_code', 40);
            $table->boolean('is_enabled')->default(false);
            /** Konfigurasi modul (bunga, pajak, admin, dsb). */
            $table->json('settings')->nullable();
            /** Daftar tabel fisik yang dibuat untuk modul ini. */
            $table->json('tables')->nullable();
            $table->dateTime('enabled_at')->nullable();
            $table->dateTime('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_code'], 'uq_tenant_modules');
            $table->index(['module_code', 'is_enabled'], 'ix_tenant_modules_enabled');
            $table->foreign('tenant_id', 'fk_tenant_modules_tenant')
                ->references('id')->on('tenant_registry')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('tenant_modules');
    }
};
