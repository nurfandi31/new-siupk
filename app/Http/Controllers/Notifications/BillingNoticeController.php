<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Notifications\Services\WhatsappNotificationService;
use App\Services\WhatsappGatewayService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BillingNoticeController
{
    public function __construct(
        private readonly WhatsappNotificationService $notices,
        private readonly WhatsappGatewayService $gateway,
        private readonly PermissionChecker $permissions,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('settings.whatsapp.hub', ['tab' => 'billing']);
    }

    public function send(Request $request, PermissionChecker $permissions): RedirectResponse
    {
        $permissions->denyUnless($request->user(), 'messages.send');

        $validated = $request->validate([
            'due_date' => ['required', 'date_format:Y-m-d'],
            'installment_row_ids' => ['required', 'array', 'min:1'],
            'installment_row_ids.*' => ['integer', 'min:1'],
        ]);

        if (! $this->gateway->isConfigured() || ! $this->gateway->isEnabled()) {
            return back()->with('error', 'WhatsApp gateway belum aktif/terkonfigurasi.');
        }

        $due = CarbonImmutable::createFromFormat('Y-m-d', $validated['due_date'])->startOfDay();
        $result = $this->notices->sendBilling(
            array_map('intval', $validated['installment_row_ids']),
            $due,
        );

        $message = sprintf(
            'Tagihan: %d terkirim, %d gagal, %d dilewati.',
            $result['sent'],
            $result['failed'],
            $result['skipped'],
        );

        if ($result['sent'] > 0 && $result['failed'] === 0) {
            return redirect()
                ->route('settings.whatsapp.hub', ['tab' => 'billing', 'due_date' => $due->toDateString()])
                ->with('success', $message);
        }

        return redirect()
            ->route('settings.whatsapp.hub', ['tab' => 'billing', 'due_date' => $due->toDateString()])
            ->with($result['sent'] > 0 ? 'warning' : 'error', $message);
    }

    private function resolveDueDate(Request $request): CarbonImmutable
    {
        $raw = $request->query('due_date');
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::today();
    }
}
