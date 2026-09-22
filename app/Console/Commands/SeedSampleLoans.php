<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberAddress;
use App\Domain\Membership\Models\Person;
use App\Models\Platform\Tenant;
use App\Models\Tenant\ActivityType;
use App\Models\Tenant\BusinessType;
use App\Models\Tenant\GroupFunction;
use App\Models\Tenant\GroupLevel;
use App\Models\Tenant\OrganizationUnit;
use App\Models\Tenant\VillageNaming;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seed sample loans untuk visualisasi setiap status pipeline (kelompok + individu).
 *   - Kelompok: proposal, verifikasi, alokasi, aktif, lunas, hapus-buku, reschedule
 *   - Individu: proposal, verifikasi, alokasi, aktif, lunas
 *
 * Idempotent: aman dipanggil berulang kali.
 */
final class SeedSampleLoans extends Command
{
    protected $signature = 'siupk:seed-sample-loans
        {--tenant=local : Tenant code}
        {--reset : Hapus sample loans (prefix SAMPLE-) sebelum seed ulang}';

    protected $description = 'Seed sample loans for visualisasi setiap status pipeline (kelompok + individu).';

    private int $tenantId;

    private const GROUP_PRODUCT_CODE = 'spp';

    private const INDIVIDUAL_PRODUCT_CODE = 'pi';

    public function handle(
        TenantContext $context,
        TenantSequenceService $sequences,
    ): int {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('siupk:seed-sample-loans restricted to local/testing.');
        }

        $tenantCode = (string) $this->option('tenant');
        $tenant = Tenant::query()->where('code', $tenantCode)->first();
        if ($tenant === null) {
            $this->error("Tenant [{$tenantCode}] not found.");

            return self::FAILURE;
        }

        $placement = $tenant->fresh(['placement.shard'])->placement;
        $shard = $placement?->shard;
        if ($placement === null || $shard === null) {
            $this->error('Tenant placement missing.');

            return self::FAILURE;
        }

        app(ShardConnectionManager::class)->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $this->tenantId = (int) $context->id();

            if ($this->option('reset')) {
                $this->resetSampleData();
            }

            $this->info('Seeding master data...');
            $village = $this->ensureVillage();
            $villageNamingId = $this->ensureVillageNaming();
            $village->village_naming_id = $villageNamingId;
            $village->save();

            $business = ActivityType::query()->where('code', 'trade')->first()
                ?? ActivityType::query()->firstOrFail();
            $level = GroupLevel::query()->where('code', 'developing')->first()
                ?? GroupLevel::query()->where('code', 'beginner')->first()
                ?? GroupLevel::query()->firstOrFail();
            $function = GroupFunction::query()->where('code', 'channeling')->first()
                ?? GroupFunction::query()->firstOrFail();

            $this->newLine();
            $this->info('Seeding sample loans (kelompok)...');
            $this->seedGroupLoans($village, $business, $level, $function, $sequences);

            $this->newLine();
            $this->info('Seeding sample loans (individu)...');
            $this->seedIndividualLoans($village, $sequences);

