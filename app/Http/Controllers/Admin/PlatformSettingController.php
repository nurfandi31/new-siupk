<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform\PlatformSetting;
use App\Services\Admin\AuditLogger;
use App\Services\PlatformSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformSettingController extends Controller
{
    /** Key yang nilainya dianggap rahasia — ditampilkan ter-masker & disimpan terenkripsi. */
    private const SENSITIVE_PATTERN = '/(secret|api_key|private_key|token|password)/i';

    public function __construct(private readonly PlatformSettingService $settings) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 30;

        $rows = DB::connection('platform')->table('platform_settings')
            ->when($search !== '', fn ($q) => $q->where('key', 'like', "%{$search}%"))
            ->orderBy('key')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row): array => [
                'key' => $row->key,
                'value_type' => $row->value_type,
                'value' => $this->displayValue($row->key, $row->value),
                'is_sensitive' => $this->isSensitive($row->key),
                'has_value' => $row->value !== null && $row->value !== '',
                'updated_at' => $row->updated_at !== null ? Carbon::parse($row->updated_at)->format('d/m/Y H:i') : null,
            ]);

        // Filter sensitif dilakukan di PHP agar portabel (SQLite tidak punya REGEXP).
        $total = DB::connection('platform')->table('platform_settings')->count();
        $sensitiveCount = DB::connection('platform')->table('platform_settings')
            ->pluck('key')
            ->filter(fn ($key): bool => $this->isSensitive((string) $key))
            ->count();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $rows,
            'search' => $search,
            'perPage' => $perPage,
            'summary' => [
                'total' => $total,
                'sensitive' => $sensitiveCount,
            ],
        ]);
    }

    /**
     * Update satu setting: nilai dikirim sebagai string mentah lalu dikonversi
     * sesuai value_type yang dipilih. Key sensitif otomatis dienkripsi.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'value' => ['nullable', 'string'],
            'value_type' => ['required', 'in:string,int,bool,json,float'],
        ]);

        $key = trim($validated['key']);
        $raw = $validated['value'] ?? '';

        // Validasi JSON sebelum menyimpan agar tidak menulis nilai rusak.
        if ($validated['value_type'] === 'json' && trim($raw) !== '') {
            json_decode($raw);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->with('error', 'Nilai JSON tidak valid: '.json_last_error_msg());
            }
        }

        $value = match ($validated['value_type']) {
            'bool' => in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true),
            'int' => trim($raw) === '' ? null : (int) $raw,
            'float' => trim($raw) === '' ? null : (float) str_replace(',', '.', trim($raw)),
            default => $raw,
        };

        $before = $this->settings->get($key);

        if ($this->isSensitive($key)) {
            $this->settings->setEncrypted($key, $raw === '' ? null : $raw);
        } else {
            $this->settings->set($key, $value === null ? null : $value, $validated['value_type']);
        }
        $this->settings->flush();

        app(AuditLogger::class)->record(
            'platform_setting.update',
            subjectType: PlatformSetting::class,
            description: sprintf('Setting platform [%s] diperbarui.', $key),
            properties: ['changes' => AuditLogger::diff(
                ['value' => $this->maskForAudit($key, $before)],
                ['value' => $this->maskForAudit($key, $this->settings->get($key))],
            )],
        );

        return back()->with('success', "Setting [{$key}] tersimpan.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $key = trim((string) $request->input('key', ''));

        if ($key === '') {
            return back()->with('error', 'Key setting wajib diisi.');
        }

        $existed = DB::connection('platform')->table('platform_settings')->where('key', $key)->exists();
        if (! $existed) {
            return back()->with('error', "Setting [{$key}] tidak ditemukan.");
        }

        DB::connection('platform')->table('platform_settings')->where('key', $key)->delete();
        $this->settings->flush();

        app(AuditLogger::class)->record(
            'platform_setting.delete',
            subjectType: PlatformSetting::class,
            description: sprintf('Setting platform [%s] dihapus.', $key),
        );

        return back()->with('success', "Setting [{$key}] dihapus.");
    }

    private function isSensitive(string $key): bool
    {
        return preg_match(self::SENSITIVE_PATTERN, $key) === 1;
    }

    /** Nilai sensitif jangan pernah dikirim ke browser — cukup penanda bahwa nilainya ada. */
    private function displayValue(string $key, ?string $stored): string
    {
        if ($this->isSensitive($key)) {
            return $stored !== null && $stored !== '' ? '••••••••' : '';
        }

        return (string) ($stored ?? '');
    }

    private function maskForAudit(string $key, mixed $value): mixed
    {
        return $this->isSensitive($key) ? '[terenkripsi]' : $value;
    }
}
