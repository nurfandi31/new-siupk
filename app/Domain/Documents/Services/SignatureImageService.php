<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Services\TenantSettingService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class SignatureImageService
{
    public const SETTING_KEY = 'signatures.images';

    private const DISK = 'public';

    private const MIME_MAP = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private readonly TenantSettingService $settings,
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function paths(): array
    {
        $stored = $this->settings->get(self::SETTING_KEY, []);
        $paths = [];
        foreach (array_keys(SignatureTemplateService::REPORT_TYPES) as $key) {
            $val = is_array($stored) ? ($stored[$key] ?? null) : null;
            $paths[$key] = is_string($val) && $val !== '' ? $val : null;
        }

        return $paths;
    }

    /**
     * @return array<string, string|null>
     */
    public function urls(): array
    {
        $urls = [];
        foreach ($this->paths() as $key => $path) {
            $urls[$key] = $path ? asset('storage/'.$path) : null;
        }

        return $urls;
    }

    public function path(string $reportKey): ?string
    {
        return $this->paths()[$reportKey] ?? null;
    }

    public function url(string $reportKey): ?string
    {
        $path = $this->path($reportKey);
        if ($path === null) {
            return null;
        }

        return asset('storage/'.$path);
    }

    public function store(string $reportKey, string $dataUri): string
    {
        if (! isset(SignatureTemplateService::REPORT_TYPES[$reportKey])) {
            throw new RuntimeException("Jenis laporan tidak dikenal: {$reportKey}");
        }

        $extension = 'png';
        $binary = $this->decodeDataUri($dataUri, $extension);
        $this->assertValidImage($binary, $extension);

        $this->delete($reportKey, false);

        $tenantId = $this->context->id();
        $path = "tenants/{$tenantId}/signatures/{$reportKey}.{$extension}";
        Storage::disk(self::DISK)->put($path, $binary);

        $paths = $this->paths();
        $paths[$reportKey] = $path;
        $this->settings->set(self::SETTING_KEY, $paths, 'json');

        return $path;
    }

    public function delete(string $reportKey, bool $persistSetting = true): void
    {
        if (! isset(SignatureTemplateService::REPORT_TYPES[$reportKey])) {
            throw new RuntimeException("Jenis laporan tidak dikenal: {$reportKey}");
        }

        $path = $this->path($reportKey);
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }

        if (! $persistSetting) {
            return;
        }

        $paths = $this->paths();
        $paths[$reportKey] = null;
        $this->settings->set(self::SETTING_KEY, $paths, 'json');
    }

    /**
     * Data URI inline (base64) untuk embed aman di PDF DomPDF.
     */
    public function dataUri(string $reportKey): ?string
    {
        $path = $this->path($reportKey);
        if ($path === null) {
            return null;
        }

        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $binary = Storage::disk(self::DISK)->get($path);
        if ($binary === null || $binary === '') {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::MIME_MAP[$ext] ?? 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function decodeDataUri(string $dataUri, string &$extension): string
    {
        if (! preg_match('#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#is', trim($dataUri), $m)) {
            throw new RuntimeException('Format tanda tangan harus data URI PNG/JPG/WebP base64.');
        }

        $rawType = strtolower($m[1]);
        $extension = $rawType === 'jpeg' ? 'jpg' : $rawType;

        $decoded = base64_decode(str_replace(' ', '+', $m[2]), true);
        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('Payload gambar tanda tangan rusak.');
        }

        return $decoded;
    }

    private function assertValidImage(string $binary, string $extension): void
    {
        if (strlen($binary) > 2 * 1024 * 1024) {
            throw new RuntimeException('Ukuran gambar tanda tangan maksimal 2 MB.');
        }

        $head = substr($binary, 0, 12);
        $valid = match ($extension) {
            'png' => str_starts_with($head, "\x89PNG\r\n\x1A\n"),
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'webp' => strlen($binary) > 12 && str_starts_with(substr($binary, 0, 4), 'RIFF') && str_starts_with(substr($binary, 8, 4), 'WEBP'),
            default => false,
        };

        if (! $valid) {
            throw new RuntimeException('Isi file bukan file gambar yang valid.');
        }
    }
}
