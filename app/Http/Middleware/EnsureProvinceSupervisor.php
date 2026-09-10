<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureProvinceSupervisor
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

        if (! $user->is_superadmin && ! $user->isProvinceUser()) {
            $fallback = $user->tenant_id !== null ? route('dashboard') : route('login');

            return redirect()
                ->to($fallback)
                ->with('error', 'Akses khusus untuk pengguna tingkat Provinsi.');
        }

        $provinceCode = $user->province_code ?: (string) $request->query('province_code', '');

        $shard = null;
        if ($provinceCode !== '') {
            $shard = DatabaseShard::query()
                ->where('province_code', $provinceCode)
                ->where('status', 'active')
                ->first();

            if ($shard === null) {
                $tenant = Tenant::query()
                    ->where('province_code', $provinceCode)
                    ->with('placement.shard')
                    ->first();
                $shard = $tenant?->placement?->shard;
            }
        }

        if ($shard === null) {
            $shard = DatabaseShard::query()->where('status', 'active')->first();
        }

        if ($shard !== null) {
            $this->shardConnectionManager->connect($shard);
            $request->attributes->set('province_shard', $shard);
        }

        return $next($request);
    }
}
