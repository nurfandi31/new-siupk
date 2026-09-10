<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function schema(): Builder
    {
        return Schema::connection((string) config('tenancy.platform_connection', 'platform'));
    }

    public function up(): void
    {
        $connection = DB::connection((string) config('tenancy.platform_connection', 'platform'));
        $duplicates = $connection->table('tenant_memberships')
            ->select('user_id')
            ->where('status', 'active')
            ->groupBy('user_id')
            ->havingRaw('COUNT(DISTINCT tenant_id) > 1')
            ->pluck('user_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot assign one tenant per user; duplicate active memberships: '.$duplicates->implode(', '));
        }

        if (! $this->schema()->hasColumn('users', 'tenant_id')) {
            $this->schema()->table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('row_id');
                $table->index('tenant_id', 'ix_users_tenant');
                $table->foreign('tenant_id', 'fk_users_tenant')->references('row_id')->on('tenants')->restrictOnDelete();
            });
        }

        if ($connection->getDriverName() === 'sqlite') {
            $connection->statement(
                'UPDATE users SET tenant_id = (SELECT tm.tenant_id FROM tenant_memberships tm WHERE tm.user_id = users.row_id AND tm.status = "active" LIMIT 1) WHERE tenant_id IS NULL AND EXISTS (SELECT 1 FROM tenant_memberships tm WHERE tm.user_id = users.row_id AND tm.status = "active")'
            );
        } else {
            $connection->statement(
                'UPDATE users u JOIN tenant_memberships tm ON tm.user_id = u.row_id AND tm.status = "active" SET u.tenant_id = tm.tenant_id WHERE u.tenant_id IS NULL'
            );
        }

        // Users created before tenant assignment may remain unassigned until provisioned.
        // Access to tenant routes still requires an active membership.
    }

    public function down(): void
    {
        $schema = $this->schema();
        $schema->table('users', function (Blueprint $table): void {
            $table->dropForeign('fk_users_tenant');
            $table->dropIndex('ix_users_tenant');
            $table->dropColumn('tenant_id');
        });
    }
};
