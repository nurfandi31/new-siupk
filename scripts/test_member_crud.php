<?php

declare(strict_types=1);

$root = 'C:\\laragon\\www\\siupknext';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Membership\Services\MemberService;
use App\Models\Platform\Tenant;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;

$tenant = Tenant::query()->with('placement.shard')->where('code', 'demo')->firstOrFail();
app(ShardConnectionManager::class)->connect($tenant->placement->shard);
app(TenantContext::class)->initialize($tenant, $tenant->placement, $tenant->placement->shard);

$ctx = app(TenantContext::class);
echo "Tenant context id: {$ctx->id()}\n";

$village = OrganizationUnit::query()->villages()->active()->first();
echo "Village: row_id={$village->row_id} name={$village->name}\n";

// Test 1: Create Person via sequence
$seqService = app(TenantSequenceService::class);
$newNik = '327301'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
echo "Test 1: Creating Person (nik={$newNik})\n";
try {
    $person = new Person;
    $person->fill([
        'national_identity_number' => $newNik,
        'family_card_number' => '327301'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'full_name' => 'Test Person '.random_int(1000, 9999),
        'gender' => 'L',
        'birth_place' => 'Bandung',
        'birth_date' => '1990-05-15',
        'phone' => '0812'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
    ]);
    $person->save();
    echo "  OK Person id={$person->id} row_id={$person->row_id}\n";
} catch (Throwable $e) {
    echo '  FAILED: '.$e->getMessage()."\n";
}

// Test 2: Create Member via MemberService
echo "\nTest 2: Creating Member via MemberService\n";
try {
    $service = app(MemberService::class);
    $newNik2 = '327301'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    $data = [
        'nik' => $newNik2,
        'kk' => '327301'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'name' => 'Test Service Member '.random_int(1000, 9999),
        'gender' => 'P',
        'birth_place' => 'Cimahi',
        'birth_date' => '1992-08-20',
        'phone' => '0813'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'address' => 'Jl. Test No. 123',
        'village_code' => $village->code,
        'village_id' => $village->row_id,
    ];
    $member = $service->createQuickRegistration($data);
    echo "  OK Member row_id={$member->row_id} number={$member->member_number}\n";
    $testMemberRowId = $member->row_id;
} catch (Throwable $e) {
    echo '  FAILED: '.$e->getMessage()."\n  at ".$e->getFile().':'.$e->getLine()."\n";
    $testMemberRowId = null;
}

// Test 3: Verify Member query loads
echo "\nTest 3: Member::query()->with(['person', 'village'])->count() = ".Member::query()->with(['person', 'village'])->count()."\n";

// Test 4: Soft delete member
if ($testMemberRowId) {
    echo "\nTest 4: Soft-delete member row_id={$testMemberRowId}\n";
    try {
        $member = Member::query()->findOrFail($testMemberRowId);
        $member->delete();
        echo '  OK deleted. Trashed: '.Member::query()->onlyTrashed()->where('row_id', $testMemberRowId)->count()."\n";
    } catch (Throwable $e) {
        echo '  FAILED: '.$e->getMessage()."\n";
    }
}

// Test 5: Hard delete test person (cleanup)
$testPerson = Person::query()->where('national_identity_number', $newNik)->first();
if ($testPerson) {
    echo "\nTest 5: Force-delete test person row_id={$testPerson->row_id}\n";
    try {
        $testPerson->forceDelete();
        echo "  OK\n";
    } catch (Throwable $e) {
        echo '  FAILED: '.$e->getMessage()."\n";
    }
}

echo "\nFinal counts:\n";
foreach (['people' => Person::class, 'members' => Member::class] as $tbl => $cls) {
    echo " - {$tbl}: ".$cls::query()->count().' (trashed: '.$cls::query()->onlyTrashed()->count().")\n";
}
