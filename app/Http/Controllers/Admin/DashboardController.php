<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Platform\Invoice;
use App\Models\Platform\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(): Response
    {
        $now = CarbonImmutable::now();
        $startOfMonth = $now->startOfMonth();
        $endOfMonth = $now->endOfMonth();
        $startOfLastMonth = $now->subMonth()->startOfMonth();
        $endOfLastMonth = $now->subMonth()->endOfMonth();
        $startOfYear = $now->startOfYear();
        $year = (int) $now->year;

        // 1. Monthly revenue trend for chart (12 months of current year) - Database Agnostic
        $invoicesOfYear = Invoice::query()
            ->where('issued_at', '>=', $startOfYear)
            ->where('status', '!=', 'void')
            ->get(['amount', 'amount_paid', 'issued_at']);

        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $chartData = collect(range(1, 12))->map(function (int $m) use ($invoicesOfYear, $monthLabels) {
            $monthInvoices = $invoicesOfYear->filter(
                fn (Invoice $inv): bool => $inv->issued_at !== null && (int) $inv->issued_at->month === $m
            );

            return [
                'key' => $m,
                'label' => $monthLabels[$m - 1],
                'disbursed' => (float) $monthInvoices->sum('amount'),
                'collected' => (float) $monthInvoices->sum('amount_paid'),
            ];
        })->values()->all();

        // 2. Business metrics & KPIs
        $revenueThisMonth = (float) (Invoice::query()
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->where('status', 'paid')
            ->sum('amount_paid') ?? 0.0);

        $revenueLastMonth = (float) (Invoice::query()
            ->whereBetween('paid_at', [$startOfLastMonth, $endOfLastMonth])
            ->where('status', 'paid')
            ->sum('amount_paid') ?? 0.0);

        $revenueGrowth = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : 0.0;

        $invoicedThisMonth = (float) (Invoice::query()
            ->whereBetween('issued_at', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'void')
            ->sum('amount') ?? 0.0);

        $revenueYtd = (float) (Invoice::query()
            ->where('issued_at', '>=', $startOfYear)
            ->where('status', 'paid')
            ->sum('amount_paid') ?? 0.0);

        $invoicedYtd = (float) (Invoice::query()
            ->where('issued_at', '>=', $startOfYear)
            ->where('status', '!=', 'void')
            ->sum('amount') ?? 0.0);

        $openInvoicesQuery = Invoice::query()
            ->whereIn('status', ['issued', 'partially_paid', 'overdue']);

        $totalOutstanding = (float) ($openInvoicesQuery->get()->sum(fn (Invoice $i): float => (float) $i->amount - (float) $i->amount_paid));

        $overdueOutstanding = (float) (Invoice::query()
            ->where('status', 'overdue')
            ->get()
            ->sum(fn (Invoice $i): float => (float) $i->amount - (float) $i->amount_paid));

        $tenantsTotal = Tenant::query()->count();
        $tenantsActive = Tenant::query()->where('status', 'active')->count();
        $tenantsSuspended = Tenant::query()->where('status', 'suspended')->count();
        $tenantsProvisioning = Tenant::query()->whereIn('status', ['provisioning', 'provisioning_failed'])->count();

        // 3. Open invoices requiring attention
        $openInvoices = Invoice::query()
            ->with(['tenant:row_id,name,code', 'subscription.plan:row_id,name'])
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->orderBy('due_at')
            ->limit(8)
            ->get(['row_id', 'number', 'tenant_id', 'subscription_id', 'status', 'amount', 'amount_paid', 'due_at', 'issued_at', 'currency']);

        // 4. Recent tenants with subscription plan
        $recentTenants = Tenant::query()
            ->with(['activeSubscription.plan:row_id,name,price_amount,billing_period'])
            ->latest('row_id')
            ->limit(5)
            ->get(['row_id', 'code', 'name', 'status', 'district_code', 'provisioned_at']);

        return Inertia::render('Admin/Dashboard', [
            'kpis' => [
                'revenue_this_month' => $revenueThisMonth,
                'revenue_last_month' => $revenueLastMonth,
                'revenue_growth' => $revenueGrowth,
                'invoiced_this_month' => $invoicedThisMonth,
                'revenue_ytd' => $revenueYtd,
                'invoiced_ytd' => $invoicedYtd,
                'total_outstanding' => $totalOutstanding,
                'overdue_outstanding' => $overdueOutstanding,
                'tenants_total' => $tenantsTotal,
                'tenants_active' => $tenantsActive,
                'tenants_suspended' => $tenantsSuspended,
                'tenants_provisioning' => $tenantsProvisioning,
                'users_total' => User::query()->whereNotNull('tenant_id')->count(),
                'invoices_open_count' => Invoice::query()->whereIn('status', ['issued', 'partially_paid', 'overdue'])->count(),
                'invoices_overdue_count' => Invoice::query()->where('status', 'overdue')->count(),
            ],
            'chart' => [
                'year' => $year,
                'data' => $chartData,
            ],
            'recent_tenants' => $recentTenants,
            'open_invoices' => $openInvoices,
        ]);
    }
}
