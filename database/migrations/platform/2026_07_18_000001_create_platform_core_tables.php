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

        $schema->create('users', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->char('public_id', 26)->unique();
            $table->string('name', 150);
            $table->string('email', 190)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->string('status', 30)->default('active')->index();
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('tenants', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->char('public_id', 26)->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('status', 30)->default('provisioning')->index();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->json('metadata')->nullable();
            $table->dateTime('provisioned_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->timestamps();
        });

        $schema->create('database_shards', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->char('public_id', 26)->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('driver', 30)->default('mysql');
            $table->string('host', 255);
            $table->unsignedSmallInteger('port')->default(3306);
            $table->string('database_name', 100);
            $table->string('credential_reference', 150);
            $table->string('placement_type', 20)->default('shared');
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('maximum_weight')->nullable();
            $table->unsignedInteger('current_weight')->default(0);
            $table->timestamps();

            $table->unique(['host', 'port', 'database_name'], 'uq_database_shards_endpoint');
        });

        $schema->create('tenant_placements', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('shard_id');
            $table->string('status', 20)->default('active')->index();
            $table->dateTime('placed_at');
            $table->dateTime('moved_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->index(['shard_id', 'status']);
            $table->foreign('tenant_id')->references('row_id')->on('tenants')->restrictOnDelete();
            $table->foreign('shard_id')->references('row_id')->on('database_shards')->restrictOnDelete();
        });

        $schema->create('tenant_memberships', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status', 20)->default('active')->index();
            $table->dateTime('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->foreign('tenant_id')->references('row_id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('row_id')->on('users')->cascadeOnDelete();
        });

        $schema->create('shard_schema_versions', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('shard_id');
            $table->string('current_version', 100)->nullable();
            $table->string('target_version', 100)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamps();

            $table->unique('shard_id');
            $table->foreign('shard_id')->references('row_id')->on('database_shards')->cascadeOnDelete();
        });

        $schema->create('tenant_migration_runs', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('target_shard_id');
            $table->string('source_database', 150);
            $table->string('source_tenant_suffix', 30)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('source_counts')->nullable();
            $table->json('target_counts')->nullable();
            $table->json('reconciliation_result')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->foreign('tenant_id')->references('row_id')->on('tenants')->restrictOnDelete();
            $table->foreign('target_shard_id')->references('row_id')->on('database_shards')->restrictOnDelete();
        });

        $schema->create('plans', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('subscriptions', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('status', 30)->default('active')->index();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('row_id')->on('tenants')->restrictOnDelete();
            $table->foreign('plan_id')->references('row_id')->on('plans')->restrictOnDelete();
        });

        $schema->create('licenses', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('secret_hash', 255);
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('row_id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        foreach ([
            'licenses',
            'subscriptions',
            'plans',
            'tenant_migration_runs',
            'shard_schema_versions',
            'tenant_memberships',
            'tenant_placements',
            'database_shards',
            'tenants',
            'users',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
