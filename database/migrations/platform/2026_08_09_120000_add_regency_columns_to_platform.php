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

        $schema->table('database_shards', function (Blueprint $table): void {
            $table->string('regency_code', 10)->nullable()->after('name')->index();
            $table->string('regency_name', 100)->nullable()->after('regency_code');
        });

        $schema->table('tenants', function (Blueprint $table): void {
            $table->string('regency_code', 10)->nullable()->after('district_code')->index();
            $table->string('regency_name', 100)->nullable()->after('regency_code');
        });

        $schema->table('users', function (Blueprint $table): void {
            $table->boolean('is_regency_user')->default(false)->after('is_superadmin')->index();
            $table->string('regency_code', 10)->nullable()->after('is_regency_user')->index();
            $table->string('regency_name', 100)->nullable()->after('regency_code');
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        $schema->table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_regency_user']);
            $table->dropIndex(['regency_code']);
            $table->dropColumn(['is_regency_user', 'regency_code', 'regency_name']);
        });

        $schema->table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['regency_code']);
            $table->dropColumn(['regency_code', 'regency_name']);
        });

        $schema->table('database_shards', function (Blueprint $table): void {
            $table->dropIndex(['regency_code']);
            $table->dropColumn(['regency_code', 'regency_name']);
        });
    }
};
