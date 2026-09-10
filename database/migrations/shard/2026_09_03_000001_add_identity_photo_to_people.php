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

        if (! $schema->hasColumn('people', 'identity_photo_path')) {
            $schema->table('people', function (Blueprint $table): void {
                $table->string('identity_photo_path', 500)->nullable()->after('photo_path');
            });
        }
    }

    public function down(): void
    {
        $schema = $this->schema();

        if ($schema->hasColumn('people', 'identity_photo_path')) {
            $schema->table('people', function (Blueprint $table): void {
                $table->dropColumn('identity_photo_path');
            });
        }
    }
};
