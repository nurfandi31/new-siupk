<?php

declare(strict_types=1);

$root = 'C:\\laragon\\www\\siupknext';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

$tenant = Tenant::query()->with('placement.shard')->where('code', 'demo')->firstOrFail();
app(ShardConnectionManager::class)->connect($tenant->placement->shard);
app(TenantContext::class)->initialize($tenant, $tenant->placement, $tenant->placement->shard);

$db = DB::connection('tenant');
$tenantId = (int) $tenant->row_id;

$reset = in_array('--reset', $argv ?? [], true);
if ($reset) {
    echo "Reset mode: deleting prior dummy data for tenant {$tenant->code}...\n";
    $db->table('group_members')->where('tenant_id', $tenantId)->delete();
    $db->table('groups')->where('tenant_id', $tenantId)->delete();
    $db->table('member_addresses')->where('tenant_id', $tenantId)->delete();
    $db->table('members')->where('tenant_id', $tenantId)->delete();
    $db->table('people')->where('tenant_id', $tenantId)->delete();
}

$villages = $db->table('organization_units')
    ->where('tenant_id', $tenantId)
    ->where('type', 'village')
    ->orderBy('id')
    ->get();

if ($villages->isEmpty()) {
    fwrite(STDERR, "Tidak ada desa di tenant demo. Jalankan seed_local_villages.php dulu.\n");
    exit(1);
}

$businessTypes = $db->table('business_types')->where('tenant_id', $tenantId)->orderBy('row_id')->pluck('row_id')->all();
$activityTypes = $db->table('activity_types')->where('tenant_id', $tenantId)->orderBy('row_id')->pluck('row_id')->all();
$groupLevels = $db->table('group_levels')->where('tenant_id', $tenantId)->orderBy('row_id')->pluck('row_id')->all();
$groupFunctions = $db->table('group_functions')->where('tenant_id', $tenantId)->orderBy('row_id')->pluck('row_id')->all();

$now = now();

$firstNames = ['Budi', 'Siti', 'Agus', 'Dewi', 'Eko', 'Fitri', 'Hadi', 'Indah', 'Joko', 'Lestari'];
$lastNames = ['Santoso', 'Wijaya', 'Pratama', 'Lestari', 'Sukma', 'Wahyuni', 'Setiawan', 'Permadi', 'Saputra', 'Anggraini'];

$maxPeopleRow = (int) ($db->table('people')->max('row_id') ?? 0);
$maxMemberRow = (int) ($db->table('members')->max('row_id') ?? 0);
$maxGroupRow = (int) ($db->table('groups')->max('row_id') ?? 0);
$maxGmRow = (int) ($db->table('group_members')->max('row_id') ?? 0);
$maxAddressRow = (int) ($db->table('member_addresses')->max('row_id') ?? 0);

$nextPersonId = (int) ($db->table('people')->where('tenant_id', $tenantId)->max('id') ?? 0);
$nextMemberNumber = (int) ($db->table('members')->where('tenant_id', $tenantId)->max('id') ?? 0);
$nextGroupId = (int) ($db->table('groups')->where('tenant_id', $tenantId)->max('id') ?? 0);

$insertedPeople = [];
$insertedMembers = [];

