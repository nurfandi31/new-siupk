<?php

declare(strict_types=1);

namespace App\Domain\Savings\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Riwayat satu kali proses bunga untuk periode tertentu (YYYY-MM).
 *
 * Unique (tenant_id, period) di tabel fisik mencegah bunga diposting dua kali
 * pada periode yang sama.
 */
final class SavingsInterestRun extends SavingsModel
{
    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    protected function savingsTableKey(): string
    {
        return 'interest_runs';
    }

    protected function casts(): array
    {
        return [
            'accounts_count' => 'integer',
            'gross_interest' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'admin_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'posted_at' => 'datetime',
            'period_start' => 'date',
            'period_end' => 'date',
            'posting_date' => 'date',
            'method' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class, 'interest_run_row_id', 'row_id');
    }
}
