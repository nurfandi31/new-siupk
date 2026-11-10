<?php

declare(strict_types=1);

namespace App\Domain\Savings\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris mutasi simpanan. balance_after menyimpan saldo berjalan setelah
 * mutasi diterapkan sehingga histori bisa diaudit tanpa menghitung ulang.
 */
final class SavingsTransaction extends SavingsModel
{
    public const CODE_OPENING = 1;

    public const CODE_DEPOSIT = 2;

    public const CODE_WITHDRAWAL = 3;

    public const CODE_INTEREST = 4;

    public const CODE_ADMIN = 5;

    public const CODE_ADJUSTMENT_CREDIT = 8;

    public const CODE_ADJUSTMENT_DEBIT = 9;

    public const CODE_TAX = 10;

    public const CODE_CLOSE = 14;

    public const TYPE_DEPOSIT = 'setoran';

    public const TYPE_WITHDRAWAL = 'penarikan';

    public const TYPE_INTEREST = 'bunga';

    public const TYPE_TAX = 'pajak';

    public const TYPE_ADMIN = 'admin';

    public const TYPE_ADJUSTMENT = 'koreksi';

    public const TYPE_OPENING = 'saldo_awal';

    public const TYPES = [
        self::TYPE_DEPOSIT => 'Setoran',
        self::TYPE_WITHDRAWAL => 'Penarikan',
        self::TYPE_INTEREST => 'Bunga',
        self::TYPE_TAX => 'Pajak Bunga',
        self::TYPE_ADMIN => 'Biaya Admin',
        self::TYPE_ADJUSTMENT => 'Koreksi',
        self::TYPE_OPENING => 'Setoran Awal',
    ];

    protected function savingsTableKey(): string
    {
        return 'transactions';
    }

    protected function casts(): array
    {
        return [
            'code' => 'integer',
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'account_row_id', 'row_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[(string) $this->type] ?? ucfirst((string) $this->type);
    }

    public function isInflow(): bool
    {
        return $this->direction === 'in';
    }
}
