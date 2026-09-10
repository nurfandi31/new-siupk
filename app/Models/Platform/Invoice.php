<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Invoice extends PlatformModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'metadata' => 'array',
            'blocks_access' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'row_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'row_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'row_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class, 'invoice_id', 'row_id');
    }

    public function remainingAmount(): string
    {
        return bcsub((string) $this->amount, (string) $this->amount_paid, 2);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['draft', 'issued', 'partially_paid', 'overdue', 'pending_payment'], true);
    }

    public function isBlockingAccess(): bool
    {
        return (bool) $this->blocks_access && $this->isOpen() && $this->status !== 'draft';
    }
}