            $this->newLine();
            $this->info('Done.');
            $this->printSummary();
        } finally {
            $context->clear();
            app(ShardConnectionManager::class)->disconnect();
        }

        return self::SUCCESS;
    }

    private function resetSampleData(): void
    {
        $this->warn('Resetting sample loans (prefix SAMPLE-)...');
        $matched = DB::connection('tenant')->table('loans')
            ->where('tenant_id', $this->tenantId)
            ->where('loan_number', 'like', 'SAMPLE-%')
            ->orderByDesc('row_id')
            ->pluck('row_id');

        if ($matched->isEmpty()) {
            $this->line('  No sample loans found.');

            return;
        }

        DB::connection('tenant')->transaction(function () use ($matched): void {
            // Break FK from rescheduled-child to parent before deleting parent loans.
            DB::connection('tenant')->table('loans')
                ->where('tenant_id', $this->tenantId)
                ->whereIn('rescheduled_from_loan_row_id', $matched)
                ->update(['rescheduled_from_loan_row_id' => null]);
            DB::connection('tenant')->table('loan_status_histories')->where('tenant_id', $this->tenantId)->whereIn('loan_row_id', $matched)->delete();
            DB::connection('tenant')->table('loan_installments')->where('tenant_id', $this->tenantId)->whereIn('loan_row_id', $matched)->delete();
            DB::connection('tenant')->table('loan_payments')->where('tenant_id', $this->tenantId)->whereIn('loan_row_id', $matched)->delete();
            DB::connection('tenant')->table('loan_committee')->where('tenant_id', $this->tenantId)->whereIn('loan_row_id', $matched)->delete();
            DB::connection('tenant')->table('loan_beneficiaries')->where('tenant_id', $this->tenantId)->whereIn('loan_row_id', $matched)->delete();
            DB::connection('tenant')->table('loan_borrowers')->where('tenant_id', $this->tenantId)->whereIn('loan_row_id', $matched)->delete();
            DB::connection('tenant')->table('loans')->where('tenant_id', $this->tenantId)->whereIn('row_id', $matched)->delete();
        });
        $this->line('  Deleted '.$matched->count().' sample loan(s).');
    }

    private function ensureVillage(): OrganizationUnit
    {
        $village = OrganizationUnit::query()
            ->where('type', 'village')
            ->where('code', '3201012001')
            ->first();
        if ($village !== null) {
            return $village;
        }

        return OrganizationUnit::query()->create([
            'id' => app(TenantSequenceService::class)->next('organization_units'),
            'code' => '3201012001',
            'name' => 'Desa Sukamaju',
            'type' => 'village',
            'parent_row_id' => null,
            'village_naming_id' => null,
            'is_active' => true,
        ]);
    }

    private function ensureVillageNaming(): int
    {
        $existing = VillageNaming::query()->active()->orderBy('row_id')->value('row_id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return VillageNaming::query()->create([
            'id' => app(TenantSequenceService::class)->next('village_namings'),
            'code' => 'village',
            'village_name' => 'Desa',
            'village_head_name' => 'Kepala Desa',
            'is_active' => true,
        ])->row_id;
    }

    /**
     * @return array{group: Group, members: list<Member>}
     */
    private function ensureSampleGroup(OrganizationUnit $village, ActivityType $activity, GroupLevel $level, GroupFunction $function, string $code, string $name, array $memberSpecs): array
    {
        $existing = Group::query()->where('code', $code)->first();
        if ($existing !== null) {
            $members = $existing->memberships()->whereNull('left_at')->get()
                ->map(fn ($m) => Member::query()->find($m->member_row_id))
                ->filter()
                ->values()
                ->all();

            return ['group' => $existing, 'members' => $members];
        }

        $group = Group::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => $code,
            'name' => $name,
            'address' => 'Jl. '.$name.' No. '.$code,
            'organization_unit_row_id' => $village->row_id,
            'activity_type_row_id' => $activity->row_id,
            'group_level_row_id' => $level->row_id,
            'group_function_row_id' => $function->row_id,
            'business_type_row_id' => BusinessType::query()->where('code', 'various_businesses')->value('row_id')
                ?? BusinessType::query()->firstOrFail()->row_id,
            'status' => 'active',
            'established_at' => now()->subYears(2)->toDateString(),
        ]);

        $members = [];
        foreach ($memberSpecs as $idx => $spec) {
            $person = Person::query()->create([
                'public_id' => (string) Str::ulid(),
                'national_identity_number' => $spec['nik'],
                'full_name' => $spec['name'],
                'gender' => 'P',
                'birth_place' => 'Sukamaju',
                'birth_date' => '1985-'.str_pad((string) (($idx % 12) + 1), 2, '0', STR_PAD_LEFT).'-15',
            ]);
            $member = Member::query()->create([
                'public_id' => (string) Str::ulid(),
                'person_row_id' => $person->row_id,
                'member_number' => $spec['number'],
                'organization_unit_row_id' => $village->row_id,
                'registered_at' => now()->subYears(2)->toDateString(),
                'status' => 'active',
            ]);
            MemberAddress::query()->create([
                'member_row_id' => $member->row_id,
                'type' => 'domisili',
                'address' => 'Dusun '.$spec['dusun'].', '.$village->name,
                'village_code' => $village->code,
                'is_primary' => true,
            ]);
            GroupMember::query()->create([
                'group_row_id' => $group->row_id,
                'member_row_id' => $member->row_id,
                'joined_at' => now()->subYears(2)->toDateString(),
                'status' => 'active',
            ]);
            $members[] = $member;
        }

        return ['group' => $group, 'members' => $members];
    }

    private function ensureSampleMember(OrganizationUnit $village, string $number, string $nik, string $name, string $dusun): Member
    {
        $existing = Member::query()->where('member_number', $number)->first();
        if ($existing !== null) {
            return $existing;
        }
        $person = Person::query()->create([
            'public_id' => (string) Str::ulid(),
            'national_identity_number' => $nik,
            'full_name' => $name,
            'gender' => 'P',
            'birth_place' => 'Sukamaju',
            'birth_date' => '1980-06-15',
        ]);
        $member = Member::query()->create([
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $person->row_id,
            'member_number' => $number,
            'organization_unit_row_id' => $village->row_id,
            'registered_at' => now()->subYears(3)->toDateString(),
            'status' => 'active',
        ]);
        MemberAddress::query()->create([
            'member_row_id' => $member->row_id,
            'type' => 'domisili',
            'address' => 'Dusun '.$dusun.', '.$village->name,
            'village_code' => $village->code,
            'is_primary' => true,
        ]);

        return $member;
    }

    private function seedGroupLoans(OrganizationUnit $village, ActivityType $activity, GroupLevel $level, GroupFunction $function, TenantSequenceService $sequences): void
    {
        $product = LoanProduct::query()->where('code', self::GROUP_PRODUCT_CODE)->where('is_active', true)->firstOrFail();
        $cashAccount = DB::connection('tenant')->table('accounts')->where('code', '1.1.01.01')->first();
        if ($cashAccount === null) {
            throw new RuntimeException('Cash account 1.1.01.01 not seeded.');
        }

        // Build 2 groups × 5 members each → enough for sample beneficiaries across loans.
        $groupA = $this->ensureSampleGroup($village, $activity, $level, $function, 'SAMPLE-GRP-A', 'Kelompok Suka Maju A', [
            ['number' => 'SAMPLE-001', 'nik' => '3201015001850001', 'name' => 'Siti Aminah', 'dusun' => 'Cisalak'],
            ['number' => 'SAMPLE-002', 'nik' => '3201015001860002', 'name' => 'Nuraeni', 'dusun' => 'Cisalak'],
            ['number' => 'SAMPLE-003', 'nik' => '3201015001870003', 'name' => 'Rohayati', 'dusun' => 'Cisalak'],
            ['number' => 'SAMPLE-004', 'nik' => '3201015001880004', 'name' => 'Maryati', 'dusun' => 'Cisalak'],
            ['number' => 'SAMPLE-005', 'nik' => '3201015001890005', 'name' => 'Halimah', 'dusun' => 'Cisalak'],
        ]);

        $groupB = $this->ensureSampleGroup($village, $activity, $level, $function, 'SAMPLE-GRP-B', 'Kelompok Suka Maju B', [
            ['number' => 'SAMPLE-006', 'nik' => '3201015001900006', 'name' => 'Julaeha', 'dusun' => 'Naringgul'],
            ['number' => 'SAMPLE-007', 'nik' => '3201015001910007', 'name' => 'Suryati', 'dusun' => 'Naringgul'],
            ['number' => 'SAMPLE-008', 'nik' => '3201015001920008', 'name' => 'Kasminah', 'dusun' => 'Naringgul'],
            ['number' => 'SAMPLE-009', 'nik' => '3201015001930009', 'name' => 'Tuminem', 'dusun' => 'Naringgul'],
            ['number' => 'SAMPLE-010', 'nik' => '3201015001940010', 'name' => 'Warti', 'dusun' => 'Naringgul'],
        ]);

        $today = CarbonImmutable::today();

        // 1. PROPOSAL (draft) — group A, baru diajukan kemarin.
        $this->upsertGroupLoan([
            'tag' => 'PROPOSAL',
            'loan_number' => 'SAMPLE-KL-PROPOSAL-001',
            'product' => $product,
            'group' => $groupA['group'],
            'members' => array_slice($groupA['members'], 0, 5),
            'committee_members' => $groupA['members'],
            'principal' => 10000000,
            'term_months' => 10,
            'service_rate_total' => 24.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subDays(2)->toDateString(),
            'verified_at' => null,
            'approved_at' => null,
            'disbursed_at' => null,
            'completed_at' => null,
            'status' => 'draft',
        ], $sequences);

        // 2. VERIFIKASI (verified) — group B, sudah diverifikasi minggu lalu.
        $this->upsertGroupLoan([
            'tag' => 'VERIFIKASI',
            'loan_number' => 'SAMPLE-KL-VERIF-002',
            'product' => $product,
            'group' => $groupB['group'],
            'members' => array_slice($groupB['members'], 0, 4),
            'committee_members' => $groupB['members'],
            'principal' => 8000000,
            'term_months' => 8,
            'service_rate_total' => 20.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subDays(10)->toDateString(),
            'verified_at' => $today->subDays(6)->toDateString(),
            'approved_at' => null,
            'disbursed_at' => null,
            'completed_at' => null,
            'status' => 'verified',
            'verification_notes' => 'Data pemanfaat dan plafond sesuai verifikasi lapangan.',
        ], $sequences);

        // 3. ALOKASI (waiting) — group A, sudah disetujui menunggu pencairan.
        $this->upsertGroupLoan([
            'tag' => 'ALOKASI',
            'loan_number' => 'SAMPLE-KL-WAITING-003',
            'product' => $product,
            'group' => $groupA['group'],
            'members' => array_slice($groupA['members'], 0, 5),
            'committee_members' => $groupA['members'],
            'principal' => 15000000,
            'term_months' => 10,
            'service_rate_total' => 24.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subDays(20)->toDateString(),
            'verified_at' => $today->subDays(15)->toDateString(),
            'approved_at' => $today->subDays(10)->toDateString(),
            'planned_disbursed_at' => $today->addDays(3)->toDateString(),
            'disbursed_at' => null,
            'completed_at' => null,
            'status' => 'waiting',
        ], $sequences);

        // 4. AKTIF (active/disbursed) — group B, sudah 2 bulan berjalan.
        $this->upsertGroupLoan([
            'tag' => 'AKTIF',
            'loan_number' => 'SAMPLE-KL-AKTIF-004',
            'product' => $product,
            'group' => $groupB['group'],
            'members' => array_slice($groupB['members'], 0, 5),
            'committee_members' => $groupB['members'],
            'principal' => 12000000,
            'term_months' => 10,
            'service_rate_total' => 24.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subMonths(3)->toDateString(),
            'verified_at' => $today->subMonths(2)->subDays(20)->toDateString(),
            'approved_at' => $today->subMonths(2)->subDays(15)->toDateString(),
            'disbursed_at' => $today->subMonths(2)->subDays(10)->toDateString(),
            'completed_at' => null,
            'status' => 'active',
            'paid_periods' => 2, // 2 angsuran pertama sudah dibayar
        ], $sequences, $cashAccount->row_id);

        // 5. LUNAS (completed) — group A, lunas 1 bulan lalu.
        $this->upsertGroupLoan([
            'tag' => 'LUNAS',
            'loan_number' => 'SAMPLE-KL-LUNAS-005',
            'product' => $product,
            'group' => $groupA['group'],
            'members' => array_slice($groupA['members'], 0, 4),
            'committee_members' => $groupA['members'],
            'principal' => 6000000,
            'term_months' => 6,
            'service_rate_total' => 18.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subMonths(8)->toDateString(),
            'verified_at' => $today->subMonths(8)->addDays(3)->toDateString(),
            'approved_at' => $today->subMonths(8)->addDays(5)->toDateString(),
            'disbursed_at' => $today->subMonths(8)->addDays(7)->toDateString(),
            'completed_at' => $today->subMonths(1)->toDateString(),
            'status' => 'completed',
            'paid_periods' => 6,
        ], $sequences, $cashAccount->row_id);

        // 6. HAPUS BUKU (written_off) — group B.
        $this->upsertGroupLoan([
            'tag' => 'HAPUS-BUKU',
            'loan_number' => 'SAMPLE-KL-WRITEOFF-006',
            'product' => $product,
            'group' => $groupB['group'],
            'members' => array_slice($groupB['members'], 0, 4),
            'committee_members' => $groupB['members'],
            'principal' => 8000000,
            'term_months' => 10,
            'service_rate_total' => 24.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subMonths(5)->toDateString(),
            'verified_at' => $today->subMonths(5)->addDays(2)->toDateString(),
            'approved_at' => $today->subMonths(5)->addDays(4)->toDateString(),
            'disbursed_at' => $today->subMonths(5)->addDays(6)->toDateString(),
            'completed_at' => $today->subMonths(2)->toDateString(),
            'status' => 'written_off',
            'paid_periods' => 2,
            'write_off_reason' => 'Pinjaman macet (>90 hari) — anggota meninggal/bpkh.',
        ], $sequences, $cashAccount->row_id);

        // 7. RESCHEDULE (rescheduled, with new child loan active).
        $this->upsertGroupLoan([
            'tag' => 'RESCHEDULE',
            'loan_number' => 'SAMPLE-KL-RESCH-007',
            'product' => $product,
            'group' => $groupA['group'],
            'members' => array_slice($groupA['members'], 0, 4),
            'committee_members' => $groupA['members'],
            'principal' => 10000000,
            'term_months' => 10,
            'service_rate_total' => 24.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subMonths(7)->toDateString(),
            'verified_at' => $today->subMonths(7)->addDays(2)->toDateString(),
            'approved_at' => $today->subMonths(7)->addDays(4)->toDateString(),
            'disbursed_at' => $today->subMonths(7)->addDays(6)->toDateString(),
            'completed_at' => $today->subMonths(3)->toDateString(),
            'status' => 'rescheduled',
            'paid_periods' => 4,
            'reschedule_into' => [
                'loan_number' => 'SAMPLE-KL-RESCH-007B',
                'principal' => 6000000,
                'term_months' => 8,
                'service_rate_total' => 22.0,
                'disbursed_at' => $today->subMonths(3)->toDateString(),
            ],
        ], $sequences, $cashAccount->row_id);
    }

    private function seedIndividualLoans(OrganizationUnit $village, TenantSequenceService $sequences): void
    {
        $product = LoanProduct::query()->where('code', self::INDIVIDUAL_PRODUCT_CODE)->where('is_active', true)->firstOrFail();
        $cashAccount = DB::connection('tenant')->table('accounts')->where('code', '1.1.01.01')->first();
        if ($cashAccount === null) {
            throw new RuntimeException('Cash account 1.1.01.01 not seeded.');
        }

        $today = CarbonImmutable::today();

        $this->upsertIndividualLoan([
            'tag' => 'PROPOSAL',
            'loan_number' => 'SAMPLE-PI-PROPOSAL-001',
            'product' => $product,
            'member' => $this->ensureSampleMember($village, 'SAMPLE-101', '3201015001800101', 'Sumarni', 'Cisalak'),
            'principal' => 5000000,
            'term_months' => 12,
            'service_rate_total' => 18.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subDays(2)->toDateString(),
            'verified_at' => null,
            'approved_at' => null,
            'disbursed_at' => null,
            'completed_at' => null,
            'status' => 'draft',
        ], $sequences);

        $this->upsertIndividualLoan([
            'tag' => 'VERIFIKASI',
            'loan_number' => 'SAMPLE-PI-VERIF-002',
            'product' => $product,
            'member' => $this->ensureSampleMember($village, 'SAMPLE-102', '3201015001810102', 'Sulastri', 'Naringgul'),
            'principal' => 4000000,
            'term_months' => 10,
            'service_rate_total' => 20.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subDays(8)->toDateString(),
            'verified_at' => $today->subDays(4)->toDateString(),
            'approved_at' => null,
            'disbursed_at' => null,
            'completed_at' => null,
            'status' => 'verified',
            'verification_notes' => 'Pekerjaan dan penghasilan tetap, agunan BPKB motor.',
        ], $sequences);

        $this->upsertIndividualLoan([
            'tag' => 'ALOKASI',
            'loan_number' => 'SAMPLE-PI-WAITING-003',
            'product' => $product,
            'member' => $this->ensureSampleMember($village, 'SAMPLE-103', '3201015001820103', 'Yatmi', 'Cisalak'),
            'principal' => 6500000,
            'term_months' => 12,
            'service_rate_total' => 18.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subDays(15)->toDateString(),
            'verified_at' => $today->subDays(10)->toDateString(),
            'approved_at' => $today->subDays(6)->toDateString(),
            'planned_disbursed_at' => $today->addDays(2)->toDateString(),
            'disbursed_at' => null,
            'completed_at' => null,
            'status' => 'waiting',
        ], $sequences);

        $this->upsertIndividualLoan([
            'tag' => 'AKTIF',
            'loan_number' => 'SAMPLE-PI-AKTIF-004',
            'product' => $product,
            'member' => $this->ensureSampleMember($village, 'SAMPLE-104', '3201015001830104', 'Ruminah', 'Cisalak'),
            'principal' => 5500000,
            'term_months' => 12,
            'service_rate_total' => 18.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subMonths(3)->toDateString(),
            'verified_at' => $today->subMonths(3)->addDays(2)->toDateString(),
            'approved_at' => $today->subMonths(3)->addDays(4)->toDateString(),
            'disbursed_at' => $today->subMonths(2)->subDays(20)->toDateString(),
            'completed_at' => null,
            'status' => 'active',
            'paid_periods' => 3,
        ], $sequences, $cashAccount->row_id);

        $this->upsertIndividualLoan([
            'tag' => 'LUNAS',
            'loan_number' => 'SAMPLE-PI-LUNAS-005',
            'product' => $product,
            'member' => $this->ensureSampleMember($village, 'SAMPLE-105', '3201015001840105', 'Wasri', 'Naringgul'),
            'principal' => 3500000,
            'term_months' => 6,
            'service_rate_total' => 15.0,
            'principal_freq' => 'monthly',
            'interest_freq' => 'monthly',
            'method' => 'flat',
            'proposed_at' => $today->subMonths(7)->toDateString(),
            'verified_at' => $today->subMonths(7)->addDays(3)->toDateString(),
            'approved_at' => $today->subMonths(7)->addDays(5)->toDateString(),
            'disbursed_at' => $today->subMonths(7)->addDays(7)->toDateString(),
            'completed_at' => $today->subMonths(1)->toDateString(),
            'status' => 'completed',
            'paid_periods' => 6,
        ], $sequences, $cashAccount->row_id);
    }

    private function upsertGroupLoan(array $spec, TenantSequenceService $sequences, ?int $cashAccountRowId = null): void
    {
        $existing = DB::connection('tenant')->table('loans')
            ->where('tenant_id', $this->tenantId)
            ->where('loan_number', $spec['loan_number'])
            ->first();
        if ($existing !== null) {
            $this->line("  • [{$spec['tag']}] {$spec['loan_number']} — already exists");

            return;
        }

        $loanId = DB::connection('tenant')->table('loans')->insertGetId([
            'tenant_id' => $this->tenantId,
            'id' => $sequences->next('loans'),
            'legacy_source' => 'group_loan',
            'public_id' => (string) Str::ulid(),
            'loan_number' => $spec['loan_number'],
            'spk_no' => isset($spec['disbursed_at']) ? 'SPK-'.str_replace('-', '', $spec['loan_number']) : null,
            'loan_product_row_id' => $spec['product']->row_id,
            'sequence_number' => 1,
            'proposed_at' => $spec['proposed_at'],
            'verified_at' => $spec['verified_at'] ?? null,
            'approved_at' => $spec['approved_at'] ?? null,
            'funded_at' => $spec['approved_at'] ?? null,
            'disbursed_at' => $spec['disbursed_at'] ?? null,
            'principal_amount' => $spec['principal'],
            'interest_rate' => round($spec['service_rate_total'] / $spec['term_months'], 4),
            'service_rate_total' => $spec['service_rate_total'],
            'term_months' => $spec['term_months'],
            'installment_method' => $spec['method'],
            'principal_frequency' => $spec['principal_freq'],
            'interest_frequency' => $spec['interest_freq'],
            'principal_grace_months' => 0,
            'interest_grace_months' => 0,
            'rounding_step' => null,
            'status' => $spec['status'],
            'verification_notes' => $spec['verification_notes'] ?? null,
            'disbursement_notes' => isset($spec['disbursement_notes']) ? $spec['disbursement_notes'] : null,
            'disbursement_account_row_id' => $spec['status'] === 'draft' || $spec['status'] === 'verified' ? null : ($cashAccountRowId ?? null),
            'completed_at' => $spec['completed_at'] ?? null,
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Borrower: group
        DB::connection('tenant')->table('loan_borrowers')->insert([
            'tenant_id' => $this->tenantId,
            'id' => $sequences->next('loan_borrowers'),
            'loan_row_id' => $loanId,
            'member_row_id' => null,
            'group_row_id' => $spec['group']->row_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Committee (chair, secretary, treasurer)
        $positions = ['chair' => 0, 'secretary' => 1, 'treasurer' => 2];
        foreach ($positions as $position => $idx) {
            $member = $spec['committee_members'][$idx] ?? null;
            if ($member === null) {
                continue;
            }
            DB::connection('tenant')->table('loan_committee')->insert([
                'tenant_id' => $this->tenantId,
                'id' => $sequences->next('loan_committee'),
                'loan_row_id' => $loanId,
                'position' => $position,
                'member_row_id' => $member->row_id,
                'member_name_snapshot' => $member->person?->full_name,
                'snapshot_at' => $spec['proposed_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Beneficiaries (split principal equally across members)
        $members = $spec['members'];
        $memberCount = count($members);
        $perBeneficiary = round($spec['principal'] / max(1, $memberCount), 2);
        $runningAllocated = 0.0;
        foreach ($members as $i => $member) {
            $share = ($i === $memberCount - 1)
                ? round($spec['principal'] - $runningAllocated, 2)
                : $perBeneficiary;
            $runningAllocated = round($runningAllocated + $share, 2);
            DB::connection('tenant')->table('loan_beneficiaries')->insert([
                'tenant_id' => $this->tenantId,
                'id' => $sequences->next('loan_beneficiaries'),
                'loan_row_id' => $loanId,
                'member_row_id' => $member->row_id,
                'proposed_amount' => $share,
                'verified_amount' => $share,
                'allocated_amount' => $share,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Status histories
        $this->insertStatusHistories($loanId, $spec, $sequences);

        // Installments & payments (only after verified stage)
        $paidPeriods = (int) ($spec['paid_periods'] ?? 0);
        $paidPeriods = min($paidPeriods, $spec['term_months']);
        if (in_array($spec['status'], ['waiting', 'active', 'completed', 'written_off', 'rescheduled'], true)) {
            $this->insertInstallments($loanId, $spec, $paidPeriods, $sequences);
            if (in_array($spec['status'], ['active', 'completed', 'written_off', 'rescheduled'], true)) {
                $this->insertPayments($loanId, $spec, $paidPeriods, $cashAccountRowId, $sequences);
            }
        } elseif ($spec['status'] === 'verified') {
            // Verified but no installment schedule yet (waiting for approval).
        } else {
            // Draft: schedule from proposed_at.
            $this->insertInstallments($loanId, $spec, 0, $sequences);
        }

        // Reschedule: create child loan.
        if ($spec['status'] === 'rescheduled' && isset($spec['reschedule_into'])) {
            $child = $spec['reschedule_into'];
            $childLoanId = DB::connection('tenant')->table('loans')->insertGetId([
                'tenant_id' => $this->tenantId,
                'id' => $sequences->next('loans'),
                'legacy_source' => 'group_loan',
                'public_id' => (string) Str::ulid(),
                'loan_number' => $child['loan_number'],
                'spk_no' => 'SPK-'.str_replace('-', '', $child['loan_number']),
                'loan_product_row_id' => $spec['product']->row_id,
                'sequence_number' => 2,
                'proposed_at' => $child['disbursed_at'],
                'verified_at' => $child['disbursed_at'],
                'approved_at' => $child['disbursed_at'],
                'funded_at' => $child['disbursed_at'],
                'disbursed_at' => $child['disbursed_at'],
                'principal_amount' => $child['principal'],
                'interest_rate' => round($child['service_rate_total'] / $child['term_months'], 4),
                'service_rate_total' => $child['service_rate_total'],
                'term_months' => $child['term_months'],
                'installment_method' => $spec['method'],
                'principal_frequency' => $spec['principal_freq'],
                'interest_frequency' => $spec['interest_freq'],
                'principal_grace_months' => 0,
                'interest_grace_months' => 0,
                'status' => 'active',
                'rescheduled_from_loan_row_id' => $loanId,
                'disbursement_account_row_id' => $cashAccountRowId,
                'disbursement_notes' => 'Pencairan reschedule dari '.$spec['loan_number'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::connection('tenant')->table('loan_borrowers')->insert([
                'tenant_id' => $this->tenantId,
                'id' => $sequences->next('loan_borrowers'),
                'loan_row_id' => $childLoanId,
                'member_row_id' => null,
                'group_row_id' => $spec['group']->row_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($positions as $position => $idx) {
                $member = $spec['committee_members'][$idx] ?? null;
                if ($member === null) {
                    continue;
                }
                DB::connection('tenant')->table('loan_committee')->insert([
                    'tenant_id' => $this->tenantId,
                    'id' => $sequences->next('loan_committee'),
                    'loan_row_id' => $childLoanId,
                    'position' => $position,
                    'member_row_id' => $member->row_id,
                    'member_name_snapshot' => $member->person?->full_name,
                    'snapshot_at' => $child['disbursed_at'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // Allocate pro-rata to beneficiaries.
            $childPerBeneficiary = round($child['principal'] / max(1, count($members)), 2);
            $running = 0.0;
            foreach ($members as $i => $member) {
                $share = ($i === count($members) - 1)
                    ? round($child['principal'] - $running, 2)
                    : $childPerBeneficiary;
                $running = round($running + $share, 2);
                DB::connection('tenant')->table('loan_beneficiaries')->insert([
                    'tenant_id' => $this->tenantId,
                    'id' => $sequences->next('loan_beneficiaries'),
                    'loan_row_id' => $childLoanId,
                    'member_row_id' => $member->row_id,
                    'proposed_amount' => $share,
                    'verified_amount' => $share,
                    'allocated_amount' => $share,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // Status histories (draft, verified, waiting, active).
            foreach (['draft' => null, 'verified' => 'draft', 'waiting' => 'verified', 'active' => 'waiting'] as $to => $from) {
                DB::connection('tenant')->table('loan_status_histories')->insert([
                    'tenant_id' => $this->tenantId,
                    'id' => $sequences->next('loan_status_histories'),
                    'loan_row_id' => $childLoanId,
                    'from_status' => $from,
                    'to_status' => $to,
                    'principal_amount' => $child['principal'],
                    'product_row_id' => $spec['product']->row_id,
                    'term_months' => $child['term_months'],
                    'service_rate_total' => $child['service_rate_total'],
                    'principal_frequency' => $spec['principal_freq'],
                    'interest_frequency' => $spec['interest_freq'],
                    'principal_grace_months' => 0,
                    'interest_grace_months' => 0,
                    'changed_by_user_id' => null,
                    'changed_at' => $child['disbursed_at'].' 09:00:00',
                    'created_at' => now(),
                ]);
            }
            // Installments for child loan (active, 0 paid).
            $childSpec = [
                'proposed_at' => $child['disbursed_at'],
                'disbursed_at' => $child['disbursed_at'],
                'principal' => $child['principal'],
                'service_rate_total' => $child['service_rate_total'],
                'term_months' => $child['term_months'],
                'method' => $spec['method'],
                'principal_freq' => $spec['principal_freq'],
                'interest_freq' => $spec['interest_freq'],
            ];
            $this->insertInstallments($childLoanId, $childSpec, 0, $sequences);
            $this->line("    → child loan [{$child['loan_number']}] created");
        }

        $this->line("  ✓ [{$spec['tag']}] {$spec['loan_number']} (status={$spec['status']})");
    }

    private function upsertIndividualLoan(array $spec, TenantSequenceService $sequences, ?int $cashAccountRowId = null): void
    {
        $existing = DB::connection('tenant')->table('loans')
            ->where('tenant_id', $this->tenantId)
            ->where('loan_number', $spec['loan_number'])
            ->first();
        if ($existing !== null) {
            $this->line("  • [{$spec['tag']}] {$spec['loan_number']} — already exists");

            return;
        }

        $loanId = DB::connection('tenant')->table('loans')->insertGetId([
            'tenant_id' => $this->tenantId,
            'id' => $sequences->next('loans'),
            'legacy_source' => 'member_loan',
            'public_id' => (string) Str::ulid(),
            'loan_number' => $spec['loan_number'],
            'spk_no' => isset($spec['disbursed_at']) ? 'SPK-PI-'.str_replace('-', '', $spec['loan_number']) : null,
            'loan_product_row_id' => $spec['product']->row_id,
            'sequence_number' => 1,
            'proposed_at' => $spec['proposed_at'],
            'verified_at' => $spec['verified_at'] ?? null,
            'approved_at' => $spec['approved_at'] ?? null,
            'funded_at' => $spec['approved_at'] ?? null,
            'disbursed_at' => $spec['disbursed_at'] ?? null,
            'principal_amount' => $spec['principal'],
            'interest_rate' => round($spec['service_rate_total'] / $spec['term_months'], 4),
            'service_rate_total' => $spec['service_rate_total'],
            'term_months' => $spec['term_months'],
            'installment_method' => $spec['method'],
            'principal_frequency' => $spec['principal_freq'],
            'interest_frequency' => $spec['interest_freq'],
            'principal_grace_months' => 0,
            'interest_grace_months' => 0,
            'rounding_step' => null,
            'status' => $spec['status'],
            'verification_notes' => $spec['verification_notes'] ?? null,
            'verification_remarks' => isset($spec['verification_remarks']) ? $spec['verification_remarks'] : null,
            'disbursement_account_row_id' => in_array($spec['status'], ['draft', 'verified'], true) ? null : ($cashAccountRowId ?? null),
            'completed_at' => $spec['completed_at'] ?? null,
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Borrower: member
        DB::connection('tenant')->table('loan_borrowers')->insert([
            'tenant_id' => $this->tenantId,
            'id' => $sequences->next('loan_borrowers'),
            'loan_row_id' => $loanId,
            'member_row_id' => $spec['member']->row_id,
            'group_row_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertStatusHistories($loanId, $spec, $sequences);

        $paidPeriods = (int) ($spec['paid_periods'] ?? 0);
        $paidPeriods = min($paidPeriods, $spec['term_months']);
        if (in_array($spec['status'], ['waiting', 'active', 'completed'], true)) {
            $this->insertInstallments($loanId, $spec, $paidPeriods, $sequences);
            if (in_array($spec['status'], ['active', 'completed'], true)) {
                $this->insertPayments($loanId, $spec, $paidPeriods, $cashAccountRowId, $sequences);
            }
        } else {
            $this->insertInstallments($loanId, $spec, 0, $sequences);
        }

        $this->line("  ✓ [{$spec['tag']}] {$spec['loan_number']} (status={$spec['status']})");
    }

    private function insertStatusHistories(int $loanId, array $spec, TenantSequenceService $sequences): void
    {
        $rows = [];
        $rows[] = ['from' => null, 'to' => 'draft', 'at' => $spec['proposed_at'].' 09:00:00', 'notes' => 'Proposal didaftarkan.'];
        if (in_array($spec['status'], ['verified', 'waiting', 'active', 'completed', 'written_off', 'rescheduled'], true)) {
            $rows[] = [
                'from' => 'draft',
                'to' => 'verified',
                'at' => ($spec['verified_at'] ?? $spec['proposed_at']).' 10:00:00',
                'notes' => $spec['verification_notes'] ?? 'Verifikasi data pemanfaat dan plafond.',
            ];
        }
        if (in_array($spec['status'], ['waiting', 'active', 'completed', 'written_off', 'rescheduled'], true)) {
            $rows[] = [
                'from' => 'verified',
                'to' => 'waiting',
                'at' => ($spec['approved_at'] ?? $spec['proposed_at']).' 11:00:00',
                'notes' => 'Alokasi disetujui, menunggu pencairan.',
            ];
        }
        if (in_array($spec['status'], ['active', 'completed', 'written_off', 'rescheduled'], true)) {
            $rows[] = [
                'from' => 'waiting',
                'to' => 'active',
                'at' => ($spec['disbursed_at'] ?? $spec['proposed_at']).' 13:00:00',
                'notes' => 'Pencairan pinjaman ke rekening kelompok/anggota.',
            ];
        }
        if ($spec['status'] === 'completed') {
            $rows[] = [
                'from' => 'active',
                'to' => 'completed',
                'at' => ($spec['completed_at'] ?? $spec['proposed_at']).' 14:00:00',
                'notes' => 'Validasi pelunasan: semua angsuran lunas.',
            ];
        } elseif ($spec['status'] === 'written_off') {
            $rows[] = [
                'from' => 'active',
                'to' => 'written_off',
                'at' => ($spec['completed_at'] ?? $spec['proposed_at']).' 14:00:00',
                'notes' => $spec['write_off_reason'] ?? 'Penghapusan piutang.',
            ];
        } elseif ($spec['status'] === 'rescheduled') {
            $rows[] = [
                'from' => 'active',
                'to' => 'rescheduled',
                'at' => ($spec['completed_at'] ?? $spec['proposed_at']).' 14:00:00',
                'notes' => 'Sisa pokok dialihkan ke pinjaman baru (reschedule).',
            ];
        }

        foreach ($rows as $row) {
            DB::connection('tenant')->table('loan_status_histories')->insert([
                'tenant_id' => $this->tenantId,
                'id' => $sequences->next('loan_status_histories'),
                'loan_row_id' => $loanId,
                'from_status' => $row['from'],
                'to_status' => $row['to'],
                'principal_amount' => $spec['principal'],
                'product_row_id' => $spec['product']->row_id,
                'term_months' => $spec['term_months'],
                'service_rate_total' => $spec['service_rate_total'],
                'principal_frequency' => $spec['principal_freq'],
                'interest_frequency' => $spec['interest_freq'],
                'principal_grace_months' => 0,
                'interest_grace_months' => 0,
                'notes' => $row['notes'],
                'changed_by_user_id' => null,
                'changed_at' => $row['at'],
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Generate simple flat-rate schedule: principal evenly split, interest = service_rate_total/12 * principal annually.
     */
    private function insertInstallments(int $loanId, array $spec, int $paidPeriods, TenantSequenceService $sequences): void
    {
        $term = (int) $spec['term_months'];
        $principal = (float) $spec['principal'];
        $serviceRateTotal = (float) $spec['service_rate_total']; // percent over term
        $method = $spec['method'] ?? 'flat';
        $principalFreq = $spec['principal_freq'] ?? 'monthly';
        $interestFreq = $spec['interest_freq'] ?? 'monthly';

        // Period step in months
        $stepMonths = match ($principalFreq) {
            'weekly', 'biweekly' => 0, // biweekly supports half-month proxy
            'monthly' => 1,
            'quarterly' => 3,
            'bimonthly' => 2,
            default => 1,
        };

        $startDate = CarbonImmutable::parse($spec['disbursed_at'] ?? $spec['proposed_at']);
        $periods = max(1, $term); // sample assumes monthly principal
        $principalPerPeriod = round($principal / $periods, 2);

        $runningPrincipal = 0.0;
        $runningInterest = 0.0;
        $paidPerPeriod = min($paidPeriods, $periods);

        for ($n = 1; $n <= $periods; $n++) {
            $dueDate = $startDate->addMonths($n - 1);
            $dueDateStr = $dueDate->toDateString();

            // principal
            $principalDue = ($n === $periods) ? round($principal - ($principalPerPeriod * ($periods - 1)), 2) : $principalPerPeriod;
            $principalPaid = $n <= $paidPerPeriod ? $principalDue : 0.0;
            $runningPrincipal = round($runningPrincipal + $principalDue, 2);

            // interest: flat = service_rate_total/term / 100 * principal
            $interestPerPeriod = round(($serviceRateTotal / 100) * $principal / $periods, 2);
            $interestDue = $interestPerPeriod;
            $interestPaid = $n <= $paidPerPeriod ? $interestDue : 0.0;
            $runningInterest = round($runningInterest + $interestDue, 2);

            $paidAt = $n <= $paidPerPeriod ? $dueDate->toDateString().' 10:00:00' : null;
            $status = $n <= $paidPerPeriod ? 'paid' : 'pending';

            DB::connection('tenant')->table('loan_installments')->insert([
                'tenant_id' => $this->tenantId,
                'id' => $sequences->next('loan_installments'),
                'loan_row_id' => $loanId,
                'component' => 'principal',
                'installment_number' => $n,
                'due_date' => $dueDateStr,
                'principal_due' => $principalDue,
                'interest_due' => 0,
                'running_principal' => $runningPrincipal,
                'running_interest' => $runningInterest,
                'principal_paid' => $principalPaid,
                'interest_paid' => 0,
                'penalty_due' => 0,
                'penalty_paid' => 0,
                'status' => $status,
                'paid_at' => $paidAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::connection('tenant')->table('loan_installments')->insert([
                'tenant_id' => $this->tenantId,
                'id' => $sequences->next('loan_installments'),
                'loan_row_id' => $loanId,
                'component' => 'interest',
                'installment_number' => $n,
                'due_date' => $dueDateStr,
                'principal_due' => 0,
                'interest_due' => $interestDue,
                'running_principal' => $runningPrincipal,
                'running_interest' => $runningInterest,
                'principal_paid' => 0,
                'interest_paid' => $interestPaid,
                'penalty_due' => 0,
                'penalty_paid' => 0,
                'status' => $status,
                'paid_at' => $paidAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function insertPayments(int $loanId, array $spec, int $paidPeriods, ?int $cashAccountRowId, TenantSequenceService $sequences): void
    {
        if ($cashAccountRowId === null) {
            return;
        }

        $term = (int) $spec['term_months'];
        $principal = (float) $spec['principal'];
        $serviceRateTotal = (float) $spec['service_rate_total'];
        $startDate = CarbonImmutable::parse($spec['disbursed_at'] ?? $spec['proposed_at']);

        $periods = max(1, $term);
        $principalPerPeriod = round($principal / $periods, 2);
        $interestPerPeriod = round(($serviceRateTotal / 100) * $principal / $periods, 2);
        $paidPeriods = min($paidPeriods, $periods);

        // One payment per period (consolidated principal+interest).
        for ($n = 1; $n <= $paidPeriods; $n++) {
            $paidAt = $startDate->addMonths($n - 1)->toDateString().' 10:00:00';
            $principalPaid = ($n === $periods) ? round($principal - ($principalPerPeriod * ($periods - 1)), 2) : $principalPerPeriod;
            $interestPaid = $interestPerPeriod;
            $total = round($principalPaid + $interestPaid, 2);

            DB::connection('tenant')->table('loan_payments')->insert([
                'tenant_id' => $this->tenantId,
                'id' => $sequences->next('loan_payments'),
                'public_id' => (string) Str::ulid(),
                'loan_row_id' => $loanId,
                'payment_number' => 'PAY-'.$spec['loan_number'].'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'paid_at' => $paidAt,
                'amount' => $total,
                'payment_method' => 'cash',
                'reference_number' => 'SETOR-'.$n,
                'created_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function printSummary(): void
    {
        $rows = DB::connection('tenant')->table('loans')
            ->where('tenant_id', $this->tenantId)
            ->where('loan_number', 'like', 'SAMPLE-%')
            ->select('legacy_source', 'status', 'loan_number', 'principal_amount', 'term_months')
            ->orderBy('legacy_source')
            ->orderBy('loan_number')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $this->table(
            ['Source', 'Status', 'Loan #', 'Plafon', 'Term'],
            $rows->map(fn ($r) => [
                $r->legacy_source === 'group_loan' ? 'Kelompok' : 'Individu',
                $r->status,
                $r->loan_number,
                number_format((float) $r->principal_amount, 0, ',', '.'),
                $r->term_months.' bln',
            ])->all()
        );
    }
}
