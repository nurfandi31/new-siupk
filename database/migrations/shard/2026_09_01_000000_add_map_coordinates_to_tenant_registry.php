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

        $schema->table('tenant_registry', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn('tenant_registry', 'map_latitude')) {
                $table->decimal('map_latitude', 10, 7)->nullable()->after('district_code');
            }
            if (! $schema->hasColumn('tenant_registry', 'map_longitude')) {
                $table->decimal('map_longitude', 10, 7)->nullable()->after('map_latitude');
            }
            if (! $schema->hasColumn('tenant_registry', 'map_zoom')) {
                $table->unsignedTinyInteger('map_zoom')->nullable()->after('map_longitude');
            }
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        $schema->table('tenant_registry', function (Blueprint $table) use ($schema): void {
            $cols = array_filter(['map_latitude', 'map_longitude', 'map_zoom'], fn ($c) => $schema->hasColumn('tenant_registry', $c));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
