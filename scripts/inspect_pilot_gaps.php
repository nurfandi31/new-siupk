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
$tid = (int) $tenant->row_id;

echo "source_types:\n";
$rows = $c->table('journal_entries')
    ->where('tenant_id', $tid)
    ->where('status', 'posted')
    ->selectRaw("COALESCE(source_type, '(null)') as st, COUNT(*) as c")
    ->groupByRaw("COALESCE(source_type, '(null)')")
    ->orderByDesc('c')
    ->get();
foreach ($rows as $r) {
    echo "  {$r->st}: {$r->c}\n";
}

echo 'loan_payments: '.$c->table('loan_payments')->where('tenant_id', $tid)->count()."\n";
echo 'loan_payments with journal_entry_row_id: '.$c->table('loan_payments')->where('tenant_id', $tid)->whereNotNull('journal_entry_row_id')->count()."\n";
echo 'groups with village: '.$c->table('groups')->where('tenant_id', $tid)->whereNotNull('organization_unit_row_id')->count()."\n";
echo 'groups total: '.$c->table('groups')->where('tenant_id', $tid)->count()."\n";
echo 'groups sample village null: '.$c->table('groups')->where('tenant_id', $tid)->whereNull('organization_unit_row_id')->limit(3)->pluck('name')->implode(', ')."\n";

// sample journal linked to loan?
$sample = $c->table('journal_entries')
    ->where('tenant_id', $tid)
    ->where('status', 'posted')
    ->whereNotNull('legacy_loan_id')
    ->orderByDesc('id')
    ->first(['id', 'source_type', 'legacy_loan_id', 'description', 'transaction_date']);
echo 'sample legacy_loan journal: '.json_encode($sample)."\n";

$withLoan = $c->table('journal_entries')
    ->where('tenant_id', $tid)
    ->where('status', 'posted')
    ->whereNotNull('legacy_loan_id')
    ->count();
echo "journals with legacy_loan_id: {$withLoan}\n";
