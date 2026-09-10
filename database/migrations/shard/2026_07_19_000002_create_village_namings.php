<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
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
        $this->schema()->create('village_namings', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id');
            $table->string('code', 50);
            $table->string('village_name', 100);
            $table->string('village_head_name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'row_id']);
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code'], 'uq_village_namings_code');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
        });

        $now = now();
        $rows = [];

        foreach (DB::connection($this->connectionName())->table('tenant_registry')->pluck('id') as $tenantId) {
            $rows[] = [
                'tenant_id' => $tenantId,
                'id' => 1,
                'code' => 'village',
                'village_name' => 'Desa',
                'village_head_name' => 'Kepala Desa',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = [
                'tenant_id' => $tenantId,
                'id' => 2,
                'code' => 'urban-village',
                'village_name' => 'Kelurahan',
                'village_head_name' => 'Lurah',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::connection($this->connectionName())->table('village_namings')->insert($rows);
        }
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('village_namings');
    }
};
