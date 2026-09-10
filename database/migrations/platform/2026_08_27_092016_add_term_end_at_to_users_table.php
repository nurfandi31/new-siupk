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
            $table->date('term_end_at')->nullable()->after('appointed_at');
        });
    }

    public function down(): void
    {
        Schema::connection('platform')->table('users', function (Blueprint $table): void {
            $table->dropColumn('term_end_at');
        });
    }
};
