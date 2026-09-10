<?php

declare(strict_types=1);

namespace App\Domain\Budgeting\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use DomainException;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Budget extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'budget_row_id', 'row_id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function assertEditable(): void
    {
        if (! $this->isEditable()) {
            throw new DomainException('Anggaran tahun ini sudah disetujui dan tidak dapat diubah.');
        }
    }
}
