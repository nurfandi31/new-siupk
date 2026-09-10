<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership;

use App\Domain\Migration\Membership\DTO\NormalizedMemberBundle;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LegacyMemberLoader
{
    public function __construct(
        private TenantContext $context,
        private TenantSequenceService $sequences,
    ) {}

    /**
     * @param  list<NormalizedMemberBundle>  $members
     * @return array{inserted: int, skipped: int}
     */
    public function loadChunk(int $batchRowId, string $sourceTable, array $members): array
    {
        if ($members === []) {
            return ['inserted' => 0, 'skipped' => 0];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;
        $skipped = 0;

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId,
            $batchRowId,
            $sourceTable,
            $members,
            $now,
            &$inserted,
            &$skipped,
        ): void {
            foreach ($members as $m) {
                $already = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $sourceTable)
                    ->where('source_id', (string) $m->legacyId)
                    ->where('source_secondary_key', 'member')
                    ->exists();
                if ($already) {
                    $skipped++;

                    continue;
                }

                // NIK unique: if another person already has this NIK, null it out for this insert
                $nik = $m->nik;
                if ($nik !== null) {
                    $existsNik = $db->table('people')
                        ->where('tenant_id', $tenantId)
                        ->where('national_identity_number', $nik)
                        ->where('id', '!=', $m->legacyId)
                        ->exists();
                    if ($existsNik) {
                        $nik = null;
                    }
                }

                $memberNumber = $m->memberNumber;
                $numberTaken = $db->table('members')
                    ->where('tenant_id', $tenantId)
                    ->where('member_number', $memberNumber)
                    ->where('id', '!=', $m->legacyId)
                    ->exists();
                if ($numberTaken) {
                    $memberNumber = (string) $m->legacyId;
                }

                $existingPerson = $db->table('people')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $m->legacyId)
                    ->first(['row_id']);

                if ($existingPerson !== null) {
                    $personRowId = (int) $existingPerson->row_id;
                    $db->table('people')
                        ->where('row_id', $personRowId)
                        ->update([
                            'national_identity_number' => $nik,
                            'family_card_number' => $m->familyCardNumber,
                            'full_name' => $m->fullName,
                            'gender' => $m->gender,
                            'birth_place' => $m->birthPlace,
                            'birth_date' => $m->birthDate,
                            'phone' => $m->phone,
                            'updated_at' => $now,
                        ]);
                } else {
                    $personRowId = (int) $db->table('people')->insertGetId([
                        'tenant_id' => $tenantId,
                        'id' => $m->legacyId,
                        'public_id' => (string) Str::ulid(),
                        'national_identity_number' => $nik,
                        'family_card_number' => $m->familyCardNumber,
                        'full_name' => $m->fullName,
                        'gender' => $m->gender,
                        'birth_place' => $m->birthPlace,
                        'birth_date' => $m->birthDate,
                        'phone' => $m->phone,
                        'photo_path' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ], 'row_id');
                }

                $db->table('legacy_record_mappings')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'source_table' => $sourceTable,
                        'source_id' => (string) $m->legacyId,
                        'source_secondary_key' => 'person',
                    ],
                    [
                        'batch_row_id' => $batchRowId,
                        'target_table' => 'people',
                        'target_row_id' => $personRowId,
                        'target_local_id' => $m->legacyId,
                        'source_snapshot' => json_encode($m->snapshot, JSON_THROW_ON_ERROR),
                        'migrated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $existingMember = $db->table('members')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $m->legacyId)
                    ->first(['row_id']);

                if ($existingMember !== null) {
                    $memberRowId = (int) $existingMember->row_id;
                    $db->table('members')
                        ->where('row_id', $memberRowId)
                        ->update([
                            'person_row_id' => $personRowId,
                            'organization_unit_row_id' => $m->organizationUnitRowId,
                            'member_number' => $memberNumber,
                            'registered_at' => $m->registeredAt,
                            'status' => $m->status,
                            'updated_at' => $now,
                        ]);
                } else {
                    $memberRowId = (int) $db->table('members')->insertGetId([
                        'tenant_id' => $tenantId,
                        'id' => $m->legacyId,
                        'public_id' => (string) Str::ulid(),
                        'person_row_id' => $personRowId,
                        'organization_unit_row_id' => $m->organizationUnitRowId,
                        'member_number' => $memberNumber,
                        'registered_at' => $m->registeredAt,
                        'status' => $m->status,
                        'registered_by_user_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ], 'row_id');
                }

                $db->table('legacy_record_mappings')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'source_table' => $sourceTable,
                        'source_id' => (string) $m->legacyId,
                        'source_secondary_key' => 'member',
                    ],
                    [
                        'batch_row_id' => $batchRowId,
                        'target_table' => 'members',
                        'target_row_id' => $memberRowId,
                        'target_local_id' => $m->legacyId,
                        'source_snapshot' => json_encode(['legacy_id' => $m->legacyId], JSON_THROW_ON_ERROR),
                        'migrated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                if ($m->address !== null && $m->address !== '') {
                    $hasAddr = $db->table('member_addresses')
                        ->where('member_row_id', $memberRowId)
                        ->exists();
                    if (! $hasAddr) {
                        $addrId = $this->sequences->next('member_addresses');
                        $db->table('member_addresses')->insert([
                            'tenant_id' => $tenantId,
                            'id' => $addrId,
                            'member_row_id' => $memberRowId,
                            'type' => 'home',
                            'address' => $m->address,
                            'village_code' => null,
                            'postal_code' => null,
                            'is_primary' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                if ($m->businessName !== null && $m->businessName !== '') {
                    $hasBiz = $db->table('member_businesses')
                        ->where('member_row_id', $memberRowId)
                        ->exists();
                    if (! $hasBiz) {
                        $bizId = $this->sequences->next('member_businesses');
                        $db->table('member_businesses')->insert([
                            'tenant_id' => $tenantId,
                            'id' => $bizId,
                            'member_row_id' => $memberRowId,
                            'business_type_row_id' => null,
                            'name' => $m->businessName,
                            'description' => $m->businessDescription,
                            'address' => null,
                            'started_at' => null,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                if ($m->guarantorName !== null && $m->guarantorName !== '') {
                    $hasGuarantor = $db->table('member_guarantors')
                        ->where('member_row_id', $memberRowId)
                        ->exists();
                    if (! $hasGuarantor) {
                        $gNik = $m->guarantorNik;
                        if ($gNik !== null) {
                            $gExists = $db->table('people')
                                ->where('tenant_id', $tenantId)
                                ->where('national_identity_number', $gNik)
                                ->exists();
                            if ($gExists) {
                                $gNik = null;
                            }
                        }
                        $gPersonId = $this->sequences->next('people');
                        $gPersonRowId = (int) $db->table('people')->insertGetId([
                            'tenant_id' => $tenantId,
                            'id' => $gPersonId,
                            'public_id' => (string) Str::ulid(),
                            'national_identity_number' => $gNik,
                            'family_card_number' => null,
                            'full_name' => $m->guarantorName,
                            'gender' => null,
                            'birth_place' => null,
                            'birth_date' => null,
                            'phone' => null,
                            'photo_path' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ], 'row_id');

                        $db->table('legacy_record_mappings')->updateOrInsert(
                            [
                                'tenant_id' => $tenantId,
                                'source_table' => $sourceTable,
                                'source_id' => (string) $m->legacyId,
                                'source_secondary_key' => 'guarantor_person',
                            ],
                            [
                                'batch_row_id' => $batchRowId,
                                'target_table' => 'people',
                                'target_row_id' => $gPersonRowId,
                                'target_local_id' => $gPersonId,
                                'source_snapshot' => json_encode([
                                    'name' => $m->guarantorName,
                                    'nik' => $m->guarantorNik,
                                ], JSON_THROW_ON_ERROR),
                                'migrated_at' => $now,
                                'created_at' => $now,
                            ]
                        );

                        $gId = $this->sequences->next('member_guarantors');
                        $db->table('member_guarantors')->insert([
                            'tenant_id' => $tenantId,
                            'id' => $gId,
                            'member_row_id' => $memberRowId,
                            'guarantor_person_row_id' => $gPersonRowId,
                            'relationship_type' => $m->guarantorRelationship,
                            'valid_from' => null,
                            'valid_until' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                $inserted++;
            }
        }, 5);

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }
}
