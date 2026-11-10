<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Savings\Services\SavingsModuleManager;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blokir seluruh halaman simpanan ketika modul belum/tidak diaktifkan untuk
 * tenant yang sedang dipakai. Menu juga disembunyikan lewat shared prop
 * `modules.simpanan`, jadi kondisi ini hanya tercapai lewat URL langsung.
 */
final class EnsureSavingsModule
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SavingsModuleManager $modules,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->isInitialized() || ! $this->modules->isEnabled($this->context->id())) {
            abort(404, 'Modul simpanan tidak aktif untuk lokasi ini.');
        }

        return $next($request);
    }
}
