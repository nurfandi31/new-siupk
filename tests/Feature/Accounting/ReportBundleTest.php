<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;
use ZipArchive;

final class ReportBundleTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private User $restrictedUser;

    private Account $cash;

    private Account $idleAccount;

    private Account $equity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = $this->createUser('bundle@example.test');
        $this->restrictedUser = $this->createUser('restricted-bundle@example.test');
        app(PermissionChecker::class)->assignRole($this->restrictedUser, 'viewer');

        $this->seedAccounts();

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'BUMDesma LKD Bundle',
            'short_name' => 'LKD Bundle',
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteReportBundles();
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_authorized_user_can_download_report_bundle(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/accounting/reports/bundle/pdf?year=2026&month=07');

        if (! $response->isSuccessful()) {
            fwrite(STDERR, (string) $response->getContent());
        }

        $response->assertOk();
        self::assertStringContainsString('zip', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('bundle-laporan-lkd-bundle-2026-07.pdf.zip', (string) $response->headers->get('Content-Disposition'));

        $zip = new ZipArchive;
        self::assertTrue(in_array($zip->open($response->getFile()->getPathname()), [ZipArchive::ER_OK, true], true));
        self::assertSame(13, $zip->numFiles);

        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = (string) $zip->getNameIndex($index);
        }

        self::assertContains('README-bundle.txt', $entries);
        self::assertSame(12, count(array_filter($entries, fn (string $entry): bool => str_ends_with($entry, '.pdf'))));
        self::assertContains('buku-besar-1.1.01.01-2026-07.pdf', $entries);
        self::assertNotContains('buku-besar-9.1.01.01-2026-07.pdf', $entries);

        $manifestStream = $zip->getStream('README-bundle.txt');
        self::assertNotFalse($manifestStream);
        $manifest = (string) stream_get_contents($manifestStream);
        fclose($manifestStream);
        self::assertStringContainsString('Akun tanpa mutasi dilewati: 1', $manifest);
        self::assertStringContainsString('Buku Besar 1.1.01.01 · Kas Tunai', $manifest);
        $zip->close();
    }

    public function test_viewer_role_with_report_permission_can_download_bundle(): void
    {
        $this->actingAs($this->restrictedUser)
            ->get('/accounting/reports/bundle/pdf?year=2026&month=12')
            ->assertOk();
    }

    private function createUser(string $email): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Bundle User',
            'email' => $email,
            'username' => Str::before($email, '@'),
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    private function seedAccounts(): void
    {
        $accountCreatedAt = Carbon::parse('2026-07-01')->startOfDay();

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);

        $this->cash = Account::query()->create([
            'code' => '1.1.01.01',
            'name' => 'Kas Tunai',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $this->idleAccount = Account::query()->create([
            'code' => '9.1.01.01',
            'name' => 'Akun Tanpa Mutasi',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $this->equity = Account::query()->create([
            'code' => '3.1.01.01',
            'name' => 'Modal',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 4,
            'is_postable' => false,
            'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);

        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-10',
            'sequence_number' => 1,
            'description' => 'Setor modal',
            'status' => 'draft',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $this->cash->row_id,
            'debit' => '100000.00',
            'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $this->equity->row_id,
            'debit' => '0.00',
            'credit' => '100000.00',
        ]);
        app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);
    }

    private function deleteReportBundles(): void
    {
        $directory = storage_path('app/report-bundles');
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $path) {
            @unlink($path);
        }
    }
}
