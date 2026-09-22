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

    public function test_individual_documents_are_registered_with_individual_stage(): void
    {
        $service = app(LoanDocumentService::class);
        $keys = [
            'analisis_keputusan_kredit',
            'surat_pemberitahuan',
            'pengikat_diri_penjamin',
            'surat_pernyataan_suami',
        ];
        foreach ($keys as $key) {
            $meta = $service->resolve($key);
            self::assertSame($key, $meta['key']);
            self::assertNotEmpty($meta['view']);
            self::assertStringStartsWith('reports.pdf.loan_documents.', $meta['view']);
            self::assertContains($meta['orientation'], ['portrait', 'landscape']);
            self::assertStringStartsWith('individual_', $meta['stage']);
        }
    }

    public function test_individual_documents_not_available_for_group_loan(): void
    {
        $service = app(LoanDocumentService::class);
        $groupLoan = $this->seedLoan('active');

        $available = $service->availableDocuments($groupLoan);
        foreach ($available as $doc) {
            self::assertStringStartsNotWith('individual_', $doc['stage']);
        }

        // Total tidak termasuk dokumen individual
        $countIndividualKeys = 0;
        foreach ($available as $doc) {
            if (in_array($doc['key'], ['analisis_keputusan_kredit', 'surat_pemberitahuan', 'pengikat_diri_penjamin', 'surat_pernyataan_suami'], true)) {
                $countIndividualKeys++;
            }
        }
        self::assertSame(0, $countIndividualKeys);
    }

    /**
     * Render payload (PDF stream) untuk 4 dokumen individu baru dan memastikan
     * payload valid + tidak ada error Blade.
     */
    public function test_individual_documents_payload_renders_for_member_loan(): void
    {
        $service = app(LoanDocumentService::class);
        $loan = $this->seedMemberLoan('active');

        foreach (['analisis_keputusan_kredit', 'surat_pemberitahuan', 'pengikat_diri_penjamin', 'surat_pernyataan_suami'] as $key) {
            $payload = $service->payload($loan, $key);
            self::assertSame($key, $payload['document']['key']);
            self::assertArrayHasKey('profile', $payload);
            self::assertArrayHasKey('loan_obj', $payload);
            self::assertArrayHasKey('installments', $payload);
            // Identitas harus terisi (OrganizationProfile sudah dibuat di setUp)
            self::assertSame('Koperasi Maju Bersama', $payload['identity']['legal_name']);
        }
    }

    public function test_individual_token_replacer_includes_penjamin_and_spouse_tokens(): void
    {
        $service = app(LoanDocumentService::class);
        $loan = $this->seedMemberLoan('active');

        $tokens = $service->tokenReplacer($loan);
        self::assertSame('Budi Santoso', $tokens['{peminjam_nama}']);
        self::assertSame('3201234567890001', $tokens['{peminjam_nik}']);
        self::assertSame('Siti Aminah', $tokens['{penjamin_nama}']);
        self::assertSame('3201234567890002', $tokens['{penjamin_nik}']);
        // Aliases suami → penjamin (paritas pacuan)
        self::assertSame('Siti Aminah', $tokens['{suami_nama}']);
        self::assertSame('3201234567890002', $tokens['{suami_nik}']);
    }

    public function test_individual_documents_endpoint_returns_pdf(): void
    {
        $loan = $this->seedMemberLoan('active');

        foreach (['analisis_keputusan_kredit', 'surat_pemberitahuan', 'pengikat_diri_penjamin', 'surat_pernyataan_suami'] as $key) {
            $response = $this->actingAs($this->user)
                ->get("/lending/loans/{$loan->row_id}/documents/{$key}");

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
        }
    }

    /**
     * Pinjaman kelompok tidak boleh menampilkan dokumen individual (security).
     */
    public function test_individual_documents_endpoint_rejects_group_loan(): void
    {
        $groupLoan = $this->seedLoan('active');

        $response = $this->actingAs($this->user)
            ->get("/lending/loans/{$groupLoan->row_id}/documents/surat_pernyataan_suami");

        $response->assertStatus(422);
    }

    /**
     * Paritas: 8 blade alias kelompok harus render tanpa error Blade dan tidak
     * menampilkan placeholder kosong "{{ '' }}" — paritas dengan pacuan.
     */
    public function test_alias_documents_have_no_empty_placeholders(): void
    {
        $loan = $this->seedLoan('active');
        $loan->load('beneficiaries.member.person', 'beneficiaries.member.address', 'beneficiaries.member.village');
        $service = app(LoanDocumentService::class);

        $bladeKeys = [
            'kuitansi_pencairan',
            'kuitansi_anggota',
            'berita_acara_pencairan',
            'kartu_angsuran_anggota',
            'daftar_pemanfaat',
            'pernyataan_tanggung_renteng',
            'spk',
            'peserta_asuransi',
        ];

        foreach ($bladeKeys as $key) {
            $payload = $service->payload($loan, $key);
            // Render via Blade engine manual
            $html = view($payload['document']['view'], $payload)->render();
            // Harus tidak mengandung placeholder kosong
            self::assertStringNotContainsString(
                "{{ '' }}",
                $html,
                "Blade '$key' masih mengandung placeholder kosong (paritas pacuan)."
            );
            // Harus tidak error Blade (html > 200 chars untuk sanity)
            self::assertGreaterThan(200, strlen($html), "Blade '$key' menghasilkan HTML terlalu pendek.");
        }
    }

    /**
     * Token baru untuk direktur (manager_nik, pengadilan_negeri, dll.) harus
     * ter-resolve di tokenReplacer() — dibutuhkan oleh SPK, kuitansi, dll.
     */
    public function test_new_officer_tokens_resolve_in_token_replacer(): void
    {
        DB::connection('tenant')->table('organization_profiles')->where('id', 1)->update([
            'manager_nik' => '3201234567899999',
            'manager_position' => 'Direktur Utama',
            'manager_address' => 'Jl. Manager No. 1',
            'kepala_desa_name' => 'H. Ahmad',
            'kepala_desa_nip' => '196801011990031001',
            'court_of_jurisdiction' => 'Pengadilan Negeri Bandung',
            'institution_level_1' => 'Lembaga',
            'institution_level_2' => 'Pengurus',
            'institution_level_3' => 'Pengelola',
        ]);

        $loan = $this->seedLoan('active');
        $service = app(LoanDocumentService::class);
        $tokens = $service->tokenReplacer($loan);

        self::assertSame('3201234567899999', $tokens['{kepala_lembaga_nik}']);
        self::assertSame('Direktur Utama', $tokens['{kepala_lembaga_jabatan}']);
        self::assertSame('Jl. Manager No. 1', $tokens['{kepala_lembaga_alamat}']);
        self::assertSame('H. Ahmad', $tokens['{kades}']);
        self::assertSame('196801011990031001', $tokens['{nip_kades}']);
        self::assertSame('Pengadilan Negeri Bandung', $tokens['{pengadilan_negeri}']);
        self::assertSame('Lembaga', $tokens['{sebutan_level_1}']);
        self::assertSame('Pengelola', $tokens['{sebutan_level_3}']);
    }

    /**
     * Beneficiary block harus expose phone, address, village, gender, birth_date
     * untuk dokumen BA Pencairan, Daftar Pemanfaat, Kartu Angsuran.
     */
    public function test_beneficiary_block_exposes_extended_profile_fields(): void
    {
        DB::connection('tenant')->table('people')->where('row_id', 1)->update([
            'birth_place' => 'Bandung',
            'birth_date' => '1985-05-15',
            'gender' => 'f',
            'phone' => '081234567890',
        ]);

        $loan = $this->seedLoan('active');
        $service = app(LoanDocumentService::class);
        $payload = $service->payload($loan, 'berita_acara_pencairan');

        $b = $payload['beneficiaries'][0] ?? null;
        self::assertNotNull($b);
        self::assertSame('Bandung', $b['birth_place']);
        self::assertSame('1985-05-15', $b['birth_date']);
        self::assertSame('f', $b['gender']);
        self::assertSame('081234567890', $b['phone']);
    }

    /**
     * Group block harus expose business_type, activity_type, level, function,
     * established_at, phone untuk dokumen BA Pencairan.
     */
    public function test_group_block_exposes_classification_fields(): void
    {
        // Setup business type, activity type, level, function
        $this->createGroupClassifications();

        DB::connection('tenant')->table('groups')->where('row_id', 1)->update([
            'business_type_row_id' => 1,
            'activity_type_row_id' => 1,
            'group_level_row_id' => 1,
            'group_function_row_id' => 1,
            'phone' => '022-1234567',
            'established_at' => '2018-03-20',
        ]);

        $loan = $this->seedLoan('active');
        $service = app(LoanDocumentService::class);
        $payload = $service->payload($loan, 'berita_acara_pencairan');

        self::assertSame('Pertanian', $payload['group']['business_type']);
        self::assertSame('Simpan Pinjam', $payload['group']['activity_type']);
        self::assertSame('Madya', $payload['group']['level']);
        self::assertSame('Produktif', $payload['group']['function']);
        self::assertSame('022-1234567', $payload['group']['phone']);
        self::assertSame('2018-03-20', $payload['group']['established_at']);
        self::assertSame('20 Maret 2018', $payload['group']['established_label']);
    }

    /**
     * Render HTML 4 dokumen individu baru (analisa, SP2K, pengikat penjamin,
     * pernyataan suami) dan cek paritas konten — string kunci pacuan muncul
     * dan placeholder Blade kosong sudah terisi.
     */
    public function test_individual_documents_html_content_parity_with_pacuan(): void
    {
        DB::connection('tenant')->table('people')->where('row_id', 1)->update([
            'birth_place' => 'Bandung',
            'birth_date' => '1985-05-15',
            'gender' => 'm',
            'phone' => '081234567890',
        ]);
        $loan = $this->seedMemberLoan('active');
        $loan->load('borrower.member.person', 'borrower.member.guarantor.person', 'borrower.member.address', 'borrower.member.village');
        $service = app(LoanDocumentService::class);

        // 1. Analisa Keputusan Kredit — cek section I-IV pacuan muncul
        $payload = $service->payload($loan, 'analisis_keputusan_kredit');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('ANALISA DAN KEPUTUSAN KREDIT', $html);
        self::assertStringContainsString('I. DATA POKOK PEMINJAM', $html);
        self::assertStringContainsString('II. PERMOHONAN PINJAMAN', $html);
        self::assertStringContainsString('III. PERHITUNGAN KREDIT', $html);
        self::assertStringContainsString('IV. REKOMENDASI PEMBERIAN KREDIT', $html);
        self::assertStringContainsString('BUDI SANTOSO', $html); // strtoupper borrower
        self::assertStringContainsString('3201234567890001', $html); // NIK
        self::assertStringContainsString('SITI AMINAH', $html); // penjamin name
        self::assertStringNotContainsString("{{ '' }}", $html);

        // 2. SP2K — cek header + 8 poin fasilitas kredit
        $payload = $service->payload($loan, 'surat_pemberitahuan');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('Surat Persetujuan Perjanjian Kredit', $html);
        self::assertStringContainsString('Nomor', $html);
        self::assertStringContainsString('Sifat', $html);
        self::assertStringContainsString('Perihal', $html);
        self::assertStringContainsString('Fasilitas Kredit', $html);
        self::assertStringContainsString('Jumlah Plafon Kredit', $html);
        self::assertStringContainsString('Jangka Waktu Kredit', $html);
        self::assertStringContainsString('Jenis Kredit', $html);
        self::assertStringContainsString('Suku Jasa Kredit', $html);
        self::assertStringContainsString('Cara Penarikan', $html);
        self::assertStringContainsString('Cara Pembayaran', $html);
        self::assertStringContainsString('Cara Pengikat Kredit', $html);
        self::assertStringContainsString('Syarat Lainnya', $html);
        self::assertStringNotContainsString("{{ '' }}", $html);

        // 3. Pengikat Diri Penjamin — cek poin 1-2 + nama penjamin
        $payload = $service->payload($loan, 'pengikat_diri_penjamin');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('SURAT PERNYATAAN', $html);
        self::assertStringContainsString('PENGIKAT DIRI SEBAGAI PENJAMIN', $html);
        self::assertStringContainsString('Saya <b>menyetujui dan menjamin</b>', $html);
        self::assertStringContainsString('SITI AMINAH', $html);
        self::assertStringContainsString('3201234567890002', $html); // penjamin NIK
        self::assertStringContainsString('tidak memenuhi kewajibannya', $html);
        self::assertStringNotContainsString("{{ '' }}", $html);

        // 4. Surat Pernyataan Suami/Istri — cek ucapkan (terbilang) + NIK suami = penjamin
        $payload = $service->payload($loan, 'surat_pernyataan_suami');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('SURAT PERNYATAAN', $html);
        self::assertStringContainsString('PERSETUJUAN SUAMI', $html);
        self::assertStringContainsString('SITI AMINAH', $html); // spouse = guarantor
        self::assertStringContainsString('3201234567890002', $html);
        self::assertStringContainsString('BUDI SANTOSO', $html);
        self::assertStringContainsString('Rupiah', $html); // ucapkan suffix
        self::assertStringContainsString('Delapan Juta', $html); // 8000000 → delapan juta
        self::assertStringNotContainsString("{{ '' }}", $html);
    }

    /**
     * Render HTML 9 blade alias kelompok dan cek paritas konten pacuan — string
     * kunci muncul dan placeholder kosong sudah terisi semua.
     */
    public function test_alias_documents_html_content_parity_with_pacuan(): void
    {
        // Seed data profil kelompok + klasifikasi
        $this->createGroupClassifications();
        DB::connection('tenant')->table('people')->where('row_id', 1)->update([
            'birth_place' => 'Bandung',
            'birth_date' => '1985-05-15',
            'gender' => 'L',
            'phone' => '081234567890',
        ]);
        DB::connection('tenant')->table('groups')->where('row_id', 1)->update([
            'business_type_row_id' => 1,
            'activity_type_row_id' => 1,
            'group_level_row_id' => 1,
            'group_function_row_id' => 1,
            'phone' => '022-1234567',
            'established_at' => '2018-03-20',
        ]);
        DB::connection('tenant')->table('organization_profiles')->where('id', 1)->update([
            'manager_nik' => '3201234567899999',
            'manager_name' => 'Drs. H. Sudirman',
            'manager_title' => 'Direktur Utama',
            'manager_position' => 'Direktur Utama',
            'manager_address' => 'Jl. Manager No. 1',
            'kepala_desa_name' => 'H. Ahmad',
            'kepala_desa_nip' => '196801011990031001',
            'court_of_jurisdiction' => 'Pengadilan Negeri Bandung',
        ]);

        $loan = $this->seedLoan('active');
        $loan->load('beneficiaries.member.person', 'beneficiaries.member.address', 'beneficiaries.member.village');
        $service = app(LoanDocumentService::class);

        // SPK — PIHAK PERTAMA + Pasal 2 + Pengadilan Negeri
        $payload = $service->payload($loan, 'spk');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('selanjutnya disebut', $html);
        self::assertStringContainsString('Direktur Utama', $html);
        self::assertStringContainsString('3201234567899999', $html); // manager NIK
        self::assertStringContainsString('Jl. Manager No. 1', $html); // manager address
        self::assertStringContainsString('Pengadilan Negeri Bandung', $html);
        self::assertStringContainsString('lima juta', $html); // ucapkan alokasi 5.000.000 (lowercase)
        self::assertStringNotContainsString("{{ '' }}", $html);

        // Kuitansi Pencairan — "Telah Diterima Dari" + ucapkan + 3 kolom TTD
        $payload = $service->payload($loan, 'kuitansi_pencairan');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('Telah Diterima Dari', $html);
        self::assertStringContainsString('Uang Sebanyak', $html);
        self::assertStringContainsString('rupiah', $html);
        self::assertStringContainsString('Setuju Dibayarkan', $html);
        self::assertStringContainsString('Dikeluarkan Oleh', $html);
        self::assertStringContainsString('Diterima Oleh', $html);
        self::assertStringNotContainsString("{{ '' }}", $html);

        // Kuitansi Anggota — "Telah Diterima Dari" per anggota + ucapkan
        $payload = $service->payload($loan, 'kuitansi_anggota');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('Telah Diterima Dari', $html);
        self::assertStringContainsString('Budi Santoso', $html);
        self::assertStringContainsString('Rupiah', $html); // ucapkan suffix
        self::assertStringNotContainsString("{{ '' }}", $html);

        // BA Pencairan — 16 field profil lengkap + direktur + per-anggota HP
        $payload = $service->payload($loan, 'berita_acara_pencairan');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('Berita Acara Pencairan', $html);
        self::assertStringContainsString('Kantor Koperasi Maju Bersama', $html); // tempat
        self::assertStringContainsString('Pertanian', $html); // business type
        self::assertStringContainsString('Simpan Pinjam', $html); // activity type
        self::assertStringContainsString('Madya', $html); // level
        self::assertStringContainsString('Produktif', $html); // function
        self::assertStringContainsString('20 Maret 2018', $html); // established_at label
        self::assertStringContainsString('022-1234567', $html); // group phone
        self::assertStringContainsString('Ketua Kelompok', $html); // TTD pihak kelompok
        self::assertStringNotContainsString("{{ '' }}", $html);

        // Kartu Angsuran per Anggota — telpon + Lembaga + suffix bulan + catatan
        $payload = $service->payload($loan, 'kartu_angsuran_anggota');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('022-1234567', $html); // telpon kelompok
        self::assertStringContainsString('bulan', $html); // suffix
        self::assertStringContainsString('Koperasi Maju Bersama', $html); // identitas catatan
        self::assertStringContainsString('Budi Santoso', $html); // TTD nama
        self::assertStringNotContainsString("{{ '' }}", $html);

        // Daftar Pemanfaat — JK + Usia + sistem angsuran
        $payload = $service->payload($loan, 'daftar_pemanfaat');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('Bulanan', $html); // principal_frequency
        self::assertStringContainsString('L</td>', $html); // gender (L = Laki-laki label singkat)
        self::assertMatchesRegularExpression('/\d{2}<\/td>/', $html); // usia (numeric 2-digit)
        self::assertStringNotContainsString("{{ '' }}", $html);

        // Pernyataan Tanggung Renteng — Kades + JK
        $payload = $service->payload($loan, 'pernyataan_tanggung_renteng');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('H. Ahmad', $html); // kades
        self::assertStringContainsString('L</td>', $html); // JK per anggota
        self::assertStringNotContainsString("{{ '' }}", $html);

        // Peserta Asuransi — Desa + Jangka + TTL per anggota + manager Lembaga
        $payload = $service->payload($loan, 'peserta_asuransi');
        $html = view($payload['document']['view'], $payload)->render();
        self::assertStringContainsString('Desa', $html); // kolom desa
        self::assertStringContainsString('Bulan', $html); // jangka
        self::assertStringContainsString('Bandung', $html); // birth place
        self::assertStringContainsString('15 Mei 1985', $html); // birth date label
        self::assertStringNotContainsString("{{ '' }}", $html);
    }

    private function createGroupClassifications(): void
    {
        DB::connection('tenant')->table('business_types')->insertOrIgnore([
            ['row_id' => 1, 'tenant_id' => 1, 'id' => 1, 'code' => 'AGR', 'name' => 'Pertanian', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
        ]);
        DB::connection('tenant')->table('activity_types')->insertOrIgnore([
            ['row_id' => 1, 'tenant_id' => 1, 'id' => 1, 'code' => 'SP', 'name' => 'Simpan Pinjam', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
        ]);
        DB::connection('tenant')->table('group_levels')->insertOrIgnore([
            ['row_id' => 1, 'tenant_id' => 1, 'id' => 1, 'code' => 'MD', 'name' => 'Madya', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
        ]);
        DB::connection('tenant')->table('group_functions')->insertOrIgnore([
            ['row_id' => 1, 'tenant_id' => 1, 'id' => 1, 'code' => 'PROD', 'name' => 'Produktif', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
        ]);
    }

    private function seedMemberLoan(string $status): Loan
    {
        $loan = Loan::query()->create([
            'legacy_source' => 'member_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => $this->loanId++,
            'loan_number' => 'PI-DOC-'.str_pad((string) ($this->loanId * 1000), 6, '0', STR_PAD_LEFT),
            'proposed_at' => '2026-02-01',
            'verified_at' => $status === 'draft' ? null : '2026-02-10',
            'approved_at' => in_array($status, ['waiting', 'approved', 'active', 'disbursed', 'completed']) ? '2026-02-15' : null,
            'funded_at' => in_array($status, ['waiting', 'approved', 'active', 'disbursed', 'completed']) ? '2026-02-20' : null,
            'disbursed_at' => in_array($status, ['active', 'disbursed', 'completed']) ? '2026-02-25' : null,
            'principal_amount' => 8000000,
            'interest_rate' => 1.5,
            'service_rate_total' => 18.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'verification_notes' => 'Pemohon memiliki agunan BPKB.',
            'collateral' => ['type' => 'bpkb', 'nomor' => 'BP-12345', 'atas_nama' => 'Budi Santoso'],
            'status' => $status,
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => null,
            'member_row_id' => $this->memberId,
        ]);

        return $loan;
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
