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

$tenant = Tenant::query()->with('placement.shard')->where('code', 'demo')->firstOrFail();
app(ShardConnectionManager::class)->connect($tenant->placement->shard);
app(TenantContext::class)->initialize($tenant, $tenant->placement, $tenant->placement->shard);

$db = DB::connection('tenant');
$tenantId = (int) $tenant->row_id;

$villageNaming = $db->table('village_namings')
    ->where('tenant_id', $tenantId)
    ->where('code', 'village')
    ->first();

if ($villageNaming === null) {
    $rowId = (int) ($db->table('village_namings')->max('row_id') ?? 0) + 1;
    $nextId = (int) ($db->table('village_namings')->where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
    $now = now();
    $db->table('village_namings')->insert([
        'tenant_id' => $tenantId,
        'row_id' => $rowId,
        'id' => $nextId,
        'code' => 'village',
        'village_name' => 'Desa',
        'village_head_name' => 'Kepala Desa',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $villageNaming = $db->table('village_namings')->where('tenant_id', $tenantId)->where('code', 'village')->first();
    echo "Created village_naming row_id={$rowId}\n";
}

$villageNamingId = (int) $villageNaming->row_id;

$dummyVillages = [
    ['code' => '3273012001', 'name' => 'Desa Sukamaju'],
    ['code' => '3273012002', 'name' => 'Desa Sukamakmur'],
    ['code' => '3273012003', 'name' => 'Desa Sukaharapan'],
    ['code' => '3273012004', 'name' => 'Desa Sukaasih'],
    ['code' => '3273012005', 'name' => 'Desa Sukanegara'],
    ['code' => '3273012006', 'name' => 'Desa Sukamulya'],
    ['code' => '3273012007', 'name' => 'Desa Sukaraja'],
    ['code' => '3273012008', 'name' => 'Desa Sukatani'],
    ['code' => '3273012009', 'name' => 'Desa Sukawangi'],
    ['code' => '3273012010', 'name' => 'Desa Sukawening'],
];

$maxRowId = (int) ($db->table('organization_units')->max('row_id') ?? 0);
$maxId = (int) ($db->table('organization_units')->where('tenant_id', $tenantId)->max('id') ?? 0);
$now = now();

foreach ($dummyVillages as $idx => $v) {
    $exists = $db->table('organization_units')
        ->where('tenant_id', $tenantId)
        ->where('code', $v['code'])
        ->exists();

    if ($exists) {
        echo "Skip (exists): {$v['code']} {$v['name']}\n";

        continue;
    }

    $maxRowId++;
    $maxId++;
    $n = $idx + 1;

    $db->table('organization_units')->insert([
        'tenant_id' => $tenantId,
        'row_id' => $maxRowId,
        'id' => $maxId,
        'parent_row_id' => null,
        'code' => $v['code'],
        'name' => $v['name'],
        'type' => 'village',
        'village_naming_id' => $villageNamingId,
        'address' => 'Jl. Raya Desa No. '.$n.', Kec. Contoh, Kab. Contoh',
        'phone' => '0812'.str_pad((string) (1000 + $n), 8, '0', STR_PAD_LEFT),
        'is_active' => true,
        'village_head_name' => 'Kepala Desa '.$v['name'],
        'village_head_phone' => '0813'.str_pad((string) (2000 + $n), 8, '0', STR_PAD_LEFT),
        'village_head_nip' => '1985010120100'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
        'village_secretary_name' => 'Sekretaris '.$v['name'],
        'village_secretary_phone' => '0814'.str_pad((string) (3000 + $n), 8, '0', STR_PAD_LEFT),
        'village_council_name' => 'BPD '.$v['name'],
        'installment_schedule' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "Inserted: {$v['code']} {$v['name']}\n";
}

echo "\nTotal villages for tenant '{$tenant->code}': ".$db->table('organization_units')->where('tenant_id', $tenantId)->where('type', 'village')->count()."\n";

$maxIdInTenant = (int) ($db->table('organization_units')->where('tenant_id', $tenantId)->max('id') ?? 0);
echo "Max id in tenant: {$maxIdInTenant}\n";
echo "Initial table next_id to update: {$maxIdInTenant}\n";
