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

        $schema->create('organization_profiles', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->string('legal_name', 200);
            $table->string('short_name', 100)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->date('operational_start_date')->nullable();
            $table->timestamps();

            $table->unique('tenant_id', 'uq_org_profiles_tenant');
        });

        $schema->create('organization_units', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('parent_row_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 180);
            $table->string('type', 30);
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_org_units_code');
            $table->index(['tenant_id', 'parent_row_id'], 'ix_org_units_parent');
            $table->foreign(['tenant_id', 'parent_row_id'], 'fk_org_units_parent')
                ->references(['tenant_id', 'row_id'])
                ->on('organization_units')
                ->restrictOnDelete();
        });

        foreach ([
            'business_types' => ['code', 'name'],
            'activity_types' => ['code', 'name'],
            'group_levels' => ['code', 'name'],
            'group_functions' => ['code', 'name'],
        ] as $tableName => $columns) {
            $schema->create($tableName, function (Blueprint $table) use ($columns, $tableName): void {
                $this->addTenantIdentity($table);
                $table->string($columns[0], 50);
                $table->string($columns[1], 150);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['tenant_id', 'code'], 'uq_'.substr($tableName, 0, 20).'_code');
            });
        }

        $schema->create('people', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->char('national_identity_number', 16)->nullable();
            $table->string('family_card_number', 20)->nullable();
            $table->string('full_name', 180);
            $table->char('gender', 1)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'national_identity_number'], 'uq_people_nik');
            $table->index(['tenant_id', 'full_name'], 'ix_people_name');
        });

        $schema->create('members', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->unsignedBigInteger('person_row_id');
            $table->unsignedBigInteger('organization_unit_row_id')->nullable();
            $table->string('member_number', 50);
            $table->date('registered_at');
            $table->string('status', 30)->default('active');
            $table->unsignedBigInteger('registered_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'member_number'], 'uq_members_number');
            $table->index(['tenant_id', 'status'], 'ix_members_status');
            $table->foreign(['tenant_id', 'person_row_id'], 'fk_members_person')
                ->references(['tenant_id', 'row_id'])->on('people')->restrictOnDelete();
            $table->foreign(['tenant_id', 'organization_unit_row_id'], 'fk_members_unit')
                ->references(['tenant_id', 'row_id'])->on('organization_units')->restrictOnDelete();
        });

        $schema->create('member_addresses', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('member_row_id');
            $table->string('type', 30)->default('home');
            $table->text('address');
            $table->string('village_code', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'member_row_id'], 'ix_member_addresses_member');
            $table->foreign(['tenant_id', 'member_row_id'], 'fk_member_addresses_member')
                ->references(['tenant_id', 'row_id'])->on('members')->cascadeOnDelete();
        });

        $schema->create('member_businesses', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('member_row_id');
            $table->unsignedBigInteger('business_type_row_id')->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->date('started_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['tenant_id', 'member_row_id'], 'fk_member_businesses_member')
                ->references(['tenant_id', 'row_id'])->on('members')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'business_type_row_id'], 'fk_member_businesses_type')
                ->references(['tenant_id', 'row_id'])->on('business_types')->restrictOnDelete();
        });

        $schema->create('member_guarantors', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('member_row_id');
            $table->unsignedBigInteger('guarantor_person_row_id');
            $table->string('relationship_type', 50)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'member_row_id'], 'fk_member_guarantors_member')
                ->references(['tenant_id', 'row_id'])->on('members')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'guarantor_person_row_id'], 'fk_member_guarantors_person')
                ->references(['tenant_id', 'row_id'])->on('people')->restrictOnDelete();
        });

        $schema->create('groups', function (Blueprint $table): void {
            $this->addTenantIdentity($table, true);
            $table->unsignedBigInteger('organization_unit_row_id')->nullable();
            $table->unsignedBigInteger('business_type_row_id')->nullable();
            $table->unsignedBigInteger('activity_type_row_id')->nullable();
            $table->unsignedBigInteger('group_level_row_id')->nullable();
            $table->unsignedBigInteger('group_function_row_id')->nullable();
            $table->string('code', 80);
            $table->string('name', 225);
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('established_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'uq_groups_code');
            $table->index(['tenant_id', 'status'], 'ix_groups_status');
            $table->foreign(['tenant_id', 'organization_unit_row_id'], 'fk_groups_unit')
                ->references(['tenant_id', 'row_id'])->on('organization_units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'business_type_row_id'], 'fk_groups_business_type')
                ->references(['tenant_id', 'row_id'])->on('business_types')->restrictOnDelete();
            $table->foreign(['tenant_id', 'activity_type_row_id'], 'fk_groups_activity_type')
                ->references(['tenant_id', 'row_id'])->on('activity_types')->restrictOnDelete();
            $table->foreign(['tenant_id', 'group_level_row_id'], 'fk_groups_level')
                ->references(['tenant_id', 'row_id'])->on('group_levels')->restrictOnDelete();
            $table->foreign(['tenant_id', 'group_function_row_id'], 'fk_groups_function')
                ->references(['tenant_id', 'row_id'])->on('group_functions')->restrictOnDelete();
        });

        $schema->create('group_members', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('group_row_id');
            $table->unsignedBigInteger('member_row_id');
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'group_row_id', 'member_row_id', 'joined_at'], 'uq_group_members_period');
            $table->foreign(['tenant_id', 'group_row_id'], 'fk_group_members_group')
                ->references(['tenant_id', 'row_id'])->on('groups')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'member_row_id'], 'fk_group_members_member')
                ->references(['tenant_id', 'row_id'])->on('members')->restrictOnDelete();
        });

        $schema->create('group_officers', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('group_row_id');
            $table->unsignedBigInteger('member_row_id');
            $table->string('position', 50);
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'group_row_id', 'position'], 'ix_group_officers_position');
            $table->foreign(['tenant_id', 'group_row_id'], 'fk_group_officers_group')
                ->references(['tenant_id', 'row_id'])->on('groups')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'member_row_id'], 'fk_group_officers_member')
                ->references(['tenant_id', 'row_id'])->on('members')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        foreach ([
            'group_officers',
            'group_members',
            'groups',
            'member_guarantors',
            'member_businesses',
            'member_addresses',
            'members',
            'people',
            'group_functions',
            'group_levels',
            'activity_types',
            'business_types',
            'organization_units',
            'organization_profiles',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
