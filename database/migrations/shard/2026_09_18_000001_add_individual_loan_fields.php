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
        Schema::connection($this->connectionName())->table('loans', function (Blueprint $table): void {
            // SPK (Surat Perjanjian Kredit) — diset saat pencairan
            $table->string('spk_no', 80)->nullable()->after('loan_number');

            // Sumber dana / rekening pencairan
            $table->unsignedSmallInteger('funding_source')->nullable()->after('disbursed_at');

            // Waktu & tempat pencairan (gabungan "waktu_tempat" ala pacuan)
            $table->string('disbursement_slot', 120)->nullable()->after('funding_source');

            // Jaminan (collateral) — JSON untuk fleksibilitas (kendaraan, sertifikat tanah, dll)
            $table->json('collateral')->nullable()->after('disbursement_slot');

            // Catatan tambahan verifikasi (vs verification_notes yang sudah ada)
            $table->text('verification_remarks')->nullable()->after('collateral');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('loans', function (Blueprint $table): void {
            $table->dropColumn([
                'spk_no',
                'funding_source',
                'disbursement_slot',
                'collateral',
                'verification_remarks',
            ]);
        });
    }
};
