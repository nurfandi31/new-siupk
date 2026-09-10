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
        $schema = $this->schema();

        $schema->create('legacy_migration_batches', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->string('source_database', 150);
            $table->string('source_suffix', 30)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('source_checksum', 128)->nullable();
            $table->string('target_checksum', 128)->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at'], 'ix_legacy_batches_tenant');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
        });

        $schema->create('legacy_record_mappings', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('batch_row_id');
            $table->string('source_table', 120);
            $table->string('source_id', 120);
            $table->string('source_secondary_key', 120)->default('');
            $table->string('target_table', 120);
            $table->unsignedBigInteger('target_row_id');
            $table->unsignedBigInteger('target_local_id')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->dateTime('migrated_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'source_table', 'source_id', 'source_secondary_key'],
                'uq_legacy_mapping_source'
            );
            $table->index(['tenant_id', 'target_table', 'target_row_id'], 'ix_legacy_mapping_target');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
            $table->foreign('batch_row_id')->references('row_id')->on('legacy_migration_batches')->cascadeOnDelete();
        });

        $schema->create('migration_reconciliation_results', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('batch_row_id');
            $table->string('scope', 80);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedBigInteger('source_count')->default(0);
            $table->unsignedBigInteger('target_count')->default(0);
            $table->decimal('source_debit', 19, 2)->nullable();
            $table->decimal('target_debit', 19, 2)->nullable();
            $table->decimal('source_credit', 19, 2)->nullable();
            $table->decimal('target_credit', 19, 2)->nullable();
            $table->decimal('source_balance', 19, 2)->nullable();
            $table->decimal('target_balance', 19, 2)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->json('difference_details')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'scope', 'period_start'], 'ix_reconciliation_scope');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
            $table->foreign('batch_row_id')->references('row_id')->on('legacy_migration_batches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = $this->schema();
        $schema->dropIfExists('migration_reconciliation_results');
        $schema->dropIfExists('legacy_record_mappings');
        $schema->dropIfExists('legacy_migration_batches');
    }
};
