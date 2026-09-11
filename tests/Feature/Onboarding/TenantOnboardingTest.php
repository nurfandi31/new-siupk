<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Onboarding\Services\TenantOnboardingService;
use App\Models\User;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantOnboardingTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private TenantOnboardingService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->service = app(TenantOnboardingService::class);
        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Onboarding User',
            'email' => 'onboarding@example.test',
            'username' => 'onboarding_user',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    public function test_can_save_balanced_opening_balances(): void
    {
        $kas = Account::query()->create([
            'code' => '1.1.01.01',
            'name' => 'Kas Kantor Test',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $modal = Account::query()->create([
            'code' => '3.1.01.01',
            'name' => 'Modal Test',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $entry = $this->service->saveOpeningBalances([
            ['account_row_id' => (int) $kas->row_id, 'debit' => 5000000, 'credit' => 0],
            ['account_row_id' => (int) $modal->row_id, 'debit' => 0, 'credit' => 5000000],
        ], '2026-01-01', (int) $this->user->row_id);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertEquals('posted', $entry->status);
        $this->assertEquals(5000000, (float) $entry->lines()->sum('debit'));
        $this->assertCount(2, $entry->lines);
    }

    public function test_throws_exception_when_opening_balances_unbalanced(): void
    {
        $kas = Account::query()->create([
            'code' => '1.1.01.02',
            'name' => 'Kas Test 2',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Saldo awal tidak imbang');

        $this->service->saveOpeningBalances([
            ['account_row_id' => (int) $kas->row_id, 'debit' => 5000000, 'credit' => 0],
        ], '2026-01-01', (int) $this->user->row_id);
    }

    public function test_can_import_active_loans_with_fifo_payments(): void
    {
        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $product = LoanProduct::query()->first();

        $person = Person::query()->create([
            'national_identity_number' => '3515011203900099',
            'full_name' => 'Budi Tester',
            'gender' => 'L',
        ]);

        $member = Member::query()->create([
            'person_row_id' => $person->row_id,
            'member_number' => 'MEM-001',
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);

        $csvContent = "nomor_spk,nik_anggota,nama_kelompok,tanggal_pencairan,plafon_pinjaman,bunga_persen,jangka_bulan,akumulasi_pokok_dibayar,akumulasi_bunga_dibayar\n".
            'SPK-TEST-001,3515011203900099,,2026-01-01,12000000,10,12,3000000,300000';

        $file = UploadedFile::fake()->createWithContent('active_loans.csv', $csvContent);

        $result = $this->service->importActiveLoans($file, (int) $this->user->row_id);

        $this->assertEquals(1, $result['imported']);
        $this->assertEmpty($result['errors']);

        $loan = Loan::query()->where('loan_number', 'SPK-TEST-001')->first();
        $this->assertNotNull($loan);
        $this->assertEquals('disbursed', $loan->status);

        $installments = LoanInstallment::query()->where('loan_row_id', $loan->row_id)->orderBy('installment_number')->get();
        $this->assertCount(12, $installments);

        // First 3 installments (1,000,000 principal each) should be paid
        $this->assertEquals('paid', $installments[0]->status);
        $this->assertEquals('paid', $installments[1]->status);
        $this->assertEquals('paid', $installments[2]->status);
        $this->assertEquals('pending', $installments[3]->status);
    }
}
