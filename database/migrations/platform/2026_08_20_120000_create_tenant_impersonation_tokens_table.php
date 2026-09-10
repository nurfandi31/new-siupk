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
        $this->schema()->create('tenant_impersonation_tokens', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('impersonator_id')->index();
            $table->dateTime('expires_at')->index();
            $table->dateTime('used_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('tenant_id')->references('row_id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('row_id')->on('users')->cascadeOnDelete();
            $table->foreign('impersonator_id')->references('row_id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('tenant_impersonation_tokens');
    }
};
