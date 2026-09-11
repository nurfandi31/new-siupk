<?php

declare(strict_types=1);

namespace Tests\Feature\Province;

use App\Models\Platform\DatabaseShard;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProvinceConsolidatedReportTest extends TestCase
{
    private DatabaseShard $shard;

    private User $provinceSupervisor;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        if (! filter_var(env('RUN_TENANCY_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_TENANCY_INTEGRATION_TESTS=true to run MySQL tenancy tests.');
        }

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        $this->shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'shard_prov_test_'.Str::random(5),
            'name' => 'Shard Test Province',
            'driver' => (string) config('database.connections.tenant.driver', 'sqlite'),
            'host' => '127.0.0.1',
            'port' => 3306,
            'database_name' => ':memory:',
            'credential_reference' => 'test',
            'status' => 'active',
            'province_code' => '32',
            'province_name' => 'Jawa Barat',
        ]);

        $this->provinceSupervisor = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Supervisor Provinsi JB',
            'username' => 'prov_sup_'.Str::random(5),
            'email' => 'prov_sup_'.Str::random(5).'@test.local',
            'password' => bcrypt('password'),
            'is_province_user' => true,
            'province_code' => '32',
            'province_name' => 'Jawa Barat',
            'status' => 'active',
        ]);

        $this->regularUser = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Regular User',
            'username' => 'reg_user_'.Str::random(5),
            'email' => 'reg_'.Str::random(5).'@test.local',
            'password' => bcrypt('password'),
            'is_province_user' => false,
            'status' => 'active',
        ]);
    }

    public function test_regular_user_cannot_access_province_dashboard(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/province/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_province_supervisor_can_access_dashboard_and_reports(): void
    {
        $response = $this->actingAs($this->provinceSupervisor)->get('/province/dashboard');
        $response->assertOk();

        $response = $this->actingAs($this->provinceSupervisor)->get('/province/reports/pack');
        $response->assertOk();

        $response = $this->actingAs($this->provinceSupervisor)->get('/province/reports/balance-sheet');
        $response->assertOk();

        $response = $this->actingAs($this->provinceSupervisor)->get('/province/reports/income-statement');
        $response->assertOk();

        $response = $this->actingAs($this->provinceSupervisor)->get('/province/reports/cash-flow');
        $response->assertOk();

        $response = $this->actingAs($this->provinceSupervisor)->get('/province/reports/equity-changes');
        $response->assertOk();

        $response = $this->actingAs($this->provinceSupervisor)->get('/province/reports/calk');
        $response->assertOk();
    }

    public function test_province_supervisor_can_stream_5_statement_pdf_pack(): void
    {
        $response = $this->actingAs($this->provinceSupervisor)->get('/province/reports/pdf');
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
