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
        $this->schema()->create('whatsapp_instances', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id');
            $table->string('name', 100);
            $table->string('instance_name', 120);
            $table->string('phone_number', 30)->nullable();
            $table->string('status', 30)->default('close');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('daily_limit')->default(0)->comment('0 = unlimited');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'instance_name']);
            $table->index(['tenant_id', 'is_active', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('whatsapp_instances');
    }
};
