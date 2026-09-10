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

        if (! $schema->hasColumn('tenant_registry', 'map_zoom')) {
            $schema->table('tenant_registry', function (Blueprint $table): void {
                $table->unsignedTinyInteger('map_zoom')->nullable()->after('map_longitude');
            });
        }
    }

    public function down(): void
    {
        $schema = $this->schema();

        if ($schema->hasColumn('tenant_registry', 'map_zoom')) {
            $schema->table('tenant_registry', function (Blueprint $table): void {
                $table->dropColumn('map_zoom');
            });
        }
    }
};
