<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use Tests\TestCase;

final class PermissionConfigTest extends TestCase
{
    public function test_permission_catalog_is_non_empty(): void
    {
        $perms = config('permissions.permissions');
        self::assertIsArray($perms);
        self::assertContains('journals.create', $perms);
        self::assertContains('installments.record', $perms);
        self::assertContains('messages.send', $perms);
        self::assertContains('assistant.use', $perms);
        self::assertContains('assets.view', $perms);
        self::assertContains('period_close.manage', $perms);
        self::assertContains('reports.view', $perms);
        self::assertContains('billing.view', $perms);
        self::assertContains('institutions.manage', $perms);
        self::assertContains('villages.view', $perms);
        self::assertContains('website.view', $perms);
        self::assertContains('website.manage', $perms);
    }

    public function test_system_roles_cover_admin_and_kasir(): void
    {
        $roles = config('permissions.roles');
        self::assertArrayHasKey('admin', $roles);
        self::assertSame(['*'], $roles['admin']['permissions']);
        self::assertContains('installments.record', $roles['kasir']['permissions']);
        self::assertContains('assets.view', $roles['kasir']['permissions']);
        self::assertNotContains('loans.approve', $roles['kasir']['permissions']);
        self::assertNotContains('period_close.manage', $roles['kasir']['permissions']);
        self::assertContains('reports.view', $roles['viewer']['permissions']);
    }

    public function test_request_and_tool_maps_point_to_known_permissions(): void
    {
        $catalog = config('permissions.permissions');
        foreach (config('permissions.request_map') as $permission) {
            self::assertContains($permission, $catalog, "unknown permission in request_map: {$permission}");
        }
    }

    public function test_installment_individual_route_is_in_nav_map(): void
    {
        // nav_map harus memuat '/accounting/journal-entries/installment-individual'
        // agar kasir yang punya 'installments.record' melihat menu di sidebar.
        $navMap = config('permissions.nav_map');
        self::assertIsArray($navMap);
        self::assertArrayHasKey('/accounting/journal-entries/installment-individual', $navMap);
        self::assertSame('installments.record', $navMap['/accounting/journal-entries/installment-individual']);
    }

    public function test_dashboard_service_uses_member_loan_enum_not_individual_loan(): void
    {
        // Bug fix: enum DB hanya izinkan 'member_loan' & 'group_loan'.
        // Query di DashboardService harus pakai 'member_loan', bukan 'individual_loan'.
        $source = file_get_contents(base_path('app/Domain/Dashboard/Services/DashboardService.php'));
        self::assertIsString($source);
        self::assertStringContainsString("'member_loan'", $source);
        self::assertStringNotContainsString("'individual_loan'", $source, 'DashboardService tidak boleh pakai enum invalid individual_loan');
    }
}
