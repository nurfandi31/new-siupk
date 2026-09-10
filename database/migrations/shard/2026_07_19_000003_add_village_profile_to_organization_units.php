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
        $this->schema()->table('organization_units', function (Blueprint $table): void {
            $table->unsignedBigInteger('village_naming_id')->nullable()->after('type');
            $table->string('village_head_name', 180)->nullable()->after('phone');
            $table->string('village_head_phone', 20)->nullable()->after('village_head_name');
            $table->string('village_head_nip', 30)->nullable()->after('village_head_phone');
            $table->string('village_secretary_name', 180)->nullable()->after('village_head_nip');
            $table->string('village_secretary_phone', 20)->nullable()->after('village_secretary_name');
            $table->string('village_council_name', 180)->nullable()->after('village_secretary_phone');
            $table->string('installment_schedule', 100)->nullable()->after('village_council_name');
            $table->foreign(['tenant_id', 'village_naming_id'], 'fk_org_units_village_naming')
                ->references(['tenant_id', 'row_id'])
                ->on('village_namings')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema()->table('organization_units', function (Blueprint $table): void {
            $table->dropForeign('fk_org_units_village_naming');
            $table->dropColumn([
                'village_naming_id',
                'village_head_name',
                'village_head_phone',
                'village_head_nip',
                'village_secretary_name',
                'village_secretary_phone',
                'village_council_name',
                'installment_schedule',
            ]);
        });
    }
};
