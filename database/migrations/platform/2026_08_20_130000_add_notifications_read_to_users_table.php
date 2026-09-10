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
            $table->json('notifications_read')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        $schema->table('users', function (Blueprint $table): void {
            $table->dropColumn('notifications_read');
        });
    }
};
