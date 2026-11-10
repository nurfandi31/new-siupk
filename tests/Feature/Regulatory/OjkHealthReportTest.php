<?php

declare(strict_types=1);

namespace Tests\Feature\Regulatory;

use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class OjkHealthReportTest extends TestCase
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
            'name' => 'Petugas OJK',
            'email' => 'ojk-health@example.test',
            'username' => 'ojk_health_user',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_health_page_and_pdf_render_for_pojk_41(): void
    {
        $this->actingAs($this->user)
            ->get('/regulatory/ojk/health?year=2026&month=7&framework=41')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Regulatory/Ojk/HealthAssessment')
                ->where('framework', '41')
                ->has('rows', 7)
                ->has('parameters', 8)
            );

        $this->actingAs($this->user)
            ->get('/regulatory/ojk/health/pdf?year=2026&month=7&framework=41')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_health_page_and_pdf_render_for_pojk_19(): void
    {
        $this->actingAs($this->user)
            ->get('/regulatory/ojk/health?year=2026&month=7&framework=19')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Regulatory/Ojk/HealthAssessment')
                ->where('framework', '19')
                ->has('rows', 6)
                ->has('parameters', 8)
            );

        $this->actingAs($this->user)
            ->get('/regulatory/ojk/health/pdf?year=2026&month=7&framework=19')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
