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

    private function addTenantIdentity(Blueprint $table): void
    {
        $table->bigIncrements('row_id');
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('id');
        $table->unique(['tenant_id', 'row_id']);
        $table->unique(['tenant_id', 'id']);
        $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
    }

    public function up(): void
    {
        $this->schema()->create('member_user_links', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('user_row_id');
            $table->unsignedBigInteger('member_row_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_row_id'], 'uq_member_user_links_user');
            $table->index(['tenant_id', 'member_row_id'], 'ix_member_user_links_member');
            $table->foreign(['tenant_id', 'member_row_id'], 'fk_member_user_links_member')
                ->references(['tenant_id', 'row_id'])->on('members')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('member_user_links');
    }
};
