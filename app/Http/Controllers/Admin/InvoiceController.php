<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Models\Platform\Invoice;
use App\Models\Platform\Subscription;
use App\Models\Platform\Tenant;
use App\Services\Admin\AuditLogger;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceController
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;
        $sort = in_array($request->query('sort'), ['number', 'due_at', 'amount', 'status', 'issued_at'], true)
            ? (string) $request->query('sort')
            : 'row_id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $invoices = Invoice::query()
            ->with('tenant:row_id,name,code')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('tenant', fn ($t) => $t
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"))))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => [
                'row_id' => $invoice->row_id,
                'number' => $invoice->number,
                'purpose' => $invoice->purpose,
                'status' => $invoice->status,
                'blocks_access' => (bool) $invoice->blocks_access,
                'amount' => $invoice->amount,
                'amount_paid' => $invoice->amount_paid,
                'currency' => $invoice->currency,
                'due_at' => $invoice->due_at?->toDateString(),
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
                'description' => $invoice->description,
                'tenant' => $invoice->tenant?->only(['row_id', 'name', 'code']),
            ]);

        return Inertia::render('Admin/Invoices/Index', compact('invoices', 'search', 'status', 'perPage', 'sort', 'direction'));
    }

    public function create(Request $request): Response
    {
        $tenantId = $request->query('tenant_id');

        return Inertia::render('Admin/Invoices/Create', [
            'tenants' => Tenant::query()->orderBy('name')->get(['row_id', 'code', 'name']),
            'selected_tenant_id' => $tenantId ? (int) $tenantId : null,
            'subscriptions' => Subscription::query()
                ->with('plan:row_id,name')
                ->when($tenantId, fn ($q) => $q->where('tenant_id', (int) $tenantId))
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->latest('row_id')
                ->limit(50)
                ->get(['row_id', 'tenant_id', 'plan_id', 'status', 'starts_at', 'ends_at']),
        ]);
    }

    public function store(StoreInvoiceRequest $request, InvoiceService $invoices): RedirectResponse
    {
        $data = $request->validated();
        $tenant = Tenant::query()->findOrFail($data['tenant_id']);
        $invoice = $invoices->create($tenant, $data, $request->user());

        return to_route('admin.invoices.show', $invoice)->with('success', 'Invoice diterbitkan.');
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load([
            'tenant:row_id,code,name',
            'subscription.plan:row_id,code,name',
            'payments' => fn ($q) => $q->latest('row_id'),
            'creator:row_id,name',
        ]);

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => [
                'row_id' => $invoice->row_id,
                'public_id' => $invoice->public_id,
                'number' => $invoice->number,
                'purpose' => $invoice->purpose,
                'status' => $invoice->status,
                'blocks_access' => (bool) $invoice->blocks_access,
                'amount' => $invoice->amount,
                'amount_paid' => $invoice->amount_paid,
                'remaining' => $invoice->remainingAmount(),
                'currency' => $invoice->currency,
                'description' => $invoice->description,
                'notes' => $invoice->notes,
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
                'due_at' => $invoice->due_at?->toDateString(),
                'paid_at' => $invoice->paid_at?->toDateTimeString(),
                'tenant' => $invoice->tenant?->only(['row_id', 'code', 'name']),
                'subscription' => $invoice->subscription ? [
                    'row_id' => $invoice->subscription->row_id,
                    'status' => $invoice->subscription->status,
                    'plan' => $invoice->subscription->plan?->only(['code', 'name']),
                ] : null,
                'creator' => $invoice->creator?->only(['name']),
                'is_open' => $invoice->isOpen() && $invoice->status !== 'draft',
            ],
            'payments' => $invoice->payments->map(fn ($p) => [
                'row_id' => $p->row_id,
                'method' => $p->method,
                'status' => $p->status,
                'amount' => $p->amount,
                'paid_at' => $p->paid_at?->toDateTimeString(),
                'reference' => $p->reference,
                'tripay_reference' => $p->tripay_reference,
                'tripay_checkout_url' => $p->tripay_checkout_url,
                'notes' => $p->notes,
            ]),
        ]);
    }

    public function void(Invoice $invoice, InvoiceService $invoices, AuditLogger $audit): RedirectResponse
    {
        $invoices->void($invoice);

        $audit->record(
            'invoice.void',
            $invoice->tenant,
            Invoice::class,
            $invoice->row_id,
            sprintf('Invoice [%s] dibatalkan.', $invoice->number),
            ['amount' => (float) $invoice->amount],
        );

        return back()->with('success', 'Invoice dibatalkan.');
    }

    public function toggleBlocking(Invoice $invoice, AuditLogger $audit): RedirectResponse
    {
        $invoice->blocks_access = ! $invoice->blocks_access;
        $invoice->save();

        $statusText = $invoice->blocks_access ? 'diaktifkan (akses tenant diblokir sampai lunas)' : 'dinonaktifkan';

        $audit->record(
            'invoice.toggle_blocking',
            $invoice->tenant,
            Invoice::class,
            $invoice->row_id,
            sprintf('Blokir akses invoice [%s] %s.', $invoice->number, $statusText),
            ['blocks_access' => (bool) $invoice->blocks_access],
        );

        return back()->with('success', "Opsi blokir akses invoice {$invoice->number} {$statusText}.");
    }
}
