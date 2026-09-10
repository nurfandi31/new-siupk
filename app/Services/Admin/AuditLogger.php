<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Platform\AuditLog;
use App\Models\Platform\Tenant;
use Illuminate\Http\Request;

/**
 * Pencatat jejak audit untuk aksi sensitif panel superadmin.
 *
 * Semua penulisan sengaja tidak pernah melempar exception ke request —
 * kegagalan logging di-report saja agar aksi utama tetap berhasil.
 */
final class AuditLogger
{
    public function record(
        string $action,
        ?Tenant $tenant = null,
        ?string $subjectType = null,
        mixed $subjectId = null,
        string $description = '',
        array $properties = [],
    ): void {
        try {
            /** @var Request|null $request */
            $request = app()->bound('request') ? app('request') : null;

            $actor = auth()->user();

            AuditLog::query()->create([
                'actor_id' => $actor?->getAuthIdentifier(),
                'actor_type' => $actor !== null ? ($actor->is_superadmin ? 'superadmin' : 'user') : 'system',
                'actor_name' => $actor?->name,
                'tenant_id' => $tenant?->row_id,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId !== null ? (int) $subjectId : null,
                'description' => $description,
                'properties' => $properties ?: null,
                'ip_address' => $request instanceof Request ? $request->ip() : null,
                'user_agent' => $request instanceof Request ? substr((string) $request->userAgent(), 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $before  Nilai atribut sebelum perubahan
     * @param  array<string, mixed>  $after  Nilai atribut sesudah perubahan
     * @return array<string, array{old: mixed, new: mixed}> Hanya key yang berubah
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;
            if ($old != $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        }

        return $changes;
    }
}
