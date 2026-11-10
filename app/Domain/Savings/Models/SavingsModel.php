<?php

declare(strict_types=1);

namespace App\Domain\Savings\Models;

use App\Domain\Savings\SavingsModule;
use App\Models\Tenant\TenantModel;
use App\Tenancy\TenantContext;

/**
 * Base model untuk seluruh tabel modul simpanan.
 *
 * Nama tabel ditentukan saat runtime dari tenant yang sedang aktif, sehingga
 * setiap lokasi membaca tabelnya sendiri (mis. simp_accounts_t3).
 */
abstract class SavingsModel extends TenantModel
{
    abstract protected function savingsTableKey(): string;

    public function getTable(): string
    {
        return SavingsModule::table($this->savingsTableKey(), app(TenantContext::class)->id());
    }
}