for ($i = 1; $i <= 10; $i++) {
    $maxPeopleRow++;
    $maxMemberRow++;
    $nextMemberNumber++;
    $nextPersonId++;

    $fullName = $firstNames[$i - 1].' '.$lastNames[$i - 1];
    $nik = '3273011'.str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT);
    $birth = sprintf('%04d-%02d-%02d', 1980 + ($i % 20), (($i % 12) + 1), (($i % 27) + 1));
    $gender = $i % 2 === 0 ? 'P' : 'L';
    $village = $villages[($i - 1) % $villages->count()];

    $personRowId = $maxPeopleRow;
    $db->table('people')->insert([
        'tenant_id' => $tenantId,
        'row_id' => $personRowId,
        'id' => $nextPersonId,
        'public_id' => strtoupper(Ulid::generate()),
        'national_identity_number' => $nik,
        'family_card_number' => '3273011'.str_pad((string) (20000000 + $i), 8, '0', STR_PAD_LEFT),
        'full_name' => $fullName,
        'gender' => $gender,
        'birth_place' => 'Bandung',
        'birth_date' => $birth,
        'phone' => '0812'.str_pad((string) (4000000 + $i), 8, '0', STR_PAD_LEFT),
        'photo_path' => null,
        'identity_photo_path' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
    ]);

    $memberRowId = $maxMemberRow;
    $memberNumber = sprintf('DEMO-%05d', $nextMemberNumber);
    $db->table('members')->insert([
        'tenant_id' => $tenantId,
        'row_id' => $memberRowId,
        'id' => $nextMemberNumber,
        'public_id' => strtoupper(Ulid::generate()),
        'person_row_id' => $personRowId,
        'organization_unit_row_id' => $village->row_id,
        'member_number' => $memberNumber,
        'registered_at' => $now->toDateString(),
        'status' => 'active',
        'registered_by_user_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
    ]);

    $db->table('member_addresses')->insert([
        'tenant_id' => $tenantId,
        'row_id' => ++$maxAddressRow,
        'id' => $maxAddressRow,
        'member_row_id' => $memberRowId,
        'type' => 'home',
        'address' => 'Jl. Contoh No. '.$i.', '.$village->name,
        'village_code' => $village->code,
        'postal_code' => '40123',
        'is_primary' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $insertedPeople[] = $personRowId;
    $insertedMembers[] = ['row_id' => $memberRowId, 'name' => $fullName, 'village_row_id' => $village->row_id, 'village_name' => $village->name];
    echo "Member: {$memberNumber} {$fullName} ({$village->name})\n";
}

$groupNames = [
    'Kelompok Maju Bersama',
    'Kelompok Sejahtera',
    'Kelompok Mitra Usaha',
    'Kelompok Bina Lestari',
    'Kelompok Tunas Mandiri',
    'Kelompok Karya Wanita',
    'Kelompok Pemuda Produktif',
    'Kelompok Wanita Tani',
    'Kelompok Sumber Rezeki',
    'Kelompok Mitra Tani',
];

for ($g = 1; $g <= 10; $g++) {
    $maxGroupRow++;
    $nextGroupId++;
    $village = $villages[($g - 1) % $villages->count()];
    $groupRowId = $maxGroupRow;
    $groupCode = 'GRP-DEMO-'.str_pad((string) $g, 4, '0', STR_PAD_LEFT);

    $db->table('groups')->insert([
        'tenant_id' => $tenantId,
        'row_id' => $groupRowId,
        'id' => $nextGroupId,
        'public_id' => strtoupper(Ulid::generate()),
        'organization_unit_row_id' => $village->row_id,
        'business_type_row_id' => $businessTypes[($g - 1) % count($businessTypes)],
        'activity_type_row_id' => $activityTypes[($g - 1) % count($activityTypes)],
        'group_level_row_id' => $groupLevels[($g - 1) % count($groupLevels)],
        'group_function_row_id' => $groupFunctions[($g - 1) % count($groupFunctions)],
        'code' => $groupCode,
        'name' => $groupNames[$g - 1],
        'address' => 'Jl. Utama No. '.$g.', '.$village->name,
        'phone' => '022-'.str_pad((string) (7000000 + $g), 7, '0', STR_PAD_LEFT),
        'established_at' => $now->copy()->subMonths($g)->toDateString(),
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
    ]);

    for ($slot = 0; $slot < 2; $slot++) {
        $memberIdx = ($g - 1 + $slot) % count($insertedMembers);
        $m = $insertedMembers[$memberIdx];
        $maxGmRow++;
        $db->table('group_members')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'row_id' => $maxGmRow,
            'id' => $maxGmRow,
            'group_row_id' => $groupRowId,
            'member_row_id' => $m['row_id'],
            'joined_at' => $now->toDateString(),
            'left_at' => null,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ], 'uq_group_members_period');
    }

    echo "Group: {$groupCode} {$groupNames[$g - 1]} ({$village->name})\n";
}

echo "\nDone.\n";
echo 'Members total: '.$db->table('members')->where('tenant_id', $tenantId)->count()."\n";
echo 'Groups total: '.$db->table('groups')->where('tenant_id', $tenantId)->count()."\n";
echo 'Group members total: '.$db->table('group_members')->where('tenant_id', $tenantId)->count()."\n";
