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
        $this->schema()->create('whatsapp_platform_instances', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->string('name', 100)->unique();
            $table->string('instance_name', 120)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('status', 30)->default('disconnected');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'status']);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('whatsapp_platform_instances');
    }
};
