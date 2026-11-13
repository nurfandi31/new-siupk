<?php

declare(strict_types=1);

namespace Tests\Feature\Savings;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Membership\Models\Person;
use App\Domain\Savings\Models\SavingsAccount;
use App\Domain\Savings\Models\SavingsProduct;
use App\Domain\Savings\Models\SavingsTransaction;
use App\Domain\Savings\Services\SavingsModuleManager;
use App\Domain\Savings\Services\SavingsService;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class SavingsPrintTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private SavingsAccount $account;

    private SavingsTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Simpanan',
            'email' => 'savings-print@example.test',
            'username' => 'savings_print_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Koperasi Simpan Pinjam Test',
            'short_name' => 'KSP Test',
            'address' => 'Jl. Simpanan No. 1',
            'phone' => '08123456789',
        ]);

        $unit = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Simpanan',
            'type' => 'village',
            'is_active' => true,
        ]);

        $person = Person::query()->create([
            'public_id' => (string) Str::ulid(),
            'full_name' => 'Anggota Simpanan',
            'national_identity_number' => '3201234567890099',
            'gender' => 'L',
            'phone' => '081200000000',
        ]);

        $member = Member::query()->create([
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $unit->row_id,
            'member_number' => 'AGT-SIMP-001',
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);

        app(SavingsModuleManager::class)->enable((int) $this->testTenant->row_id);

        $product = SavingsProduct::query()->where('code', '1')->firstOrFail();

        $this->account = app(SavingsService::class)->openAccount([
            'member_row_id' => $member->row_id,
            'product_row_id' => $product->row_id,
            'opened_at' => '2026-01-15',
            'opening_amount' => 100000,
        ], (int) $this->user->row_id);

        $this->transaction = SavingsTransaction::query()
            ->where('account_row_id', $this->account->row_id)
            ->where('direction', 'in')
            ->orderBy('row_id')
            ->firstOrFail();
    }

    public function test_savings_documents_and_receipts_render(): void
    {
        $account = (int) $this->account->row_id;
        $transaction = (int) $this->transaction->row_id;

        $this->actingAs($this->user)
            ->get('/simpanan/'.$account.'/cetak/kop')
            ->assertOk()
            ->assertSee($this->account->account_number);

        $this->actingAs($this->user)
            ->get('/simpanan/'.$account.'/cetak/formulir')
            ->assertOk()
            ->assertSee('Anggota Simpanan');

        $this->actingAs($this->user)
            ->get('/simpanan/'.$account.'/cetak/koran?year=2026&month=1')
            ->assertOk()
            ->assertSee($this->account->account_number);

        $this->actingAs($this->user)
            ->get('/simpanan/'.$account.'/cetak/buku/'.$transaction)
            ->assertOk()
            ->assertSee($this->account->account_number);

        $this->actingAs($this->user)
            ->get('/simpanan/'.$account.'/cetak/kwitansi/'.$transaction)
            ->assertOk()
            ->assertSee($this->account->account_number);
    }

    public function test_savings_documents_stream_pdf_and_excel(): void
    {
        $account = (int) $this->account->row_id;

        foreach ([
            '/simpanan/'.$account.'/cetak/kop/pdf',
            '/simpanan/'.$account.'/cetak/formulir/pdf',
            '/simpanan/'.$account.'/cetak/koran/pdf?year=2026&month=1',
        ] as $url) {
            $this->actingAs($this->user)
                ->get($url)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }

        foreach ([
            '/simpanan/'.$account.'/cetak/kop/excel',
            '/simpanan/'.$account.'/cetak/formulir/excel',
            '/simpanan/'.$account.'/cetak/koran/excel?year=2026&month=1',
        ] as $url) {
            $this->actingAs($this->user)
                ->get($url)
                ->assertOk();
        }
    }
}
