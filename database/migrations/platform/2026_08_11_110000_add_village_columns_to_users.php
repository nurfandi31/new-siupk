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

        $schema->table('users', function (Blueprint $table): void {
            $table->boolean('is_village_user')->default(false)->after('is_province_user')->index();
            $table->unsignedBigInteger('village_row_id')->nullable()->after('is_village_user')->index();
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        $schema->table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_village_user']);
            $table->dropIndex(['village_row_id']);
            $table->dropColumn(['is_village_user', 'village_row_id']);
        });
    }
};
