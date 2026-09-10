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

        $schema->create('budgets', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('name', 180);
            $table->string('status', 30)->default('draft');
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'fiscal_year', 'status'], 'ix_budgets_year_status');
        });

        $schema->create('budget_lines', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('budget_row_id');
            $table->unsignedBigInteger('account_row_id');
            $table->unsignedBigInteger('organization_unit_row_id')->nullable();
            $table->unsignedTinyInteger('fiscal_month');
            $table->decimal('amount', 19, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'budget_row_id', 'account_row_id', 'organization_unit_row_id', 'fiscal_month'],
                'uq_budget_line_dimension'
            );
            $table->foreign(['tenant_id', 'budget_row_id'], 'fk_budget_lines_budget')
                ->references(['tenant_id', 'row_id'])->on('budgets')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'account_row_id'], 'fk_budget_lines_account')
                ->references(['tenant_id', 'row_id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'organization_unit_row_id'], 'fk_budget_lines_unit')
                ->references(['tenant_id', 'row_id'])->on('organization_units')->restrictOnDelete();
        });

        $schema->create('asset_categories', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->string('code', 50);
            $table->string('name', 150);
            $table->unsignedInteger('default_useful_life_months')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_asset_categories_code');
        });

        $schema->create('assets', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->unsignedBigInteger('organization_unit_row_id')->nullable();
            $table->unsignedBigInteger('asset_category_row_id')->nullable();
            $table->string('asset_code', 80)->nullable();
            $table->string('name', 180);
            $table->date('purchased_at')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_cost', 19, 2)->default(0);
            $table->unsignedInteger('useful_life_months')->nullable();
            $table->string('status', 30)->default('good');
            $table->date('validated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'asset_code'], 'uq_assets_code');
            $table->index(['tenant_id', 'status'], 'ix_assets_status');
            $table->foreign(['tenant_id', 'organization_unit_row_id'], 'fk_assets_unit')
                ->references(['tenant_id', 'row_id'])->on('organization_units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'asset_category_row_id'], 'fk_assets_category')
                ->references(['tenant_id', 'row_id'])->on('asset_categories')->restrictOnDelete();
        });

        $schema->create('asset_status_histories', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('asset_row_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->dateTime('changed_at');
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['tenant_id', 'asset_row_id'], 'fk_asset_status_asset')
                ->references(['tenant_id', 'row_id'])->on('assets')->cascadeOnDelete();
        });

        $schema->create('documents', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->string('documentable_type', 100);
            $table->unsignedBigInteger('documentable_row_id');
            $table->string('document_type', 80)->nullable();
            $table->string('storage_disk', 50);
            $table->string('storage_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'documentable_type', 'documentable_row_id'], 'ix_documents_owner');
        });

        $schema->create('tenant_settings', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('key', 150);
            $table->longText('value')->nullable();
            $table->string('value_type', 30)->default('string');
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'key'], 'uq_tenant_settings_key');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->cascadeOnDelete();
        });

        $schema->create('roles', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->string('code', 80);
            $table->string('name', 150);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_roles_code');
        });

        $schema->create('user_roles', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('platform_user_id');
            $table->unsignedBigInteger('role_row_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'platform_user_id', 'role_row_id'], 'uq_user_roles_assignment');
            $table->foreign(['tenant_id', 'role_row_id'], 'fk_user_roles_role')
                ->references(['tenant_id', 'row_id'])->on('roles')->cascadeOnDelete();
        });

        $schema->create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 80);
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_row_id')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'row_id']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'auditable_type', 'auditable_row_id'], 'ix_audit_target');
            $table->index(['tenant_id', 'occurred_at'], 'ix_audit_time');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        foreach ([
            'audit_logs',
            'user_roles',
            'roles',
            'tenant_settings',
            'documents',
            'asset_status_histories',
            'assets',
            'asset_categories',
            'budget_lines',
            'budgets',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
