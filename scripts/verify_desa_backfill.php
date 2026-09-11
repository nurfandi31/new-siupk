<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Domain\Lending\Services\Reports\LoanPortfolioReportService;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$tenant = Tenant::query()->with('placement.shard')->where('code', 'local')->firstOrFail();
app(ShardConnectionManager::class)->connect($tenant->placement->shard);
app(TenantContext::class)->initialize($tenant, $tenant->placement, $tenant->placement->shard);

$c = DB::connection('tenant');
echo 'org units: '.$c->table('organization_units')->where('tenant_id', 1)->where('type', 'village')->count().PHP_EOL;
echo 'groups with unit: '.$c->table('groups')->where('tenant_id', 1)->whereNotNull('organization_unit_row_id')->count().PHP_EOL;
echo 'members with unit: '.$c->table('members')->where('tenant_id', 1)->whereNotNull('organization_unit_row_id')->count().PHP_EOL;

$sample = $c->table('groups as g')
    ->leftJoin('organization_units as u', function ($j): void {
        $j->on('u.tenant_id', '=', 'g.tenant_id')->on('u.row_id', '=', 'g.organization_unit_row_id');
    })
    ->where('g.tenant_id', 1)
    ->orderBy('g.id')
    ->limit(5)
    ->get(['g.id', 'g.name', 'g.organization_unit_row_id', 'u.code', 'u.name as village']);
foreach ($sample as $s) {
    echo json_encode($s, JSON_UNESCAPED_UNICODE).PHP_EOL;
}

$p = app(LoanPortfolioReportService::class)->build(date('Y-m-d'), 'all');
echo 'portfolio villages: '.count($p['by_village']).PHP_EOL;
foreach (array_slice($p['by_village'], 0, 8) as $v) {
    echo json_encode($v, JSON_UNESCAPED_UNICODE).PHP_EOL;
}
