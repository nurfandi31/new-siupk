<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'uq_users_phone';

    public function up(): void
    {
        $connection = (string) config('tenancy.platform_connection', 'platform');
        $schema = Schema::connection($connection);

        if (! $schema->hasColumn('users', 'phone') || $schema->hasIndex('users', self::INDEX_NAME)) {
            return;
        }

        $existingPhones = $schema->getConnection()->table('users')
            ->selectRaw('phone, count(*) as total')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->havingRaw('count(*) > 1')
            ->pluck('total', 'phone');

        if ($existingPhones->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot require users.phone: duplicate WhatsApp phone values exist. Resolve rows before migrating: %s',
                $existingPhones->keys()->implode(', '),
            ));
        }

        // Backfill pengguna tanpa nomor WhatsApp dengan sentinel unik per baris.
        //
        // Sentinel TIDAK bisa menerima OTP: PhoneNormalizer::normalize()
        // menghasilkan string non-numerik, WhatsAppPasswordOtpService::issueOtp()
        // dan ForgotPasswordController::sendOtp() menolak format selain
        // ^628\d{7,12}$, dan UpdateProfileRequest mewajibkan regex
        // ^(?:\+?62|0)8\d{7,12}$ saat user memperbarui nomornya sendiri.
        // Nilai deterministik per baris (hash public_id) agar migrasi idempoten,
        // dan dihitung di PHP agar portabel untuk MySQL maupun SQLite.
        $usersWithoutPhone = $schema->getConnection()->table('users')
            ->whereNull('phone')
            ->orWhere('phone', '')
            ->get(['row_id', 'public_id']);

        foreach ($usersWithoutPhone as $user) {
            $schema->getConnection()->table('users')
                ->where('row_id', $user->row_id)
                ->update([
                    'phone' => 'pending-wa-'.substr(md5((string) $user->public_id), 0, 8),
                ]);
        }

        $remaining = $schema->getConnection()->table('users')
            ->whereNull('phone')
            ->orWhere('phone', '')
            ->count();

        if ($remaining > 0) {
            throw new RuntimeException(sprintf(
                'Cannot require users.phone: %d user(s) still have no phone after backfill attempt.',
                $remaining,
            ));
        }

        if (Schema::connection($connection)->getConnection()->getDriverName() === 'sqlite') {
            Schema::connection($connection)->table('users', function (Blueprint $table): void {
                $table->unique('phone', self::INDEX_NAME);
            });

            return;
        }

        Schema::connection($connection)->table('users', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable(false)->unique(self::INDEX_NAME)->change();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection((string) config('tenancy.platform_connection', 'platform'));

        if (! $schema->hasIndex('users', self::INDEX_NAME)) {
            return;
        }

        $schema->table('users', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });

        if ($schema->getConnection()->getDriverName() !== 'sqlite') {
            $schema->table('users', function (Blueprint $table): void {
                $table->string('phone', 20)->nullable()->change();
            });
        }
    }
};
