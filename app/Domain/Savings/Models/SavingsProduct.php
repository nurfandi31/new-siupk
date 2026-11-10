<?php

declare(strict_types=1);

namespace App\Domain\Savings\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master jenis simpanan per tenant (Pokok, Wajib, Sukarela, Berjangka, ...).
 */
final class SavingsProduct extends SavingsModel
{
    public const CATEGORIES = [
        'umum' => 'Simpanan Umum',
        'deposito' => 'Simpanan Deposito',
        'program' => 'Simpanan Program',
        'utang' => 'Utang',
        // Kategori lama tetap dikenali agar data yang sudah ada tidak rusak.
        'pokok' => 'Simpanan Pokok',
        'wajib' => 'Simpanan Wajib',
        'sukarela' => 'Simpanan Sukarela',
        'berjangka' => 'Simpanan Berjangka',
        'lainnya' => 'Simpanan Lainnya',
    ];

    public const FORM_TYPES = [
        1 => 'Tanpa tanggal minimal tutup',
        2 => 'Memakai tanggal minimal tutup',
        3 => 'Program / berjangka',
    ];

    protected function savingsTableKey(): string
    {
        return 'products';
    }

    protected function casts(): array
    {
        return [
            'form_type' => 'integer',
            'interest_rate' => 'decimal:3',
            'tax_rate' => 'decimal:3',
            'admin_fee' => 'decimal:2',
            'opening_fee' => 'decimal:2',
            'minimum_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class, 'product_row_id', 'row_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[(string) $this->category] ?? 'Simpanan Lainnya';
    }

    public function formTypeLabel(): string
    {
        return self::FORM_TYPES[(int) $this->form_type] ?? self::FORM_TYPES[1];
    }
}
