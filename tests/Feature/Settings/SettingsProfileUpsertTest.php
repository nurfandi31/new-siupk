<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domain\Lending\Services\CollectibilityConfigService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

/**
 * Regresi: `organization_profiles.id` bukan AUTO_INCREMENT (memakai sequence
 * per tenant), sehingga upsert pertama harus tetap mengisi `id`.
 */
final class SettingsProfileUpsertTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Pengaturan',
            'email' => 'settings@example.test',
            'username' => 'settings_user',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_identity_update_creates_profile_with_sequence_id(): void
    {
        self::assertNull(OrganizationProfile::query()->first());

        $this->actingAs($this->user)
            ->put('/settings/identity', [
                'legal_name' => 'Koperasi Baru',
                'short_name' => 'KB',
                'district_name' => 'Sukamaju',
                'regency_name' => 'Cianjur',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertRedirect();

        $profile = OrganizationProfile::query()->firstOrFail();
        self::assertSame(1, (int) $profile->id);
        self::assertSame('Koperasi Baru', $profile->legal_name);
        self::assertSame('Sukamaju', $profile->district_name);
        self::assertSame('Cianjur', $profile->regency_name);

        // Update kedua tidak boleh mengubah id maupun membuat baris baru.
        $this->actingAs($this->user)
            ->put('/settings/identity', [
                'legal_name' => 'Koperasi Baru Sejahtera',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertRedirect();

        self::assertSame(1, OrganizationProfile::query()->count());
        self::assertSame('Koperasi Baru Sejahtera', OrganizationProfile::query()->firstOrFail()->legal_name);
    }

    public function test_kolek_update_creates_profile_with_sequence_id(): void
    {
        self::assertNull(OrganizationProfile::query()->first());

        $this->actingAs($this->user)
            ->put('/settings/sop/kolek', [
                'nama_kolek1' => 'Lancar',
                'pros_kolek1' => '0.5',
                'durasi1' => '3',
                'satuan1' => 'bulan',
            ])
            ->assertRedirect();

        $profile = OrganizationProfile::query()->firstOrFail();
        self::assertSame(1, (int) $profile->id);

        $rows = app(CollectibilityConfigService::class)->all();
        self::assertSame('Lancar', $rows[0]['nama']);
        self::assertSame('0.5', $rows[0]['prosentase']);
    }
}
