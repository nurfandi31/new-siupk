<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Platform-scoped (not tenant-scoped) key/value config store.
 *
 * Use for installation-wide settings that apply to all tenants in this
 * siupk deployment — e.g. orchestrator base URL & shared secret.
 * Per-tenant overrides go to {@see TenantSettingService} instead.
 */
final class PlatformSettingService
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $row = DB::connection('platform')->table('platform_settings')
            ->where('key', $key)
            ->first(['value', 'value_type']);

        if ($row === null) {
            return $this->cache[$key] = $default;
        }

        return $this->cache[$key] = $this->decode($row->value, $row->value_type);
    }

    public function has(string $key): bool
    {
        return $this->get($key, null) !== null;
    }

    public function set(string $key, mixed $value, string $valueType = 'string'): void
    {
        $encoded = $this->encode($value, $valueType);

        DB::connection('platform')->table('platform_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $encoded,
                'value_type' => $valueType,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->cache[$key] = $value;
    }

    public function getEncrypted(string $key, mixed $default = null): ?string
    {
        $stored = $this->get($key, null);
        if ($stored === null || $stored === '') {
            return is_string($default) ? $default : null;
        }

        try {
            return Crypt::decryptString((string) $stored);
        } catch (\Throwable) {
            return is_string($default) ? $default : null;
        }
    }

    public function setEncrypted(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            $this->set($key, null);

            return;
        }

        $this->set($key, Crypt::encryptString($value), 'string');
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    private function decode(?string $raw, string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => $raw === null ? null : (int) $raw,
            'bool', 'boolean' => $raw === null ? null : in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true),
            'json', 'array' => $raw === null ? null : json_decode($raw, true),
            'float', 'double', 'decimal' => $raw === null ? null : (float) $raw,
            default => $raw,
        };
    }

    private function encode(mixed $value, string $valueType): ?string
    {
        return match ($valueType) {
            'json', 'array' => $value === null ? null : json_encode($value),
            'bool', 'boolean' => $value === null ? null : ($value ? '1' : '0'),
            default => $value === null ? null : (is_scalar($value) ? (string) $value : json_encode($value)),
        };
    }
}
