<?php

declare(strict_types=1);

namespace App\Domain\Supervisor\Models;

use App\Models\Tenant\TenantModel;
use App\Models\User;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupervisorNote extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const CATEGORY_KEUANGAN = 'keuangan';

    public const CATEGORY_OPERASIONAL = 'operasional';

    public const CATEGORY_KEPATUHAN = 'kepatuhan';

    public const CATEGORY_STRATEGIS = 'strategis';

    public const CATEGORIES = [
        self::CATEGORY_KEUANGAN,
        self::CATEGORY_OPERASIONAL,
        self::CATEGORY_KEPATUHAN,
        self::CATEGORY_STRATEGIS,
    ];

    public const CATEGORY_LABELS = [
        self::CATEGORY_KEUANGAN => 'Keuangan',
        self::CATEGORY_OPERASIONAL => 'Operasional',
        self::CATEGORY_KEPATUHAN => 'Kepatuhan',
        self::CATEGORY_STRATEGIS => 'Strategis',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id', 'row_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id', 'row_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isAcknowledged(): bool
    {
        return $this->status === self::STATUS_ACKNOWLEDGED;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? (string) $this->category;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Disubmit',
            self::STATUS_ACKNOWLEDGED => 'Disetujui Manajemen',
            default => (string) $this->status,
        };
    }
}
