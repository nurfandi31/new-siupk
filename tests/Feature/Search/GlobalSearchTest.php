<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class GlobalSearchTest extends TestCase
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
            'name' => 'Kasir Search',
            'email' => 'search@example.test',
            'username' => 'search_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $person = Person::query()->create([
            'national_identity_number' => '3201010101010001',
            'full_name' => 'Siti Aminah',
            'gender' => 'P',
            'phone' => '08123456789',
        ]);
        Member::query()->create([
            'person_row_id' => $person->row_id,
            'member_number' => '3201010101010001',
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_search_finds_member_by_name(): void
    {
        $this->actingAs($this->user)
            ->getJson('/search?q=Siti')
            ->assertOk()
            ->assertJsonPath('q', 'Siti')
            ->assertJsonFragment(['title' => 'Siti Aminah']);
    }

    public function test_search_requires_min_length(): void
    {
        $this->actingAs($this->user)
            ->getJson('/search?q=S')
            ->assertOk()
            ->assertJsonPath('groups', []);
    }
}
