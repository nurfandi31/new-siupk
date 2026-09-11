<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Services\Reports\LoanDocumentService;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MemberService;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class MemberIdentityPhotoTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private OrganizationUnit $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTestDatabaseFiles();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);
        Storage::fake('public');

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Anggota',
            'email' => 'identity-photo@example.test',
            'username' => 'identity_photo_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Induk',
            'type' => 'village',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_upload_success_stores_file_and_persists_path(): void
    {
        $member = $this->createMember();

        $this->actingAs($this->user)
            ->post('/master-data/members/'.$member->row_id.'/identity-photo', [
                'identity_photo' => UploadedFile::fake()->image('ktp.jpg', 200, 120),
            ])
            ->assertRedirect('/master-data/members/'.$member->row_id)
            ->assertSessionHas('success');

        $member->refresh()->load('person');
        self::assertNotNull($member->person->identity_photo_path);
        self::assertStringStartsWith('identity-photos/'.$member->person->row_id.'/ktp.', $member->person->identity_photo_path);
        Storage::disk('public')->assertExists($member->person->identity_photo_path);
    }

    public function test_upload_rejects_non_image_and_oversized_file(): void
    {
        $member = $this->createMember();

        $this->actingAs($this->user)
            ->post('/master-data/members/'.$member->row_id.'/identity-photo', [
                'identity_photo' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('identity_photo');

        $oversized = UploadedFile::fake()->image('ktp.png', 200, 120)->size(4097);
        $this->actingAs($this->user)
            ->post('/master-data/members/'.$member->row_id.'/identity-photo', [
                'identity_photo' => $oversized,
            ])
            ->assertSessionHasErrors('identity_photo');

        self::assertNull($member->refresh()->person->identity_photo_path);
    }

    public function test_replacing_identity_photo_deletes_old_file(): void
    {
        $member = $this->createMember();

        $this->actingAs($this->user)
            ->post('/master-data/members/'.$member->row_id.'/identity-photo', [
                'identity_photo' => UploadedFile::fake()->image('old.jpg', 200, 120),
            ]);

        $oldPath = (string) $member->refresh()->person->identity_photo_path;

        $this->actingAs($this->user)
            ->post('/master-data/members/'.$member->row_id.'/identity-photo', [
                'identity_photo' => UploadedFile::fake()->image('new.png', 200, 120),
            ]);

        $newPath = (string) $member->refresh()->person->identity_photo_path;

        self::assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_destroy_identity_photo_deletes_file_and_clears_path(): void
    {
        $member = $this->createMember();

        $this->actingAs($this->user)
            ->post('/master-data/members/'.$member->row_id.'/identity-photo', [
                'identity_photo' => UploadedFile::fake()->image('ktp.jpg', 200, 120),
            ]);

        $path = (string) $member->refresh()->person->identity_photo_path;

        $this->actingAs($this->user)
            ->delete('/master-data/members/'.$member->row_id.'/identity-photo')
            ->assertRedirect('/master-data/members/'.$member->row_id)
            ->assertSessionHas('success');

        self::assertNull($member->refresh()->person->identity_photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_upload_requires_member_manage_permission(): void
    {
        $member = $this->createMember();
        $restrictedUser = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Viewer Tanpa Izin',
            'email' => 'identity-viewer@example.test',
            'username' => 'identity_viewer',
            'password' => 'password',
            'status' => 'active',
        ]);

        app(PermissionChecker::class)->ensureSystemRoles();
        app(PermissionChecker::class)->assignRole($restrictedUser, 'viewer');

        $this->actingAs($restrictedUser)
            ->post('/master-data/members/'.$member->row_id.'/identity-photo', [
                'identity_photo' => UploadedFile::fake()->image('ktp.jpg', 200, 120),
            ])
            ->assertForbidden();

        self::assertNull($member->refresh()->person->identity_photo_path);
    }

    private function resetTestDatabaseFiles(): void
    {
        DB::connection('platform')->disconnect();
        DB::connection('tenant')->disconnect();
        usleep(200000);

        foreach ([
            (string) config('database.connections.platform.database'),
            (string) config('database.connections.tenant.database'),
        ] as $database) {
            File::delete($database);
            File::ensureDirectoryExists(dirname($database));
            File::put($database, '');
        }
    }

    public function test_ktp_document_renders_placeholder_without_photo(): void
    {
        $loan = $this->createLoanWithMember();

        $html = $this->renderKtpHtml($loan);

        self::assertStringContainsString('FOTO KTP BELUM DIUNGGAH', $html);
        self::assertStringNotContainsString('data:image', $html);
    }

    public function test_ktp_document_embeds_uploaded_photo(): void
    {
        $member = $this->createMember();
        $this->actingAs($this->user)
            ->post('/master-data/members/'.$member->row_id.'/identity-photo', [
                'identity_photo' => UploadedFile::fake()->image('ktp.jpg', 200, 120),
            ]);

        $loan = $this->createLoanWithMember($member);
        $html = $this->renderKtpHtml($loan);

        self::assertStringContainsString('data:image/jpeg;base64,', $html);
    }

    private function createMember(): Member
    {
        return app(MemberService::class)->create([
            'nik' => '3273010203040001',
            'name' => 'Budi Anggota',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '1990-01-02',
            'phone' => '081234567890',
            'family_card_number' => '3273010203040004',
            'address' => 'Jalan Desa',
            'village_id' => $this->village->row_id,
            'registered_at' => '2026-07-19',
            'status' => 'active',
            'has_guarantor' => false,
            'has_business' => false,
        ], (int) $this->user->row_id);
    }

    private function createLoanWithMember(?Member $member = null): Loan
    {
        $member ??= $this->createMember();

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $productId,
            'sequence_number' => 1,
            'loan_number' => 'PK-KTP-000001',
            'proposed_at' => '2026-02-01',
            'verified_at' => '2026-02-10',
            'approved_at' => '2026-02-15',
            'funded_at' => '2026-02-20',
            'disbursed_at' => null,
            'principal_amount' => 5000000,
            'interest_rate' => 1.5,
            'service_rate_total' => 18.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'status' => 'approved',
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => null,
            'member_row_id' => $member->row_id,
        ]);

        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan->row_id,
            'member_row_id' => $member->row_id,
            'proposed_amount' => 5000000,
            'verified_amount' => 5000000,
            'allocated_amount' => 5000000,
        ]);

        return $loan;
    }

    private function renderKtpHtml(Loan $loan): string
    {
        $payload = app(LoanDocumentService::class)->payload($loan, 'ktp');

        return (string) view('reports.pdf.loan_documents.ktp', $payload)->render();
    }
}
