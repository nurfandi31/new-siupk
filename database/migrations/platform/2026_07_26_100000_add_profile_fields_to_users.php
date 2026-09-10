<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('platform')->table('users', function (Blueprint $table): void {
            $table->char('nik', 16)->nullable()->after('name');
            $table->string('initials', 10)->nullable()->after('nik');
            $table->string('birth_place', 100)->nullable()->after('initials');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->text('address')->nullable()->after('birth_date');
            $table->string('education', 20)->nullable()->after('address');
            $table->date('appointed_at')->nullable()->after('education');
            $table->string('photo_path', 500)->nullable()->after('appointed_at');

            $table->index('nik', 'ix_users_nik');
        });
    }

    public function down(): void
    {
        Schema::connection('platform')->table('users', function (Blueprint $table): void {
            $table->dropIndex('ix_users_nik');
            $table->dropColumn([
                'nik',
                'initials',
                'birth_place',
                'birth_date',
                'address',
                'education',
                'appointed_at',
                'photo_path',
            ]);
        });
    }
};
