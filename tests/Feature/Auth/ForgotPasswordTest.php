<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Models\Platform\WhatsappPlatformInstance;
use App\Models\User;
use App\Services\PhoneNormalizer;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ForgotPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        DB::connection('platform')->disconnect();
        DB::connection('tenant')->disconnect();

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);
    }

    public function test_guest_can_view_forgot_password_page(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_forgot_password_route_is_registered_with_native_path(): void
    {
        self::assertSame('/forgot-password', route('password.request', absolute: false));
        $this->get('/forgot-password')->assertOk();
    }

    public function test_send_otp_with_valid_username_redirects_to_otp_form_even_when_gateway_fails(): void
    {
        $user = $this->createTenantUser();

        $this->post(route('password.otp.send'), ['identifier' => $user->username])
            ->assertRedirect(route('password.otp.form'))
            ->assertSessionHas('info');

        $this->get(route('password.otp.form'))->assertRedirect(route('password.request'));
    }

    public function test_otp_is_sent_through_platform_instance_without_tenant_context(): void
    {
        $user = $this->createTenantUser();
        WhatsappPlatformInstance::query()->create([
            'name' => 'OTP Platform',
            'instance_name' => 'platform-wa-otp',
            'status' => 'open',
            'is_default' => true,
            'is_active' => true,
        ]);

        config()->set('services.wa_gateway.base_url', 'https://wa-gateway.test');
        config()->set('services.wa_gateway.api_key', 'test-api-key');

        Http::fake([
            'https://wa-gateway.test/send-message' => Http::response(['success' => true]),
        ]);

        $this->post(route('password.otp.send'), ['identifier' => $user->username])
            ->assertRedirect(route('password.otp.form'));

        $this->assertFalse(app(TenantContext::class)->isInitialized());

        Http::assertSent(fn ($request) => $request->url() === 'https://wa-gateway.test/send-message'
            && $request['instance'] === 'platform-wa-otp'
            && $request['number'] === '6281234567890'
            && str_contains((string) $request['text'], 'Kode reset password siupk'));

        $this->assertDatabaseHas('password_reset_tokens', [
            'user_row_id' => $user->row_id,
            'phone' => '6281234567890',
        ], 'platform');
    }

    public function test_otp_falls_back_to_tenant_gateway_when_platform_is_empty(): void
    {
        $user = $this->createTenantUser();

        config()->set('services.wa_gateway.base_url', 'https://wa-gateway.test');
        config()->set('services.wa_gateway.api_key', 'tenant-api-key');

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);

        DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => $user->tenant_id,
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-'.Str::lower(Str::random(8)),
            'name' => 'Test Tenant',
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('tenant_settings')->insert([
            'tenant_id' => $user->tenant_id,
            'key' => 'whatsapp.is_enabled',
            'value' => '1',
            'value_type' => 'bool',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('whatsapp_instances')->insert([
            'tenant_id' => $user->tenant_id,
            'id' => 1,
            'name' => 'Tenant WA',
            'instance_name' => 'tenant-wa-otp',
            'status' => 'open',
            'is_default' => 1,
            'is_active' => 1,
            'daily_limit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://wa-gateway.test/send-message' => Http::response(['success' => true]),
        ]);

        $this->post(route('password.otp.send'), ['identifier' => $user->username])
            ->assertRedirect(route('password.otp.form'));

        Http::assertSent(fn ($request) => $request->url() === 'https://wa-gateway.test/send-message'
            && $request['instance'] === 'tenant-wa-otp'
            && $request['number'] === '6281234567890');

        $this->assertDatabaseHas('password_reset_tokens', [
            'user_row_id' => $user->row_id,
            'phone' => '6281234567890',
        ], 'platform');
    }

    public function test_send_otp_with_unknown_identifier_has_same_response(): void
    {
        $this->post(route('password.otp.send'), ['identifier' => 'unknown-user'])
            ->assertRedirect(route('password.otp.form'))
            ->assertSessionHas('info');

        $this->get(route('password.otp.form'))->assertRedirect(route('password.request'));
    }

    public function test_wrong_otp_is_rejected_and_valid_otp_redirects_to_reset_form(): void
    {
        $user = $this->createTenantUser();
        $this->issueOtpDirectly($user, '123456');

        $this->withSession($this->otpSessionData($user))
            ->post(route('password.otp.verify'), ['otp' => '000001'])
            ->assertRedirect()
            ->assertSessionHasErrors('otp');

        $this->updateOtp($user, '123456');

        $this->withSession($this->otpSessionData($user))
            ->post(route('password.otp.verify'), ['otp' => '123456'])
            ->assertRedirect(route('password.reset.form'));

        $this->get(route('password.reset.form'))->assertOk();
    }

    public function test_wrong_grant_token_clears_session_and_redirects_to_request_form(): void
    {
        $user = $this->createTenantUser();
        $this->issueOtpDirectly($user, '123456');

        $this->withSession($this->otpSessionData($user))
            ->post(route('password.otp.verify'), ['otp' => '123456'])
            ->assertRedirect(route('password.reset.form'));

        $this->post(route('password.reset'), [
            'grant_token' => Str::random(64),
            'password' => 'password-baru-2026',
            'password_confirmation' => 'password-baru-2026',
        ])->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('otp');

        $this->get(route('password.reset.form'))->assertRedirect(route('password.request'));
    }

    public function test_valid_grant_token_resets_user_password(): void
    {
        $user = $this->createTenantUser();
        $this->issueOtpDirectly($user, '123456');

        $this->withSession($this->otpSessionData($user))
            ->post(route('password.otp.verify'), ['otp' => '123456'])
            ->assertRedirect(route('password.reset.form'));

        $grantToken = session('password_reset.grant_token');

        $this->post(route('password.reset'), [
            'grant_token' => $grantToken,
            'password' => 'password-baru-2026',
            'password_confirmation' => 'password-baru-2026',
        ])->assertRedirect(route('login'));

        $user->refresh();

        $this->assertTrue(Hash::check('password-baru-2026', $user->password));
        $this->assertGuest();
    }

    public function test_sixth_send_otp_request_is_rate_limited(): void
    {
        $user = $this->createTenantUser();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.otp.send'), ['identifier' => $user->username])
                ->assertRedirect(route('password.otp.form'));
        }

        $this->post(route('password.otp.send'), ['identifier' => $user->username])
            ->assertStatus(429);
    }

    private function createTenantUser(array $attributes = []): User
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-'.Str::lower(Str::random(8)),
            'name' => 'Test Tenant',
            'status' => 'active',
        ]);

        $shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'shard-'.Str::lower(Str::random(8)),
            'name' => 'Test Shard',
            'driver' => 'sqlite',
            'host' => 'localhost',
            'port' => 0,
            'database_name' => database_path('tenant_test.sqlite'),
            'credential_reference' => 'test',
            'status' => 'active',
        ]);

        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        return User::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'name' => 'Test User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'phone' => '6281234567890',
            'username' => 'user_'.Str::lower(Str::random(12)),
            'password' => 'old-password',
            'status' => 'active',
            'tenant_id' => $tenant->row_id,
        ], $attributes));
    }

    private function updateOtp(User $user, string $otp): void
    {
        DB::connection('platform')->table('password_reset_tokens')
            ->where('user_row_id', $user->row_id)
            ->update([
                'token' => Hash::make($otp),
                'attempts' => 0,
            ]);
    }

    private function otpSessionData(User $user): array
    {
        return [
            'password_reset' => [
                'phone' => app(PhoneNormalizer::class)->normalize((string) $user->phone),
                'user_id' => $user->row_id,
                'resends' => 0,
            ],
        ];
    }

    private function issueOtpDirectly(User $user, string $otp): void
    {
        DB::connection('platform')->table('password_reset_tokens')->insert([
            'phone' => app(PhoneNormalizer::class)->normalize((string) $user->phone),
            'user_row_id' => $user->row_id,
            'token' => Hash::make($otp),
            'attempts' => 0,
            'created_at' => now(),
        ]);
    }
}
