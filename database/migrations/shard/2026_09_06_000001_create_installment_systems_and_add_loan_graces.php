<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    private function schema(): Builder
    {
        return Schema::connection($this->connectionName());
    }

    public function up(): void
    {
        $schema = $this->schema();

        $schema->create('installment_systems', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id');
            $table->unsignedBigInteger('legacy_id');
            $table->string('name');
            $table->text('description');
            $table->unsignedTinyInteger('interval_months');
            $table->unsignedSmallInteger('sort_order');
            $table->unsignedTinyInteger('principal_grace_months')->default(0);
            $table->unsignedTinyInteger('interest_grace_months')->default(0);
            $table->unsignedTinyInteger('principal_interval_months')->default(1);
            $table->unsignedTinyInteger('interest_interval_months')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'sort_order']);
        });

        $schema->table('loans', function (Blueprint $table): void {
            $table->unsignedInteger('principal_grace_months')->default(0)->after('interest_frequency');
            $table->unsignedInteger('interest_grace_months')->default(0)->after('principal_grace_months');
        });
    }

    public function down(): void
    {
        $this->schema()->table('loans', function (Blueprint $table): void {
            $table->dropColumn(['principal_grace_months', 'interest_grace_months']);
        });

        $this->schema()->dropIfExists('installment_systems');
    }
};
