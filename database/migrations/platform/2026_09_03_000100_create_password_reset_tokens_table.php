<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection((string) config('tenancy.platform_connection', 'platform'))
            ->create('password_reset_tokens', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('phone', 20)->index();
                $table->unsignedBigInteger('user_row_id');
                $table->string('token');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('created_at')->nullable();
            });
    }

    public function down(): void
    {
        Schema::connection((string) config('tenancy.platform_connection', 'platform'))
            ->dropIfExists('password_reset_tokens');
    }
};
