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
            $table->json('permissions')->nullable()->after('is_system')
                ->comment('Custom permissions array for this role. NULL inherits config pack.');
            $table->string('description', 255)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('roles', function (Blueprint $table): void {
            $table->dropColumn(['permissions', 'description']);
        });
    }
};
