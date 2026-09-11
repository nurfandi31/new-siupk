<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProfilePhotoTest extends TestCase
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

    public function test_user_can_view_profile_edit_page(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->get('/profile?tab=photo');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Edit')
                ->has('profile')
                ->has('account')
                ->where('photoUrl', null)
                ->where('auth.user.photo_url', null));
    }

    public function test_user_can_upload_profile_photo_and_persists(): void
    {
        Storage::fake('public');

        $user = $this->createTenantMember();
        $fakeImage = UploadedFile::fake()->image('profile_pic.jpg', 200, 200);

        $response = $this->actingAs($user)->post('/profile/photo', [
            'photo' => $fakeImage,
        ]);

        $response->assertRedirect('/profile?tab=photo');
        $response->assertSessionHas('success');

        $freshUser = User::query()->find($user->row_id);
        $this->assertNotNull($freshUser->photo_path);
        $this->assertStringStartsWith('users/'.$user->row_id.'/photo.', $freshUser->photo_path);

        Storage::disk('public')->assertExists($freshUser->photo_path);
    }

    public function test_uploaded_photo_can_be_served_via_storage_route(): void
    {
        $testFileName = 'users/test_sample/photo.jpg';
        $testFileDir = storage_path('app/public/users/test_sample');
        if (! is_dir($testFileDir)) {
            mkdir($testFileDir, 0777, true);
        }

        file_put_contents(storage_path('app/public/'.$testFileName), 'fake-jpg-binary-content');

        try {
            $response = $this->get('/storage/'.$testFileName);

            $response->assertOk();
            $this->assertStringContainsString('fake-jpg-binary-content', $response->streamedContent());
        } finally {
            if (file_exists(storage_path('app/public/'.$testFileName))) {
                unlink(storage_path('app/public/'.$testFileName));
            }
            if (is_dir($testFileDir)) {
                rmdir($testFileDir);
            }
        }
    }

    public function test_non_existent_storage_path_returns_404(): void
    {
        $response = $this->get('/storage/users/999999/non_existent_photo.jpg');

        $response->assertNotFound();
    }

    public function test_storage_route_blocks_directory_traversal(): void
    {
        $response = $this->get('/storage/../../routes/web.php');

        $response->assertNotFound();
    }

    public function test_user_can_delete_profile_photo(): void
    {
        Storage::fake('public');

        $user = $this->createTenantMember();
        $fakeImage = UploadedFile::fake()->image('profile_pic.jpg', 200, 200);

        $this->actingAs($user)->post('/profile/photo', ['photo' => $fakeImage]);
        $freshUser = User::query()->find($user->row_id);
        $photoPath = $freshUser->photo_path;
        $this->assertNotNull($photoPath);

        $deleteResponse = $this->actingAs($freshUser)->delete('/profile/photo');
        $deleteResponse->assertRedirect('/profile?tab=photo');

        $finalUser = User::query()->find($user->row_id);
        $this->assertNull($finalUser->photo_path);
        Storage::disk('public')->assertMissing($photoPath);
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
            'name' => 'Profile Test User',
            'email' => 'profile_test@example.test',
            'username' => 'profile_test_user',
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
