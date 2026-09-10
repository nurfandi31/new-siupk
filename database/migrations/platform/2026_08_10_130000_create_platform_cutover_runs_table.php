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
        $this->schema()->create('platform_cutover_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('tenant_code', 50);
            $table->string('suffix', 20);
            $table->boolean('is_dry_run')->default(false);
            $table->json('options')->nullable();
            $table->string('status', 30)->default('pending');
            $table->json('steps')->nullable();
            $table->longText('output_log')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('platform_cutover_runs');
    }
};
