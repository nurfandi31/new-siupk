<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Billing\Services\SubscriptionGateService;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureSubscriptionActive
{
    public function __construct(
        private TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();
        if ($tenant === null) {
            return $next($request);
        }

        // Jangan blokir rute billing, profile, auth, webhook, atau admin.
        // Middleware ini hanya dipasang pada grup web tenant dan API mobile
        // yang sudah terautentikasi, sehingga endpoint publik/desktop tidak
        // ikut terkena.
        if ($request->routeIs('billing.*') || $request->routeIs('profile.*') || $request->is('logout') || $request->routeIs('api.v1.mobile.auth.logout') || $request->routeIs('admin.*')) {
            return $next($request);
        }

        $gate = app(SubscriptionGateService::class)->check((int) $tenant->row_id);

        if ($gate['blocked'] && $gate['invoice_id'] !== null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $gate['message'],
                    'invoice_id' => $gate['invoice_id'],
                    'status' => 'blocked',
                ], 402);
            }

            return redirect()->route('billing.invoices.show', ['invoice' => $gate['invoice_id'], 'locked' => 1])
                ->with('error', "Akses fitur operasional ditangguhkan karena tagihan #{$gate['invoice_number']} mewajibkan pelunasan sebelum dapat melanjutkan aktivitas. Silakan lakukan pembayaran tagihan.");
        }

        if ($gate['blocked']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $gate['message'],
                    'invoice_id' => null,
                    'status' => 'blocked',
                ], 402);
            }

            return redirect()->route('billing.invoices.index', ['locked' => 1])
                ->with('error', 'Akses fitur operasional dibatasi karena langganan Anda ditangguhkan/menunggak. Silakan selesaikan pembayaran tagihan untuk mengaktifkan kembali.');
        }

        return $next($request);
    }
}
