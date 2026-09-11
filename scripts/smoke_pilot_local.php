<?php

declare(strict_types=1);

/**
 * One-shot pilot smoke for tenant code=local.
 * Run: docker exec new_siupk-app-1 php scripts/smoke_pilot_local.php
 */

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\InstallmentReceiptService;
use App\Domain\Accounting\Services\JournalBrowseService;
use App\Domain\Accounting\Services\Reports\CalkService;
use App\Domain\Accounting\Services\Reports\CashFlowService;
use App\Domain\Accounting\Services\Reports\EquityChangeService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Services\Reports\LoanCardService;
use App\Domain\Lending\Services\Reports\LoanPortfolioReportService;
use App\Domain\Lending\Services\Reports\LoanScheduleVsActualService;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$out = static function (string $status, string $label, string $detail = ''): void {
    $line = str_pad("[{$status}]", 6).' '.$label;
    if ($detail !== '') {
        $line .= ' — '.$detail;
    }
    echo $line.PHP_EOL;
};

$fail = 0;

try {
    $tenant = Tenant::query()->with('placement.shard')->where('code', 'local')->first();
    if ($tenant === null) {
        $out('FAIL', 'tenant local', 'not found');
        exit(1);
    }
    $placement = $tenant->placement;
    $shard = $placement?->shard;
    if ($placement === null || $shard === null) {
        $out('FAIL', 'placement', 'incomplete');
        exit(1);
    }

    $connections = app(ShardConnectionManager::class);
    $context = app(TenantContext::class);
    $connections->connect($shard);
    $context->initialize($tenant, $placement, $shard);

    $conn = (string) config('tenancy.tenant_connection', 'tenant');
    $out('OK', 'tenant', "id={$tenant->row_id} shard={$shard->code}");

    // Counts
    $counts = [
        'members' => (int) DB::connection($conn)->table('members')->where('tenant_id', $tenant->row_id)->count(),
        'groups' => (int) DB::connection($conn)->table('groups')->where('tenant_id', $tenant->row_id)->count(),
        'loans' => (int) DB::connection($conn)->table('loans')->where('tenant_id', $tenant->row_id)->count(),
        'active_loans' => (int) DB::connection($conn)->table('loans')->where('tenant_id', $tenant->row_id)->whereIn('status', ['active', 'disbursed'])->count(),
        'journals_posted' => (int) DB::connection($conn)->table('journal_entries')->where('tenant_id', $tenant->row_id)->where('status', 'posted')->count(),
        'installment_journals' => (int) DB::connection($conn)->table('journal_entries')->where('tenant_id', $tenant->row_id)->where('status', 'posted')->where('source_type', 'loan_installment')->count(),
    ];
    foreach ($counts as $k => $v) {
        $out($v > 0 || in_array($k, ['installment_journals'], true) ? 'OK' : 'WARN', "count.{$k}", (string) $v);
    }

    $year = (int) date('Y');
    $month = (int) date('n');
    $asOf = date('Y-m-d');

    // P0.3 portfolio
    try {
        $p = app(LoanPortfolioReportService::class)->build($asOf, 'all');
        $out(
            $p['totals']['count'] > 0 ? 'OK' : 'WARN',
            'P0.3 portfolio',
            "loans={$p['totals']['count']} outstanding={$p['totals']['principal_remaining']} overdue={$p['totals']['overdue_count']} villages=".count($p['by_village'] ?? []),
        );
        $over = app(LoanPortfolioReportService::class)->build($asOf, 'overdue');
        $out('OK', 'P0.3 portfolio filter overdue', 'count='.$over['totals']['count']);
    } catch (Throwable $e) {
        $fail++;
        $out('FAIL', 'P0.3 portfolio', $e->getMessage());
    }

    // P0.3 schedule vs actual
    try {
        $rr = app(LoanScheduleVsActualService::class)->build($year, $month);
        $out('OK', 'P0.3 schedule-vs-actual', "rows={$rr['totals']['count']} gap_pokok={$rr['totals']['gap_principal']}");
    } catch (Throwable $e) {
        $fail++;
        $out('FAIL', 'P0.3 schedule-vs-actual', $e->getMessage());
    }

    // P0.2 journal browse
    try {
        $from = date('Y-m-01');
        $browse = app(JournalBrowseService::class)->list($from, $asOf, null, null, 1, 25);
        $out(
            $browse['pagination']['total'] > 0 ? 'OK' : 'WARN',
            'P0.2 journal browse',
            "total={$browse['pagination']['total']} page_rows=".count($browse['rows']),
        );
        $canReverse = collect($browse['rows'])->where('can_reverse', true)->count();
        $out('OK', 'P0.2 reverse candidates', (string) $canReverse);
    } catch (Throwable $e) {
        $fail++;
        $out('FAIL', 'P0.2 journal browse', $e->getMessage());
    }

    // P0.1 installment receipt (Next + legacy Angs.)
    try {
        // Prefer a material Angs. journal (skip tiny test rows like 0.04)
        $entryIds = JournalEntry::query()
            ->where('status', 'posted')
            ->where(function ($q): void {
                $q->where('source_type', 'loan_installment')
                    ->orWhere(function ($q2): void {
                        $q2->where('source_type', 'legacy_transaksi')
                            ->where('description', 'like', 'Angs.%');
                    });
            })
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('row_id');
        $entry = null;
        $receipt = null;
        foreach ($entryIds as $rid) {
            $candidate = JournalEntry::query()->whereKey((int) $rid)->first();
            if ($candidate === null) {
                continue;
            }
            try {
                $built = app(InstallmentReceiptService::class)->build($candidate);
            } catch (Throwable) {
                continue;
            }
            if (($built['amounts']['total'] ?? 0) >= 1000) {
                $entry = $candidate;
                $receipt = $built;
                break;
            }
            if ($entry === null) {
                $entry = $candidate;
                $receipt = $built;
            }
        }
        if ($entry === null || $receipt === null) {
            $out('WARN', 'P0.1 receipt', 'no angsuran journal found');
        } else {
            $loanId = $receipt['loan']['id'] ?? '—';
            $out(
                'OK',
                'P0.1 receipt build',
                "journal#{$entry->id} source={$entry->source_type} loan={$loanId} total={$receipt['amounts']['total']} P={$receipt['amounts']['principal']} J={$receipt['amounts']['interest']}",
            );
        }
    } catch (Throwable $e) {
        $fail++;
        $out('FAIL', 'P0.1 receipt', $e->getMessage());
    }

    // P1.1 cash flow
    try {
        $cf = app(CashFlowService::class)->build($year, $month);
        $out(
            $cf['reconciled'] ? 'OK' : 'WARN',
            'P1.1 cash-flow',
            "open={$cf['opening_cash']} net={$cf['net_change']} close={$cf['closing_cash']} reconciled=".($cf['reconciled'] ? 'yes' : 'no'),
        );
    } catch (Throwable $e) {
        $fail++;
        $out('FAIL', 'P1.1 cash-flow', $e->getMessage());
    }

    // P1.2 equity
    try {
        $eq = app(EquityChangeService::class)->build($year, $month);
        $out('OK', 'P1.2 equity-change', "closing={$eq['summary']['closing_total']} ni={$eq['summary']['period_net_income']}");
    } catch (Throwable $e) {
        $fail++;
        $out('FAIL', 'P1.2 equity-change', $e->getMessage());
    }

    // P1.3 calk
    try {
        $calk = app(CalkService::class)->build($year, $month);
        $out('OK', 'P1.3 calk', 'highlights='.count($calk['highlights']).' policies='.count($calk['policies']));
    } catch (Throwable $e) {
        $fail++;
        $out('FAIL', 'P1.3 calk', $e->getMessage());
    }

    // P1.4 loan card
    try {
        $loan = Loan::query()
            ->whereIn('status', ['active', 'disbursed', 'completed'])
            ->whereHas('installments')
            ->orderByDesc('id')
            ->first();
        if ($loan === null) {
            $out('WARN', 'P1.4 loan-card', 'no loan with installments');
        } else {
            $card = app(LoanCardService::class)->build($loan);
            $out('OK', 'P1.4 loan-card', "loan#{$loan->id} rows=".count($card['rows'])." sisa_pokok={$card['totals']['remaining_principal']}");
        }
    } catch (Throwable $e) {
        $fail++;
        $out('FAIL', 'P1.4 loan-card', $e->getMessage());
    }

    $routes = [
        'accounting.journals.index',
        'accounting.journal-entries.installment.receipt',
        'accounting.reports.cash-flow',
        'accounting.reports.equity-change',
        'accounting.reports.calk',
        'lending.reports.portfolio',
        'lending.reports.schedule-vs-actual',
        'lending.loans.card',
    ];
    foreach ($routes as $name) {
        if (app('router')->has($name)) {
            $out('OK', "route {$name}");
        } else {
            $fail++;
            $out('FAIL', "route {$name}", 'not registered');
        }
    }

    $connections->disconnect();
    $context->clear();
} catch (Throwable $e) {
    $fail++;
    $out('FAIL', 'bootstrap', $e->getMessage());
}

echo PHP_EOL.($fail === 0 ? 'SMOKE PASS' : "SMOKE ISSUES fail={$fail}").PHP_EOL;
exit($fail > 0 ? 1 : 0);
