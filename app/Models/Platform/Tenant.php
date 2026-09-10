<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Tenant extends PlatformModel
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_training_mode' => 'boolean',
            'training_started_at' => 'datetime',
            'training_ended_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'suspended_at' => 'datetime',
            'map_latitude' => 'float',
            'map_longitude' => 'float',
            'map_zoom' => 'integer',
        ];
    }

    public function isTraining(): bool
    {
        return (bool) ($this->is_training_mode ?? false);
    }

    public function hasCompletedTraining(): bool
    {
        return $this->training_ended_at !== null && ! $this->isTraining();
    }

    public function placement(): HasOne
    {
        return $this->hasOne(TenantPlacement::class, 'tenant_id', 'row_id')
            ->whereIn('status', ['active', 'read_only', 'switching']);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'tenant_id', 'row_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'tenant_id', 'row_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'tenant_id', 'row_id');
    }

    public function latestInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'tenant_id', 'row_id')
            ->where('status', '!=', 'void')
            ->latestOfMany('row_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'tenant_id', 'row_id')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->latestOfMany('row_id');
    }

    /**
     * Whether this tenant claims the given host through its metadata domain
     * list — exact match, or a `*.example.com` wildcard subdomain pattern.
     */
    public function matchesHost(string $host): bool
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $domains = $metadata['domains'] ?? ($metadata['domain'] ?? []);
        $domains = is_array($domains) ? $domains : [$domains];

        foreach ($domains as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === '') {
                continue;
            }
            if ($pattern === $host) {
                return true;
            }
            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1);
                if (str_ends_with($host, $suffix) && $host !== ltrim($suffix, '.')) {
                    return true;
                }
            }
        }

        return false;
    }
}
