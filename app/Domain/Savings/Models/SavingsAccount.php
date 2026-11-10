<?php

declare(strict_types=1);

namespace App\Domain\Savings\Models;

use App\Domain\Membership\Models\Member;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rekening simpanan satu anggota pada satu jenis simpanan.
 *
 * Saldo disimpan sebagai decimal(18,2) dan selalu dihitung ulang dari mutasi
 * (balance_after) agar tidak pernah menyimpang seperti data legacy.
 */
final class SavingsAccount extends SavingsModel
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected function savingsTableKey(): string
    {
        return 'accounts';
    }

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'interest_rate' => 'decimal:3',
            'tax_rate' => 'decimal:3',
            'admin_fee' => 'decimal:2',
            'opening_fee' => 'decimal:2',
            'authority_type' => 'integer',
            'opened_at' => 'date',
            'minimum_closing_date' => 'date',
            'closed_at' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_row_id', 'row_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SavingsProduct::class, 'product_row_id', 'row_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class, 'account_row_id', 'row_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
