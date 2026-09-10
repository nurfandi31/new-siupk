<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class OfflineAccessService
{
    public const KEY_ENABLED = 'offline.access.enabled';

    public const KEY_USER_ID = 'offline.access.user_id';

    public function __construct() {}

    public function isEnabled(int $tenantId): bool
    {
        return (bool) $this->readTenantSetting($tenantId, self::KEY_ENABLED, false);
    }

    public function userId(int $tenantId): ?int
    {
        $value = $this->readTenantSetting($tenantId, self::KEY_USER_ID, null);

        return $value === null ? null : (int) $value;
    }

    public function isUserAllowed(int $tenantId, ?int $userId): bool
    {
        return $userId !== null
            && $this->isEnabled($tenantId)
            && $this->userId($tenantId) === $userId;
    }

    public function save(int $tenantId, bool $isEnabled, ?int $userId): void
    {
        if ($isEnabled && $userId === null) {
            throw new InvalidArgumentException('Pengguna offline wajib dipilih.');
        }

        if ($isEnabled && ! $this->activeTenantUserExists($tenantId, (int) $userId)) {
            throw new InvalidArgumentException('Pengguna offline harus aktif dan terdaftar pada tenant ini.');
        }

        $this->writeTenantSetting($tenantId, self::KEY_ENABLED, $isEnabled ? '1' : '0', 'boolean');
        $this->writeTenantSetting($tenantId, self::KEY_USER_ID, $isEnabled ? $userId : null, 'int');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function selectableUsers(int $tenantId): array
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['row_id', 'name', 'username', 'email'])
            ->map(fn (User $user): array => [
                'row_id' => (int) $user->row_id,
                'name' => (string) $user->name,
                'username' => (string) $user->username,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }

    private function activeTenantUserExists(int $tenantId, int $userId): bool
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('row_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    private function readTenantSetting(int $tenantId, string $key, mixed $default): mixed
    {
        $row = DB::connection('tenant')->table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->first(['value', 'value_type']);

        if ($row === null) {
            return $default;
        }

        return match ((string) $row->value_type) {
            'bool', 'boolean' => in_array(strtolower((string) $row->value), ['1', 'true', 'yes', 'on'], true),
            'int', 'integer' => $row->value === null ? null : (int) $row->value,
            default => $row->value,
        };
    }

    private function writeTenantSetting(int $tenantId, string $key, mixed $value, string $type): void
    {
        DB::connection('tenant')->table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'key' => $key],
            [
                'value' => $value,
                'value_type' => $type,
                'is_encrypted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
