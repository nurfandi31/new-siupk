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
        $this->schema()->table('invoices', function (Blueprint $table): void {
            $table->boolean('blocks_access')->default(false)->after('status')->index();
        });
    }

    public function down(): void
    {
        $this->schema()->table('invoices', function (Blueprint $table): void {
            $table->dropColumn('blocks_access');
        });
    }
};
