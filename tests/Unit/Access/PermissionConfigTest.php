<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Http\Requests\Accounting\JournalEntryRequest;
use App\Http\Requests\Accounting\LoanInstallmentJournalRequest;
use App\Http\Requests\Website\SitePageRequest;
use App\Http\Requests\Website\SitePostRequest;
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
        foreach (config('permissions.tool_map') as $tool => $permission) {
            self::assertContains($permission, $catalog, "unknown permission for tool {$tool}");
        }
        foreach (config('permissions.nav_map') as $path => $permission) {
            self::assertContains($permission, $catalog, "unknown permission in nav_map for {$path}");
        }

        self::assertSame('journals.create', config('permissions.request_map.'.JournalEntryRequest::class));
        self::assertSame('installments.record', config('permissions.request_map.'.LoanInstallmentJournalRequest::class));
        self::assertSame('website.manage', config('permissions.request_map.'.SitePostRequest::class));
        self::assertSame('website.manage', config('permissions.request_map.'.SitePageRequest::class));
        self::assertSame('journals.create', config('permissions.tool_map.create_journal_entry'));
        self::assertSame('assets.view', config('permissions.tool_map.search_assets'));
        self::assertSame('assets.view', config('permissions.nav_map./accounting/assets'));
    }
}
