<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\InstallmentReceiptService;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$tenant = Tenant::query()->with('placement.shard')->where('code', 'local')->firstOrFail();
app(ShardConnectionManager::class)->connect($tenant->placement->shard);
app(TenantContext::class)->initialize($tenant, $tenant->placement, $tenant->placement->shard);

$entry = JournalEntry::query()
    ->where('status', 'posted')
    ->where('description', 'like', 'Angs.%')
    ->orderByDesc('id')
    ->first();

echo 'entry: '.json_encode([
    'row_id' => $entry->row_id,
    'id' => $entry->id,
    'desc' => $entry->description,
    'legacy_loan_id' => $entry->legacy_loan_id,
]).PHP_EOL;

$lines = DB::connection('tenant')->table('journal_lines as l')
    ->leftJoin('accounts as a', function ($j): void {
        $j->on('a.tenant_id', '=', 'l.tenant_id')->on('a.row_id', '=', 'l.account_row_id');
    })
    ->where('l.tenant_id', 1)
    ->where('l.journal_entry_row_id', $entry->row_id)
    ->get(['l.debit', 'l.credit', 'l.description', 'a.code', 'a.name', 'a.account_type']);
foreach ($lines as $l) {
    echo json_encode($l).PHP_EOL;
}

$r = app(InstallmentReceiptService::class)->build($entry);
echo 'receipt amounts: '.json_encode($r['amounts']).PHP_EOL;
echo 'loan: '.json_encode($r['loan']).PHP_EOL;
echo 'payer: '.json_encode($r['payer']).PHP_EOL;
