<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    public function up(): void
    {
        Schema::connection($this->connectionName())->table('roles', function (Blueprint $table): void {
            $table->json('menu_access')->nullable()->after('permissions')
                ->comment('Explicit allowed menu keys. NULL = unrestricted (follow module permissions).');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('roles', function (Blueprint $table): void {
            $table->dropColumn('menu_access');
        });
    }
};
