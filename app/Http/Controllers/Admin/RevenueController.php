<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Platform\Invoice;
use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RevenueController
{
    public function __invoke(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', '');
        $planFilter = $request->query('plan_id');
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;

        // 1. Calculate overall summary statistics
        $allTenants = Tenant::query()
            ->with(['activeSubscription.plan', 'latestInvoice'])
            ->get();

        $today = CarbonImmutable::today();
        $summary = [
            'total_tenants' => $allTenants->count(),
            'tenants_paid' => 0,
            'tenants_pending' => 0,
            'tenants_overdue' => 0,
            'tenants_no_invoice' => 0,
            'total_collected' => (float) (Invoice::query()->where('status', 'paid')->sum('amount_paid') ?? 0.0),
            'total_outstanding' => (float) (Invoice::query()->whereIn('status', ['issued', 'partially_paid', 'overdue'])->selectRaw('SUM(amount - amount_paid) as outstanding')->value('outstanding') ?? 0.0),
        ];

        foreach ($allTenants as $t) {
            $inv = $t->latestInvoice;
            if ($inv === null) {
                $summary['tenants_no_invoice']++;
            } elseif ($inv->status === 'paid') {
                $summary['tenants_paid']++;
            } elseif ($inv->status === 'overdue' || ($inv->due_at && $inv->due_at->isPast())) {
                $summary['tenants_overdue']++;
            } else {
                $summary['tenants_pending']++;
            }
        }

        // 2. Query paginated tenant list with filters
        $query = Tenant::query()
            ->with(['activeSubscription.plan', 'latestInvoice'])
            ->withSum(['invoices as lifetime_paid' => fn ($q) => $q->where('status', 'paid')], 'amount_paid')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('district_code', 'like', "%{$search}%")
                ->orWhereHas('invoices', fn ($iq) => $iq->where('number', 'like', "%{$search}%"))))
            ->when(is_numeric($planFilter), fn ($q) => $q->whereHas('activeSubscription', fn ($sq) => $sq->where('plan_id', (int) $planFilter)));

        // Status filter filtering
        if ($statusFilter === 'paid') {
            $query->whereHas('latestInvoice', fn ($q) => $q->where('status', 'paid'));
        } elseif ($statusFilter === 'pending') {
            $query->whereHas('latestInvoice', fn ($q) => $q->whereIn('status', ['issued', 'partially_paid'])->where(fn ($sub) => $sub->whereNull('due_at')->orWhere('due_at', '>=', $today->toDateString())));
        } elseif ($statusFilter === 'overdue') {
            $query->whereHas('latestInvoice', fn ($q) => $q->where('status', 'overdue')->orWhere(fn ($sub) => $sub->whereIn('status', ['issued', 'partially_paid'])->where('due_at', '<', $today->toDateString())));
        } elseif ($statusFilter === 'no_invoice') {
            $query->doesntHave('latestInvoice');
        }

        $tenants = $query->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Tenant $tenant) use ($today): array {
                $latestInv = $tenant->latestInvoice;
                $activeSub = $tenant->activeSubscription;
                $plan = $activeSub?->plan;

                $paymentStatus = 'no_invoice';
                $statusLabel = 'Belum Ada Tagihan';
                $statusTone = 'neutral';
                $dueInfo = null;

                if ($latestInv !== null) {
                    if ($latestInv->status === 'paid') {
                        $paymentStatus = 'paid';
                        $statusLabel = 'Lunas';
                        $statusTone = 'success';
                        $dueInfo = $latestInv->paid_at ? 'Dibayar pada '.$latestInv->paid_at->format('d/m/Y') : 'Lunas';
                    } elseif ($latestInv->status === 'overdue' || ($latestInv->due_at && $latestInv->due_at->isPast())) {
                        $paymentStatus = 'overdue';
                        $statusLabel = 'Jatuh Tempo (Overdue)';
                        $statusTone = 'error';
                        $daysOverdue = $latestInv->due_at ? (int) $latestInv->due_at->diffInDays($today, false) : 0;
                        $dueInfo = $daysOverdue > 0 ? "Terlambat {$daysOverdue} hari" : 'Jatuh tempo hari ini';
                    } else {
                        $paymentStatus = 'pending';
                        $statusLabel = 'Menunggu Pembayaran';
                        $statusTone = 'warning';
                        $daysLeft = $latestInv->due_at ? (int) $today->diffInDays($latestInv->due_at, false) : null;
                        $dueInfo = $daysLeft !== null ? ($daysLeft === 0 ? 'Jatuh tempo hari ini' : "Sisa {$daysLeft} hari lagi") : 'Menunggu pembayaran';
                    }
                } elseif ($activeSub?->status === 'trialing') {
                    $paymentStatus = 'trialing';
                    $statusLabel = 'Masa Percobaan';
                    $statusTone = 'info';
                    $dueInfo = $activeSub->ends_at ? 'Trial s.d. '.$activeSub->ends_at->format('d/m/Y') : 'Trial aktif';
                }

                return [
                    'row_id' => $tenant->row_id,
                    'code' => $tenant->code,
                    'name' => $tenant->name,
                    'district_code' => $tenant->district_code,
                    'tenant_status' => $tenant->status,
                    'plan' => $plan ? [
                        'row_id' => $plan->row_id,
                        'name' => $plan->name,
                        'price_amount' => (float) $plan->price_amount,
                        'billing_period' => $plan->billing_period,
                        'currency' => $plan->currency,
                    ] : null,
                    'subscription_status' => $activeSub?->status,
                    'payment_status' => $paymentStatus,
                    'payment_status_label' => $statusLabel,
                    'payment_status_tone' => $statusTone,
                    'due_info' => $dueInfo,
                    'latest_invoice' => $latestInv ? [
                        'row_id' => $latestInv->row_id,
                        'number' => $latestInv->number,
                        'status' => $latestInv->status,
                        'amount' => (float) $latestInv->amount,
                        'amount_paid' => (float) $latestInv->amount_paid,
                        'remaining' => (float) $latestInv->remainingAmount(),
                        'currency' => $latestInv->currency,
                        'issued_at' => $latestInv->issued_at?->format('d/m/Y'),
                        'due_at' => $latestInv->due_at?->format('d/m/Y'),
                        'paid_at' => $latestInv->paid_at?->format('d/m/Y'),
                    ] : null,
                    'lifetime_paid' => (float) ($tenant->lifetime_paid ?? 0.0),
                ];
            });

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['row_id', 'name', 'price_amount', 'billing_period']);

        return Inertia::render('Admin/Revenue/Index', [
            'tenants' => $tenants,
            'summary' => $summary,
            'plans' => $plans,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
                'plan_id' => $planFilter ? (string) $planFilter : '',
                'per_page' => $perPage,
            ],
        ]);
    }
}
