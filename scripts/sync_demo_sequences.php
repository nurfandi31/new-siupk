<?php

declare(strict_types=1);

$root = 'C:\\laragon\\www\\siupknext';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$tenant = Tenant::query()->with('placement.shard')->where('code', 'demo')->firstOrFail();
app(ShardConnectionManager::class)->connect($tenant->placement->shard);
app(TenantContext::class)->initialize($tenant, $tenant->placement, $tenant->placement->shard);

$db = DB::connection('tenant');
$tenantId = (int) $tenant->row_id;
$seqService = app(TenantSequenceService::class);

$mappings = [
    'people' => 'people',
    'members' => 'members',
    'member_addresses' => 'member_addresses',
    'member_businesses' => 'member_businesses',
    'member_guarantors' => 'member_guarantors',
    'groups' => 'groups',
    'group_members' => 'group_members',
];

foreach ($mappings as $table => $sequenceName) {
    $maxId = (int) ($db->table($table)->where('tenant_id', $tenantId)->max('id') ?? 0);
    $existing = $db->table('tenant_sequences')
        ->where('tenant_id', $tenantId)
        ->where('sequence_name', $sequenceName)
        ->first();
    $currentNext = $existing ? (int) $existing->next_value : 0;
    $desired = $maxId + 1;

    if ($desired > $currentNext) {
        $seqService->initializeAtLeast($sequenceName, $desired);
        echo "Sync {$sequenceName}: current_next={$currentNext} -> {$desired} (max_id_in_table={$maxId})\n";
    } else {
        echo "OK   {$sequenceName}: current_next={$currentNext} (max_id_in_table={$maxId})\n";
    }
}

echo "\nSequences after sync:\n";
foreach ($db->table('tenant_sequences')->where('tenant_id', $tenantId)->orderBy('sequence_name')->get() as $s) {
    echo " - {$s->sequence_name}: next_value={$s->next_value}\n";
}
