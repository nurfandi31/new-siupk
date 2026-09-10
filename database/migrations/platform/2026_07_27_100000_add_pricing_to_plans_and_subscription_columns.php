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

        $schema->table('plans', function (Blueprint $table): void {
            $table->decimal('price_amount', 19, 2)->default(0)->after('name');
            $table->char('currency', 3)->default('IDR')->after('price_amount');
            $table->string('billing_period', 20)->default('monthly')->after('currency');
        });

        $schema->table('subscriptions', function (Blueprint $table): void {
            $table->boolean('auto_renew')->default(true)->after('ends_at');
            $table->dateTime('cancelled_at')->nullable()->after('auto_renew');
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        $schema->table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['auto_renew', 'cancelled_at']);
        });

        $schema->table('plans', function (Blueprint $table): void {
            $table->dropColumn(['price_amount', 'currency', 'billing_period']);
        });
    }
};
