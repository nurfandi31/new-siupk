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
        $this->schema()->table('organization_profiles', function (Blueprint $table): void {
            $table->string('district_name', 100)->nullable()->after('address');
            $table->string('regency_name', 100)->nullable()->after('district_name');
        });
    }

    public function down(): void
    {
        $this->schema()->table('organization_profiles', function (Blueprint $table): void {
            $table->dropColumn(['district_name', 'regency_name']);
        });
    }
};
