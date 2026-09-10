<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Dashboard\Services\DashboardService;
use App\Models\Platform\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    private const PIPELINE_MODAL_KEYS = ['proposal', 'verifikasi', 'waiting', 'aktif'];

    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $membership = $user?->memberships()
            ->with('tenant')
            ->where('status', 'active')
            ->orderBy('row_id')
            ->first();

        $payload = $this->dashboard->build();

        $pipelineKey = $this->resolvePipelineKey($request);
        $pipelineModal = $pipelineKey !== null
            ? $this->dashboard->loansByStatus($pipelineKey)
            : null;

        $unpaidInvoice = null;
        $tenant = $membership?->tenant ?? $user?->tenant;
        if ($tenant !== null) {
            $inv = Invoice::query()
                ->where('tenant_id', $tenant->row_id)
                ->whereIn('status', ['issued', 'pending_payment', 'overdue'])
                ->orderByRaw("CASE WHEN status = 'overdue' THEN 0 ELSE 1 END")
                ->oldest('due_at')
                ->first();

            if ($inv !== null) {
                $unpaidInvoice = [
                    'row_id' => $inv->row_id,
                    'number' => $inv->number,
                    'amount' => (float) $inv->amount,
                    'remaining' => (float) $inv->remainingAmount(),
                    'status' => $inv->status,
                    'blocks_access' => (bool) $inv->blocks_access,
                    'is_blocking' => $inv->isBlockingAccess(),
                    'due_at' => $inv->due_at?->toDateString(),
                    'due_at_formatted' => $inv->due_at?->format('d M Y'),
                    'is_overdue' => $inv->status === 'overdue' || ($inv->due_at && $inv->due_at->isPast()),
                    'purpose' => $inv->purpose,
                    'target_url' => "/billing/invoices/{$inv->row_id}",
                ];
            }
        }

        return Inertia::render('Dashboard', [
            'unitName' => $membership?->tenant?->name ?? $payload['unit_name'],
            ...$payload,
            'pipeline_modal' => $pipelineModal,
            'pipeline_modal_key' => $pipelineKey,
            'unpaid_invoice' => $unpaidInvoice,
        ]);
    }

    private function resolvePipelineKey(Request $request): ?string
    {
        $key = (string) $request->query('pipeline', '');
        if ($key === '' || ! in_array($key, self::PIPELINE_MODAL_KEYS, true)) {
            return null;
        }

        return $key;
    }
}
