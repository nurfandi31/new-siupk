<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRegencySupervisor
{
    public function __construct(
        private readonly ShardConnectionManager $shardConnectionManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if (! $user->is_superadmin && ! $user->isRegencyUser()) {
            $fallback = $user->tenant_id !== null ? route('dashboard') : route('login');

            return redirect()
                ->to($fallback)
                ->with('error', 'Akses khusus untuk pengguna tingkat Kabupaten.');
        }

        // Resolve regency shard
        $regencyCode = $user->regency_code ?: (string) $request->query('regency_code', '');

        $shard = null;
        if ($regencyCode !== '') {
            $shard = DatabaseShard::query()
                ->where('regency_code', $regencyCode)
                ->where('status', 'active')
                ->first();

            // If no shard matched by explicit regency_code, try shard where tenants belong to that regency
            if ($shard === null) {
                $tenant = Tenant::query()
                    ->where('regency_code', $regencyCode)
                    ->with('placement.shard')
                    ->first();
                $shard = $tenant?->placement?->shard;
            }
        }

        if ($shard === null) {
            // Default to local/first active shard
            $shard = DatabaseShard::query()->where('status', 'active')->first();
        }

        if ($shard !== null) {
            $this->shardConnectionManager->connect($shard);
            $request->attributes->set('regency_shard', $shard);
        }

        return $next($request);
    }
}
