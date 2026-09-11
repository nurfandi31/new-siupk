<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Domain\Migration\Support\LegacyConnection;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$tenant = Tenant::query()->with('placement.shard')->where('code', 'local')->firstOrFail();
app(ShardConnectionManager::class)->connect($tenant->placement->shard);
app(TenantContext::class)->initialize($tenant, $tenant->placement, $tenant->placement->shard);

$legacy = app(LegacyConnection::class);
$suffix = '1';
$kelTable = $legacy->kelompokTable($suffix);
echo "kelompok table: {$kelTable}\n";

$cols = $legacy->columns($kelTable);
$colNames = array_map(static function ($c) {
    if (is_object($c)) {
        return (string) ($c->Field ?? $c->column_name ?? $c->COLUMN_NAME ?? json_encode($c));
    }

    return (string) $c;
}, $cols);
echo 'all cols: '.implode(', ', $colNames)."\n";

$samples = $legacy->select("SELECT id, nama_kelompok, desa FROM `{$kelTable}` ORDER BY id LIMIT 15");
echo "kelompok samples:\n";
foreach ($samples as $s) {
    echo json_encode($s, JSON_UNESCAPED_UNICODE).PHP_EOL;
}

$distinct = $legacy->select("SELECT desa, COUNT(*) c FROM `{$kelTable}` GROUP BY desa ORDER BY c DESC LIMIT 40");
echo "kelompok desa distinct:\n";
foreach ($distinct as $d) {
    echo json_encode($d, JSON_UNESCAPED_UNICODE).PHP_EOL;
}

// legacy desa table
if ($legacy->tableExists('desa')) {
    echo "legacy desa table exists\n";
    $dc = $legacy->columns('desa');
    $dcNames = array_map(static fn ($c) => is_object($c) ? (string) ($c->Field ?? $c->COLUMN_NAME ?? '') : (string) $c, $dc);
    echo 'desa cols: '.implode(', ', $dcNames)."\n";
    $ds = $legacy->select('SELECT * FROM desa LIMIT 15');
    foreach ($ds as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
    echo 'desa count: '.$legacy->countAll('desa')."\n";
}

// Next org units
$units = DB::connection('tenant')->table('organization_units')
    ->where('tenant_id', 1)
    ->orderBy('code')
    ->get(['row_id', 'id', 'code', 'name', 'type']);
echo 'org units count: '.$units->count()."\n";
foreach ($units->take(25) as $u) {
    echo json_encode($u, JSON_UNESCAPED_UNICODE).PHP_EOL;
}

// match attempt
$legacyDesaCodes = collect($distinct)->pluck('desa')->map(fn ($v) => strtolower(trim((string) $v)))->all();
$unitCodes = $units->pluck('code')->map(fn ($v) => strtolower(trim((string) $v)))->all();
$matched = array_values(array_intersect($legacyDesaCodes, $unitCodes));
echo 'matched codes: '.implode(', ', $matched)."\n";
echo 'unmatched legacy desa: '.implode(', ', array_values(array_diff($legacyDesaCodes, $unitCodes)))."\n";

// members desa
$angTable = $legacy->anggotaTable($suffix);
$memDistinct = $legacy->select("SELECT desa, COUNT(*) c FROM `{$angTable}` GROUP BY desa ORDER BY c DESC LIMIT 20");
echo "anggota desa distinct:\n";
foreach ($memDistinct as $d) {
    echo json_encode($d, JSON_UNESCAPED_UNICODE).PHP_EOL;
}
$memWithUnit = DB::connection('tenant')->table('members')->where('tenant_id', 1)->whereNotNull('organization_unit_row_id')->count();
$memTotal = DB::connection('tenant')->table('members')->where('tenant_id', 1)->count();
echo "members with unit: {$memWithUnit}/{$memTotal}\n";
