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
        $this->schema()->table('organization_units', function (Blueprint $table): void {
            $table->string('institution_identity_number', 100)->nullable()->after('phone');
            $table->string('leader_name', 180)->nullable()->after('institution_identity_number');
            $table->string('responsible_name', 180)->nullable()->after('leader_name');
            $table->unique(['tenant_id', 'institution_identity_number'], 'uq_org_units_institution_identity');
            $table->index(['tenant_id', 'type', 'is_active'], 'ix_org_units_type_active');
        });
    }

    public function down(): void
    {
        $this->schema()->table('organization_units', function (Blueprint $table): void {
            $table->dropUnique('uq_org_units_institution_identity');
            $table->dropIndex('ix_org_units_type_active');
            $table->dropColumn([
                'institution_identity_number',
                'leader_name',
                'responsible_name',
            ]);
        });
    }
};
