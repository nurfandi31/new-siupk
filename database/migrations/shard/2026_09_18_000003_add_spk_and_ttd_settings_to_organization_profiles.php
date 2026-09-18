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
            // SPK redaksi keyword — body 8 Pasal (HTML/text) untuk kelompok
            $table->longText('spk_template')->nullable()->after('village_installment_day');

            // SPK redaksi keyword — body khusus individu
            $table->longText('spk_template_individual')->nullable()->after('spk_template');

            // TTD configuration — JSON: list of {role, label, name}
            $table->json('signature_config')->nullable()->after('spk_template_individual');

            // TTD configuration khusus individu — JSON
            $table->json('signature_config_individual')->nullable()->after('signature_config');

            // Tanggal pakai aplikasi (validasi tgl_cair >= nilai ini)
            $table->date('operational_start_date_v2')->nullable()->after('signature_config_individual');

            // Sebutan level struktural (untuk TTD/hormat)
            $table->string('manager_title', 60)->nullable()->after('operational_start_date_v2');
            $table->string('secretary_title', 60)->nullable()->after('manager_title');
            $table->string('treasurer_title', 60)->nullable()->after('secretary_title');
            $table->string('verifier_title', 60)->nullable()->after('treasurer_title');

            // Nama-nama pejabat (untuk TTD default kalau signature_config kosong)
            $table->string('manager_name', 120)->nullable()->after('verifier_title');
            $table->string('secretary_name', 120)->nullable()->after('manager_name');
            $table->string('treasurer_name', 120)->nullable()->after('secretary_name');
            $table->string('verifier_name', 120)->nullable()->after('treasurer_name');

            // Branding tambahan (untuk kop surat / laporan)
            $table->string('brand_short', 60)->nullable()->after('verifier_name');
            $table->string('contact_email_secondary', 190)->nullable()->after('brand_short');

            // Kolektibilitas config (JSON: rules lancar/kurang lancar/diragukan/macet)
            $table->json('collectibility_rules')->nullable()->after('contact_email_secondary');
        });
    }

    public function down(): void
    {
        $this->schema()->table('organization_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'spk_template',
                'spk_template_individual',
                'signature_config',
                'signature_config_individual',
                'operational_start_date_v2',
                'manager_title',
                'secretary_title',
                'treasurer_title',
                'verifier_title',
                'manager_name',
                'secretary_name',
                'treasurer_name',
                'verifier_name',
                'brand_short',
                'contact_email_secondary',
                'collectibility_rules',
            ]);
        });
    }
};
