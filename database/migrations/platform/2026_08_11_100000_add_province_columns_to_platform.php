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
            $table->string('province_code', 10)->nullable()->after('name')->index();
            $table->string('province_name', 100)->nullable()->after('province_code');
        });

        $schema->table('tenants', function (Blueprint $table): void {
            $table->string('province_code', 10)->nullable()->after('district_code')->index();
            $table->string('province_name', 100)->nullable()->after('province_code');
        });

        $schema->table('users', function (Blueprint $table): void {
            $table->boolean('is_province_user')->default(false)->after('is_regency_user')->index();
            $table->string('province_code', 10)->nullable()->after('is_province_user')->index();
            $table->string('province_name', 100)->nullable()->after('province_code');
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        $schema->table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_province_user']);
            $table->dropIndex(['province_code']);
            $table->dropColumn(['is_province_user', 'province_code', 'province_name']);
        });

        $schema->table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['province_code']);
            $table->dropColumn(['province_code', 'province_name']);
        });

        $schema->table('database_shards', function (Blueprint $table): void {
            $table->dropIndex(['province_code']);
            $table->dropColumn(['province_code', 'province_name']);
        });
    }
};
