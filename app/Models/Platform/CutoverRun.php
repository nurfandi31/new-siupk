<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CutoverRun extends PlatformModel
{
    protected $table = 'platform_cutover_runs';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'is_dry_run' => 'boolean',
        'options' => 'array',
        'steps' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'row_id');
    }
}
