<?php

declare(strict_types=1);

$root = 'C:\\laragon\\www\\siupknext';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Membership\Services\GroupService;
use App\Domain\Membership\Services\MemberService;
use App\Models\Platform\Tenant;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$tenant = Tenant::query()->with('placement.shard')->where('code', 'demo')->firstOrFail();
app(ShardConnectionManager::class)->connect($tenant->placement->shard);
app(TenantContext::class)->initialize($tenant, $tenant->placement, $tenant->placement->shard);

$ctx = app(TenantContext::class);
echo "=== Tenant: {$tenant->code} (id={$ctx->id()}) ===\n\n";

$village = OrganizationUnit::query()->villages()->active()->first();

function assertPass(string $label, callable $cb): mixed
{
    try {
        $r = $cb();
        echo "  OK   {$label}\n";

        return $r;
    } catch (Throwable $e) {
        echo "  FAIL {$label}: ".$e->getMessage()."\n  at ".$e->getFile().':'.$e->getLine()."\n";

        return null;
    }
}

echo "[Member CRUD]\n";
$service = app(MemberService::class);

$newNik = '327301'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);

$member = assertPass('Create member', function () use ($service, $newNik, $village) {
    return $service->create([
        'nik' => $newNik,
        'kk' => '327301'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'name' => 'Test Member CRUD',
        'gender' => 'L',
        'birth_place' => 'Bandung',
        'birth_date' => '1990-01-15',
        'phone' => '0812'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'address' => 'Jl. CRUD Test',
        'village_code' => $village->code,
        'village_id' => $village->row_id,
        'registered_at' => '2026-09-21',
        'status' => 'active',
        'business_name' => 'Usaha Test',
        'business_description' => null,
        'business_started_at' => '2024-01-01',
        'guarantor_nik' => null,
        'guarantor_name' => null,
        'guarantor_birth_place' => null,
        'guarantor_birth_date' => null,
        'guarantor_relationship' => null,
    ], 0);
});

if ($member) {
    echo "  -> id={$member->id} row_id={$member->row_id} member_number={$member->member_number}\n";

    assertPass('Read member with relations', function () use ($member) {
        return $member->load(['person', 'village', 'address']);
    });

    $updatedNik = '327301'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    assertPass('Update member', function () use ($service, $member, $updatedNik, $village) {
        return $service->update($member, [
            'nik' => $updatedNik,
            'kk' => '327301'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'name' => 'Test Member CRUD (Updated)',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '1990-01-15',
            'phone' => '0812'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'address' => 'Jl. CRUD Test Updated',
            'village_code' => $village->code,
            'village_id' => $village->row_id,
            'registered_at' => '2026-09-21',
            'status' => 'active',
            'business_name' => null,
            'business_description' => null,
            'business_started_at' => null,
            'guarantor_nik' => null,
            'guarantor_name' => null,
            'guarantor_birth_place' => null,
            'guarantor_birth_date' => null,
            'guarantor_relationship' => null,
        ]);
    });

    echo "\n[Group CRUD]\n";
    $groupService = app(GroupService::class);

    $existingMembers = Member::query()->take(3)->get();
    $chair = $existingMembers[0]->row_id;
    $sec = $existingMembers[1]->row_id;
    $tre = $existingMembers[2]->row_id;

    $group = assertPass('Create group', function () use ($groupService, $village, $chair, $sec, $tre) {
        return $groupService->create([
            'village_id' => $village->row_id,
            'business_type_id' => 1,
            'activity_type_id' => 1,
            'group_level_id' => 1,
            'group_function_id' => 1,
            'name' => 'Test Group CRUD',
            'address' => 'Jl. Group Test',
            'phone' => '022-1234567',
            'established_at' => '2024-06-01',
            'status' => 'active',
            'member_ids' => [$chair, $sec, $tre],
            'chair_id' => $chair,
            'secretary_id' => $sec,
            'treasurer_id' => $tre,
        ]);
    });

    if ($group) {
        echo "  -> id={$group->id} row_id={$group->row_id} code={$group->code}\n";

        assertPass('Read group with memberships', function () use ($group) {
            return $group->load(['village', 'activeMemberships.member.person']);
        });

        assertPass('Update group', function () use ($groupService, $group, $village, $chair, $sec, $tre) {
            return $groupService->update($group, [
                'village_id' => $village->row_id,
                'business_type_id' => 1,
                'activity_type_id' => 2,
                'group_level_id' => 2,
                'group_function_id' => 2,
                'name' => 'Test Group CRUD (Updated)',
                'address' => 'Jl. Group Test Updated',
                'phone' => '022-7654321',
                'established_at' => '2024-06-01',
                'status' => 'active',
                'member_ids' => [$chair, $sec],
                'chair_id' => $chair,
                'secretary_id' => $sec,
                'treasurer_id' => $tre,
            ]);
        });

        assertPass('Delete group', function () use ($group) {
            $group->delete();

            return Group::query()->onlyTrashed()->where('row_id', $group->row_id)->count();
        });
    }

    assertPass('Delete member (soft)', function () use ($member) {
        $member->delete();

        return Member::query()->onlyTrashed()->where('row_id', $member->row_id)->count();
    });

    assertPass('Force-cleanup test data', function () use ($member) {
        $member->forceDelete();
        $member->person->forceDelete();
        DB::connection('tenant')->table('member_addresses')->where('member_row_id', $member->row_id)->delete();
        DB::connection('tenant')->table('group_members')->where('member_row_id', $member->row_id)->delete();

        return true;
    });
}

echo "\nFinal counts:\n";
echo ' - people: '.Person::query()->count()."\n";
echo ' - members: '.Member::query()->count()."\n";
echo ' - groups: '.Group::query()->count()."\n";
echo ' - group_members: '.GroupMember::query()->count()."\n";
