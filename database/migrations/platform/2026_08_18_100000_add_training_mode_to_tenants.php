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
        $this->schema()->table('tenants', function (Blueprint $table): void {
            $table->boolean('is_training_mode')->default(false)->index()->after('status');
            $table->dateTime('training_started_at')->nullable()->after('is_training_mode');
            $table->dateTime('training_ended_at')->nullable()->after('training_started_at');
        });
    }

    public function down(): void
    {
        $this->schema()->table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['is_training_mode', 'training_started_at', 'training_ended_at']);
        });
    }
};
