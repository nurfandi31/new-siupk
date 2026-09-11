<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$tenant = Tenant::query()->with('placement.shard')->where('code', 'local')->firstOrFail();
app(ShardConnectionManager::class)->connect($tenant->placement->shard);
app(TenantContext::class)->initialize($tenant, $tenant->placement, $tenant->placement->shard);
$c = DB::connection('tenant');
$tid = 1;

echo 'legacy_loan_id > 0: '.$c->table('journal_entries')->where('tenant_id', $tid)->where('legacy_loan_id', '>', 0)->count()."\n";
echo 'legacy_loan_id = 0: '.$c->table('journal_entries')->where('tenant_id', $tid)->where('legacy_loan_id', 0)->count()."\n";
echo 'legacy_loan_id null: '.$c->table('journal_entries')->where('tenant_id', $tid)->whereNull('legacy_loan_id')->count()."\n";

$sample = $c->table('journal_entries')
    ->where('tenant_id', $tid)
    ->where('legacy_loan_id', '>', 0)
    ->orderByDesc('id')
    ->limit(5)
    ->get(['id', 'legacy_loan_id', 'legacy_loan_item_id', 'description', 'transaction_date', 'source_type']);
echo "samples:\n";
foreach ($sample as $s) {
    echo json_encode($s).PHP_EOL;
}

// group desa field in membership mapping?
$cols = $c->select('SHOW COLUMNS FROM groups');
echo 'groups columns: '.collect($cols)->pluck('Field')->implode(', ').PHP_EOL;

// organization_units count
echo 'org units: '.$c->table('organization_units')->where('tenant_id', $tid)->count().PHP_EOL;
echo 'org units sample: '.json_encode($c->table('organization_units')->where('tenant_id', $tid)->limit(3)->get(['row_id', 'code', 'name', 'type'])).PHP_EOL;
