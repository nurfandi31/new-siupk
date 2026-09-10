<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use DomainException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class JournalLine extends TenantModel
{
    use HasTenantLocalId;

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $line): void {
            $status = JournalEntry::query()
                ->whereKey($line->journal_entry_row_id)
                ->value('status');

            if ($status === 'posted') {
                throw new DomainException('Lines of a posted journal entry are immutable.');
            }
        };

        self::updating($guard);
        self::deleting($guard);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_row_id', 'row_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_row_id', 'row_id');
    }
}
