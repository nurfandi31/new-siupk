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

        $schema->create('tenant_registry', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->char('public_id', 26)->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('status', 30)->default('active')->index();
            $table->string('schema_version', 100)->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();
        });

        $schema->create('tenant_sequences', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id');
            $table->string('sequence_name', 100);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->dateTime('updated_at');

            $table->primary(['tenant_id', 'sequence_name']);
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = $this->schema();
        $schema->dropIfExists('tenant_sequences');
        $schema->dropIfExists('tenant_registry');
    }
};
