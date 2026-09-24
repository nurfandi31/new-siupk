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

    private function addTenantIdentity(Blueprint $table, bool $publicId = false): void
    {
        $table->bigIncrements('row_id');
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('id');

        if ($publicId) {
            $table->char('public_id', 26)->unique();
        }

        $table->unique(['tenant_id', 'row_id']);
        $table->unique(['tenant_id', 'id']);
        $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
    }

    public function up(): void
    {
        $schema = $this->schema();

        $schema->create('supervisor_notes', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);

            // platform User.id (pengawas/supervisor) — tidak ber-FK ke tabel user tenant.
            $table->unsignedBigInteger('supervisor_user_id');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->string('category', 40); // keuangan | operasional | kepatuhan | strategis
            $table->string('subject', 200);
            $table->text('content');
            $table->string('status', 30)->default('draft'); // draft | submitted | acknowledged
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by_user_id')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->text('acknowledgment_note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'period_year', 'period_month'], 'ix_supervisor_notes_period');
            $table->index(['tenant_id', 'status'], 'ix_supervisor_notes_status');
            $table->index(['tenant_id', 'category'], 'ix_supervisor_notes_category');
            $table->index(['tenant_id', 'supervisor_user_id'], 'ix_supervisor_notes_supervisor');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('supervisor_notes');
    }
};
