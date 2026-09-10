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
        $this->schema()->create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            // actor_type membedakan superadmin, sistem/job, atau aktor anonim.
            $table->string('actor_type', 30)->default('user')->index();
            $table->string('actor_name', 150)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('action', 60)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('audit_logs');
    }
};
