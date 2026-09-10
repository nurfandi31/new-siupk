<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use DomainException;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class JournalEntry extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $entry): void {
            if ($entry->getOriginal('status') === 'posted') {
                throw new DomainException('A posted journal entry is immutable. Create a reversal instead.');
            }
        });

        self::deleting(function (self $entry): void {
            if ($entry->status === 'posted') {
                throw new DomainException('A posted journal entry cannot be deleted. Create a reversal instead.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_row_id', 'row_id')
            ->orderBy('line_number');
    }
}
