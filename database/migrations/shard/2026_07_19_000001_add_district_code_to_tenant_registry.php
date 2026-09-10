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
        $this->schema()->table('tenant_registry', function (Blueprint $table): void {
            $table->string('district_code', 6)->nullable()->after('name');
            $table->unique('district_code', 'uq_tenant_registry_district_code');
        });
    }

    public function down(): void
    {
        $this->schema()->table('tenant_registry', function (Blueprint $table): void {
            $table->dropUnique('uq_tenant_registry_district_code');
            $table->dropColumn('district_code');
        });
    }
};
