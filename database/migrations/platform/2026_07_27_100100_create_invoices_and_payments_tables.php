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
        return Schema::connection((string) config('tenancy.platform_connection', 'platform'));
    }

    public function up(): void
    {
        $schema = $this->schema();

        $schema->create('invoices', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->char('public_id', 26)->unique();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('amount', 19, 2);
            $table->decimal('amount_paid', 19, 2)->default(0);
            $table->char('currency', 3)->default('IDR');
            $table->dateTime('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('description', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['due_at', 'status']);
            $table->foreign('tenant_id')->references('row_id')->on('tenants')->restrictOnDelete();
            $table->foreign('subscription_id')->references('row_id')->on('subscriptions')->nullOnDelete();
            $table->foreign('created_by')->references('row_id')->on('users')->nullOnDelete();
        });

        $schema->create('invoice_payments', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('method', 20);
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('amount', 19, 2);
            $table->dateTime('paid_at')->nullable();
            $table->string('reference', 150)->nullable();
            $table->string('tripay_reference', 100)->nullable()->unique();
            $table->string('tripay_checkout_url', 500)->nullable();
            $table->json('tripay_payload')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('invoice_id')->references('row_id')->on('invoices')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('row_id')->on('tenants')->restrictOnDelete();
            $table->foreign('recorded_by')->references('row_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $schema = $this->schema();
        $schema->dropIfExists('invoice_payments');
        $schema->dropIfExists('invoices');
    }
};
