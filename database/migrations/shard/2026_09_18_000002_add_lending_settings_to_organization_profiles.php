<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    private function schema(): Builder
    {
        return Schema::connection($this->connectionName());
    }

    public function up(): void
    {
        $this->schema()->table('organization_profiles', function (Blueprint $table): void {
            // Pembulatan angsuran (legacy pacuan: '5000', '+5000', '-5000', '100', dst.)
            $table->string('installment_rounding', 12)->default('5000')->after('phone');

            // Batas tanggal cair — kalau tanggal cair >= nilai ini, angsuran pertama mundur 1 bulan
            $table->unsignedTinyInteger('disbursement_cutoff_day')->default(0)->after('installment_rounding');

            // Hari jadwal angsuran desa (override default tanggal cair; 0 = ikut tanggal cair)
            $table->unsignedTinyInteger('village_installment_day')->default(0)->after('disbursement_cutoff_day');
        });
    }

    public function down(): void
    {
        $this->schema()->table('organization_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'installment_rounding',
                'disbursement_cutoff_day',
                'village_installment_day',
            ]);
        });
    }
};
