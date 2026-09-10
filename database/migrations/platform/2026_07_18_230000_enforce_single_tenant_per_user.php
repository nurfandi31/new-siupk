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
        return Schema::connection((string) config('tenancy.platform_connection', 'platform'));
    }

    public function up(): void
    {
        $schema = $this->schema();
        $schema->table('tenant_memberships', function (Blueprint $table): void {
            $table->unique('user_id', 'uq_tenant_memberships_user');
        });
    }

    public function down(): void
    {
        $this->schema()->table('tenant_memberships', function (Blueprint $table): void {
            $table->dropUnique('uq_tenant_memberships_user');
        });
    }
};
