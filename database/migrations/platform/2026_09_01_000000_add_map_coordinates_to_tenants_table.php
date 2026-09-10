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
        $schema = $this->schema();

        $schema->table('tenants', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn('tenants', 'map_latitude')) {
                $table->decimal('map_latitude', 10, 7)->nullable()->after('district_code');
            }
            if (! $schema->hasColumn('tenants', 'map_longitude')) {
                $table->decimal('map_longitude', 10, 7)->nullable()->after('map_latitude');
            }
            if (! $schema->hasColumn('tenants', 'map_zoom')) {
                $table->unsignedTinyInteger('map_zoom')->nullable()->after('map_longitude');
            }
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        $schema->table('tenants', function (Blueprint $table) use ($schema): void {
            $cols = array_filter(['map_latitude', 'map_longitude', 'map_zoom'], fn ($c) => $schema->hasColumn('tenants', $c));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
