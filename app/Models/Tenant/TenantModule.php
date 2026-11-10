<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;

/**
 * Status modul opsional (mis. simpanan) untuk tenant pada shard aktif.
 *
 * Row ini hanya menyimpan status + konfigurasi kecil. Tabel data modul dibuat
 * dan dihapus secara dinamis oleh service domain masing-masing modul.
 */
final class TenantModule extends TenantModel
{
    protected $table = 'tenant_modules';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings' => 'array',
            'tables' => 'array',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function scopeCode(Builder $query, string $code): Builder
    {
        return $query->where('module_code', $code);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
