<?php

declare(strict_types=1);

namespace Tests\Feature\Desktop;

use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class DesktopReadOnlyGuardTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();

        // Register a test mutation route to test offline guard
        Route::post('/test/mutation', fn () => response()->json(['status' => 'success']))->middleware('web');
        Route::put('/test/mutation', fn () => response()->json(['status' => 'success']))->middleware('web');
        Route::delete('/test/mutation', fn () => response()->json(['status' => 'success']))->middleware('web');
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_safe_read_requests_pass_in_offline_mode(): void
    {
        Config::set('desktop.enabled', true);
        Config::set('desktop.offline', true);

        $response = $this->get('/up');
        $response->assertOk();

        $syncStatus = $this->getJson('/desktop/sync/status');
        $syncStatus->assertOk();
    }

    public function test_mutations_are_blocked_when_offline_header_is_sent(): void
    {
        Config::set('desktop.enabled', true);

        $response = $this->withHeader('X-Client-Offline', 'true')
            ->postJson('/test/mutation', ['name' => 'John Doe']);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'OFFLINE_READ_ONLY_GUARD');

        $deleteResponse = $this->withHeader('X-Client-Offline', 'true')
            ->deleteJson('/test/mutation');

        $deleteResponse->assertStatus(403)
            ->assertJsonPath('code', 'OFFLINE_READ_ONLY_GUARD');
    }

    public function test_mutations_are_blocked_in_desktop_offline_mode(): void
    {
        Config::set('desktop.enabled', true);
        Config::set('desktop.offline', true);

        $response = $this->postJson('/test/mutation', ['name' => 'Test Item']);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'OFFLINE_READ_ONLY_GUARD');
    }

    public function test_auth_login_mutation_is_whitelisted_offline(): void
    {
        Config::set('desktop.enabled', true);
        Config::set('desktop.offline', true);

        // Login POST request should pass through the offline guard (even if credentials fail validation)
        $response = $this->post('/login', [
            'username' => 'invalid_user',
            'password' => 'invalid_pass',
        ]);

        // Guard did not return 403 OFFLINE_READ_ONLY_GUARD; it redirects with auth validation error
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_inertia_middleware_shares_desktop_props(): void
    {
        Config::set('desktop.enabled', true);
        Config::set('desktop.offline', false);
        Config::set('desktop.server.url', 'https://app.siupk.id');

        $response = $this->get('/login');
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('desktop')
                ->where('desktop.is_desktop', true)
                ->where('desktop.server_url', 'https://app.siupk.id')
            );
    }
}
