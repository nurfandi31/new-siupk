<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Documents\Services\SignatureTemplateService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanCommittee;
use App\Domain\Lending\Services\Reports\LoanDocumentService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberGuarantor;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LoanDocumentTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Group $group;

    private OrganizationUnit $village;

    private int $productId;

    private int $memberId;

    private int $loanId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Dokumen',
            'email' => 'loan-doc@example.test',
            'username' => 'loan_doc_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Koperasi Maju Bersama',
            'short_name' => 'KMB',
            'address' => 'Jl. Raya No. 1, Kota Test',
            'phone' => '08123456789',
            'email' => 'info@kmb.test',
        ]);

        $this->village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Makmur',
            'type' => 'village',
            'is_active' => true,
        ]);
        $district = OrganizationUnit::query()->create([
            'id' => 2,
            'code' => 'K001',
            'name' => 'Kecamatan Sentosa',
            'type' => 'district',
            'parent_row_id' => null,
            'is_active' => true,
        ]);
        $this->village->parent_row_id = $district->row_id;
        $this->village->save();

        $this->group = Group::query()->create([
            'code' => 'KLP-001',
            'name' => 'Kelompok Tani Maju',
            'status' => 'active',
            'organization_unit_row_id' => 1,
            'address' => 'Dusun Krajan RT 02',
        ]);

        $person = Person::query()->create([
            'public_id' => (string) Str::ulid(),
            'full_name' => 'Budi Santoso',
            'national_identity_number' => '3201234567890001',
            'gender' => 'm',
        ]);
        $guarantorPerson = Person::query()->create([
            'public_id' => (string) Str::ulid(),
            'full_name' => 'Siti Aminah',
            'national_identity_number' => '3201234567890002',
            'gender' => 'f',
        ]);
        $member = Member::query()->create([
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => 1,
            'member_number' => 'AGT-001',
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);
        MemberGuarantor::query()->create([
            'member_row_id' => $member->row_id,
            'guarantor_person_row_id' => $guarantorPerson->row_id,
            'relationship_type' => 'family',
            'valid_from' => '2026-01-01',
        ]);
        $this->memberId = (int) $member->row_id;

        GroupOfficer::query()->create([
            'group_row_id' => $this->group->row_id,
            'member_row_id' => $this->memberId,
            'position' => 'chair',
            'started_at' => '2026-01-01',
        ]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');
        $this->loanId = 1;
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_resolve_throws_for_unknown_document(): void
    {
        $service = app(LoanDocumentService::class);

        $this->expectException(\DomainException::class);
        $service->resolve('unknown_document');
    }

    public function test_resolve_returns_meta_for_each_document(): void
    {
        $service = app(LoanDocumentService::class);
        $keys = ['cover_proposal', 'pengajuan_kredit', 'profil_kelompok', 'susunan_pengurus', 'daftar_pemanfaat', 'pernyataan_tanggung_renteng', 'check', 'anggota', 'ktp', 'catatan_bimbingan', 'rekomendasi_kredit', 'ba_musyawarah', 'surat_verifikasi', 'surat_kelayakan', 'form_verifikasi', 'form_verifikasi_anggota', 'daftar_hadir_verifikasi', 'cover_pencairan', 'spk', 'berita_acara_pencairan', 'ba_pendanaan', 'rencana_angsuran', 'kartu_angsuran_anggota', 'pemberitahuan_desa', 'peserta_asuransi', 'tanda_terima', 'kuitansi_pencairan', 'kuitansi_anggota', 'tagihan', 'surat_ahli_waris', 'surat_kuasa', 'tanggung_renteng_kematian', 'iptw', 'rekening_koran', 'pernyataan_peminjam', 'daftar_hadir_pencairan'];

        foreach ($keys as $key) {
            $meta = $service->resolve($key);
            self::assertSame($key, $meta['key']);
            self::assertNotEmpty($meta['view']);
            self::assertContains($meta['orientation'], ['portrait', 'landscape']);
            self::assertContains($meta['stage'], ['proposal', 'verification', 'disbursement']);
        }
    }

    public function test_token_replacer_builds_complete_map_for_active_loan(): void
    {
        $loan = $this->seedLoan('active');
        $service = app(LoanDocumentService::class);

        $tokens = $service->tokenReplacer($loan);

        // Lembaga tokens harus terisi (OrganizationProfile diisi di setUp)
        self::assertSame('Koperasi Maju Bersama', $tokens['{nama_lembaga}']);
        self::assertSame('KMB', $tokens['{nama_singkat}']);
        self::assertStringContainsString('Jl. Raya No. 1', $tokens['{alamat_lembaga}']);
        self::assertSame('08123456789', $tokens['{telepon_lembaga}']);

        // Kelompok tokens
        self::assertSame('Kelompok Tani Maju', $tokens['{nama_kelompok}']);
        self::assertSame('KLP-001', $tokens['{kd_kelompok}']);
        self::assertSame('Desa Makmur', $tokens['{desa}']);
        self::assertSame('Kecamatan Sentosa', $tokens['{kecamatan}']);

        // Pinjaman tokens
        self::assertSame($loan->loan_number, $tokens['{no_pinjaman}']);
        self::assertStringContainsString('SPP', $tokens['{produk}']);
        self::assertStringContainsString('Rp', $tokens['{alokasi}']);
        self::assertSame('12 bulan', $tokens['{jangka}']);

        // Pemanfaat tokens (anggota pertama)
        self::assertSame('Budi Santoso', $tokens['{pemanfaat_nama}']);
        self::assertSame('3201234567890001', $tokens['{pemanfaat_nik}']);
        self::assertSame('Siti Aminah', $tokens['{pemanfaat_penjamin}']);
    }

    public function test_available_documents_filter_by_status(): void
    {
        $service = app(LoanDocumentService::class);

        // draft → hanya proposal (10 dokumen: 7 iter-1/2 + anggota, ktp, catatan_bimbingan)
        $draftLoan = $this->seedLoan('draft');
        $draftDocs = $service->availableDocuments($draftLoan);
        self::assertCount(10, $draftDocs);
        foreach ($draftDocs as $doc) {
            self::assertSame('proposal', $doc['stage']);
        }

        // verified → proposal + verification (10 + 7 = 17)
        $verifiedLoan = $this->seedLoan('verified');
        $verifiedDocs = $service->availableDocuments($verifiedLoan);
        self::assertCount(17, $verifiedDocs);
        $verifiedStages = array_unique(array_column($verifiedDocs, 'stage'));
        self::assertContains('proposal', $verifiedStages);
        self::assertContains('verification', $verifiedStages);

        // waiting → + disbursement (10 + 7 + 19 = 36, semua)
        $waitingLoan = $this->seedLoan('waiting');
        $waitingDocs = $service->availableDocuments($waitingLoan);
        self::assertCount(36, $waitingDocs);

        // active → semua 36
        $activeLoan = $this->seedLoan('active');
        $activeDocs = $service->availableDocuments($activeLoan);
        self::assertCount(36, $activeDocs);

        // rescheduled → tidak ada (karena tidak masuk dalam STAGE_ALLOWED_STATUS)
        $rescheduledLoan = $this->seedLoan('rescheduled');
        $rescheduledDocs = $service->availableDocuments($rescheduledLoan);
        self::assertCount(0, $rescheduledDocs);
    }

    public function test_payload_contains_blade_render_shape(): void
    {
        $loan = $this->seedLoan('active');
        $service = app(LoanDocumentService::class);

        $payload = $service->payload($loan, 'pengajuan_kredit');

        self::assertArrayHasKey('identity', $payload);
        self::assertArrayHasKey('loan', $payload);
        self::assertArrayHasKey('group', $payload);
        self::assertArrayHasKey('committee', $payload);
        self::assertArrayHasKey('beneficiaries', $payload);
        self::assertArrayHasKey('document', $payload);
        self::assertArrayHasKey('tokens', $payload);
        self::assertArrayHasKey('signature', $payload);

        self::assertSame('pengajuan_kredit', $payload['document']['key']);
        self::assertSame('Surat Pengajuan Kredit', $payload['document']['label']);
        self::assertArrayHasKey('{nama_kelompok}', $payload['tokens']);
        self::assertSame('KLP-001', $payload['group']['code']);
    }

    public function test_signature_template_with_tokens_is_replaced_in_pdf_payload(): void
    {
        // Set template dengan token
        app(SignatureTemplateService::class)->save([
            'proposal' => '<p>Disusun Bagian Kredit &mdash; {produk} &mdash; {nama_kelompok}</p>',
        ]);

        $loan = $this->seedLoan('active');
        $service = app(LoanDocumentService::class);

        $payload = $service->payload($loan, 'pengajuan_kredit');

        // Signature harus berisi HTML dengan token ter-replace
        self::assertNotEmpty($payload['signature']);
        self::assertStringContainsString('SPP', $payload['signature']);
        self::assertStringContainsString('Kelompok Tani Maju', $payload['signature']);
        self::assertStringNotContainsString('{produk}', $payload['signature']);
    }

    public function test_endpoint_returns_pdf_for_known_document(): void
    {
        $loan = $this->seedLoan('active');

        $response = $this->actingAs($this->user)
            ->get("/lending/loans/{$loan->row_id}/documents/pengajuan_kredit");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_endpoint_returns_422_for_unknown_document(): void
    {
        $loan = $this->seedLoan('active');

        $response = $this->actingAs($this->user)
            ->get("/lending/loans/{$loan->row_id}/documents/xyz_unknown");

        $response->assertStatus(422);
    }

    public function test_endpoint_returns_404_for_missing_loan(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/lending/loans/999999/documents/cover_proposal');

        $response->assertNotFound();
    }

    private function seedLoan(string $status): Loan
    {
        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => $this->loanId++,
            'loan_number' => 'PK-DOC-'.str_pad((string) ($this->loanId * 1000), 6, '0', STR_PAD_LEFT),
            'proposed_at' => '2026-02-01',
            'verified_at' => $status === 'draft' ? null : '2026-02-10',
            'approved_at' => in_array($status, ['waiting', 'approved', 'active', 'disbursed', 'completed']) ? '2026-02-15' : null,
            'funded_at' => in_array($status, ['waiting', 'approved', 'active', 'disbursed', 'completed']) ? '2026-02-20' : null,
            'disbursed_at' => in_array($status, ['active', 'disbursed', 'completed']) ? '2026-02-25' : null,
            'principal_amount' => 5000000,
            'interest_rate' => 1.5,
            'service_rate_total' => 18.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'verification_notes' => 'Kelompok aktif dan memiliki simpanan rutin.',
            'status' => $status,
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => $this->group->row_id,
            'member_row_id' => null,
        ]);

        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan->row_id,
            'member_row_id' => $this->memberId,
            'proposed_amount' => 5000000,
            'verified_amount' => 5000000,
            'allocated_amount' => 5000000,
        ]);

        if (in_array($status, ['waiting', 'approved', 'active', 'disbursed', 'completed'], true)) {
            LoanCommittee::query()->create([
                'loan_row_id' => $loan->row_id,
                'position' => 'chair',
                'member_row_id' => $this->memberId,
                'member_name_snapshot' => 'Budi Santoso',
                'snapshot_at' => '2026-02-20',
            ]);
        }

        return $loan;
    }
}
