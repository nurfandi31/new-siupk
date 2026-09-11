<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EmailLoginTest extends TestCase
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

    public function test_user_can_login_using_email(): void
    {
        $user = $this->createUser([
            'email' => 'login@example.test',
            'password' => 'secret-password',
        ]);

        $this->post('/login', [
            'identifier' => 'login@example.test',
            'password' => 'secret-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_login_with_wrong_password_fails(): void
    {
        $this->createUser([
            'email' => 'login@example.test',
            'password' => 'secret-password',
        ]);

        $this->from('/login')
            ->post('/login', [
                'identifier' => 'login@example.test',
                'password' => 'wrong',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_unknown_identifier_is_rejected(): void
    {
        $this->from('/login')
            ->post('/login', [
                'identifier' => 'nobody@example.test',
                'password' => 'whatever',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'name' => 'Email User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'user_'.Str::lower(Str::random(12)),
            'password' => 'password',
            'status' => 'active',
        ], $attributes));
    }
}
