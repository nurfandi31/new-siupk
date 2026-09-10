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
        $this->schema()->create('sync_conflicts', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id')->nullable();
            $table->string('table_name', 100);
            $table->string('row_public_id', 100);
            $table->string('operation', 20);
            $table->string('reason', 60);
            $table->json('payload');
            $table->dateTime('client_updated_at')->nullable();
            $table->dateTime('last_pulled_at')->nullable();
            $table->string('status', 30)->default('pending_review');
            $table->timestamps();

            $table->unique(['tenant_id', 'row_id']);
            $table->index(['tenant_id', 'table_name', 'row_public_id', 'status'], 'ix_sync_conflicts_review');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->cascadeOnDelete();
        });

        $this->schema()->create('sync_mutations', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('mutation_uuid');
            $table->string('table_name', 100);
            $table->string('row_public_id', 100);
            $table->dateTime('applied_at');

            $table->unique(['tenant_id', 'mutation_uuid'], 'uq_sync_mutation_uuid');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->cascadeOnDelete();
        });

        $this->schema()->create('outbox', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('mutation_uuid')->unique();
            $table->string('table_name', 100);
            $table->string('operation', 20);
            $table->string('row_public_id', 100);
            $table->json('payload');
            $table->dateTime('created_at');
            $table->dateTime('pushed_at')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->index(['status', 'created_at'], 'ix_outbox_dispatch');
            $table->index(['table_name', 'row_public_id', 'status'], 'ix_outbox_row');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('outbox');
        $this->schema()->dropIfExists('sync_mutations');
        $this->schema()->dropIfExists('sync_conflicts');
    }
};
