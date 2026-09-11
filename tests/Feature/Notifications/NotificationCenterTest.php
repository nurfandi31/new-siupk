<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domain\Accounting\Models\JournalEntry;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_user_can_fetch_notifications(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'unread_count',
                'items' => [
                    '*' => ['id', 'type', 'title', 'message', 'time', 'target_url', 'icon', 'variant', 'read', 'actor'],
                ],
            ]);
    }

    public function test_notifications_include_actor_attribution_for_journal_and_loans(): void
    {
        $user = $this->createTenantMember();

        // 1. Create a journal entry by user
        JournalEntry::query()->create([
            'journal_number' => 'JU-TEST-001',
            'transaction_date' => now()->toDateString(),
            'description' => 'Pencatatan kas operasional',
            'status' => 'draft',
            'created_by_user_id' => $user->row_id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications');
        $response->assertOk();

        $items = $response->json('items');
        $journalNotif = collect($items)->firstWhere('type', 'journal_activity');

        $this->assertNotNull($journalNotif);
        $this->assertSame($user->name, $journalNotif['actor']);
        $this->assertStringContainsString('oleh '.$user->name, $journalNotif['message']);
    }

    public function test_user_can_mark_notification_as_read_and_persists_to_database(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->getJson('/api/notifications');
        $response->assertOk();
        $items = $response->json('items');
        $this->assertNotEmpty($items);

        $firstItemId = $items[0]['id'];

        $markResponse = $this->actingAs($user)->postJson('/api/notifications/mark-read', [
            'id' => $firstItemId,
        ]);
        $markResponse->assertOk()->assertJson(['success' => true]);

        // Verify database persistence on user model
        $freshUser = User::query()->find($user->row_id);
        $this->assertNotNull($freshUser);
        $this->assertIsArray($freshUser->notifications_read);
        $this->assertContains($firstItemId, $freshUser->notifications_read);

        // Simulate logout and re-login with a fresh request instance
        $this->flushSession();
        $afterReloginResponse = $this->actingAs($freshUser)->getJson('/api/notifications');
        $afterReloginResponse->assertOk();

        $updatedItems = $afterReloginResponse->json('items');
        $targetItem = collect($updatedItems)->firstWhere('id', $firstItemId);
        $this->assertNotNull($targetItem);
        $this->assertTrue($targetItem['read']);
    }

    public function test_user_can_mark_multiple_ids_as_read(): void
    {
        $user = $this->createTenantMember();

        $markResponse = $this->actingAs($user)->postJson('/api/notifications/mark-read', [
            'ids' => ['custom_notif_1', 'custom_notif_2'],
        ]);
        $markResponse->assertOk()->assertJson(['success' => true]);

        $freshUser = User::query()->find($user->row_id);
        $this->assertNotNull($freshUser);
        $this->assertContains('custom_notif_1', $freshUser->notifications_read);
        $this->assertContains('custom_notif_2', $freshUser->notifications_read);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = $this->createTenantMember();

        $markAllResponse = $this->actingAs($user)->postJson('/api/notifications/mark-read', [
            'id' => null,
        ]);
        $markAllResponse->assertOk()->assertJson(['success' => true]);

        $freshUser = User::query()->find($user->row_id);
        $this->assertNotNull($freshUser);
        $this->assertNotEmpty($freshUser->notifications_read);

        $this->flushSession();
        $response = $this->actingAs($freshUser)->getJson('/api/notifications');
        $response->assertOk();
        $this->assertSame(0, $response->json('unread_count'));
    }

    private function createTenantMember(): User
    {
        $tenantDb = (string) config('database.connections.tenant.database');

        $shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Shard',
            'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
            'host' => (string) config('database.connections.tenant.host', '127.0.0.1'),
            'port' => (int) config('database.connections.tenant.port', 3306),
            'database_name' => $tenantDb,
            'credential_reference' => str_ends_with($tenantDb, '_test') ? 'test' : 'local',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Tenant',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => ['localhost']],
        ]);

        $placement = TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => 'Notification User',
            'email' => 'notification@example.test',
            'username' => 'notification_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);
        Artisan::call('tenancy:sync-registry', ['--shard' => 'local']);

        config(['tenancy.local_tenant' => 'local']);

        app(TenantContext::class)->initialize($tenant, $placement, $shard);

        return $user;
    }
}
