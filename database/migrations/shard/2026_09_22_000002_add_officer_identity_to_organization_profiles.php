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
            // Identitas direktur/manager — NIK untuk dokumen SPK/kuitansi/ba_pencairan
            $table->string('manager_nik', 32)->nullable()->after('manager_name');
            $table->string('manager_position', 120)->nullable()->after('manager_nik');
            $table->string('manager_address', 250)->nullable()->after('manager_position');

            // Sekretaris & Bendahara NIK
            $table->string('secretary_nik', 32)->nullable()->after('secretary_name');
            $table->string('treasurer_nik', 32)->nullable()->after('treasurer_name');

            // Kades / pejabat kecamatan untuk blok tandatangan BA & dokumen
            $table->string('kepala_desa_name', 120)->nullable()->after('treasurer_nik');
            $table->string('kepala_desa_nip', 32)->nullable()->after('kepala_desa_name');

            // Pengadilan Negeri yurisdiksi (paritas pacuan SPK)
            $table->string('court_of_jurisdiction', 120)->nullable()->after('kepala_desa_nip');

            // Sebutan level struktural (paritas pacuan $kec->sebutan_level_1/2/3)
            $table->string('institution_level_1', 60)->nullable()->after('court_of_jurisdiction');
            $table->string('institution_level_2', 60)->nullable()->after('institution_level_1');
            $table->string('institution_level_3', 60)->nullable()->after('institution_level_2');
        });
    }

    public function down(): void
    {
        $this->schema()->table('organization_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'manager_nik',
                'manager_position',
                'manager_address',
                'secretary_nik',
                'treasurer_nik',
                'kepala_desa_name',
                'kepala_desa_nip',
                'court_of_jurisdiction',
                'institution_level_1',
                'institution_level_2',
                'institution_level_3',
            ]);
        });
    }
};
