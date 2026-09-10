<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Services;

final readonly class UpdateManifestService
{
    public function manifest(?string $currentVersion = null): array
    {
        $latestVersion = $this->normalizeVersion((string) config('desktop-update.latest_version'));
        $minSupportedVersion = $this->normalizeVersion((string) config('desktop-update.min_version'));
        $currentVersion = $this->normalizeVersion($currentVersion ?? '');

        return [
            'update_available' => $currentVersion !== '' && version_compare($currentVersion, $latestVersion, '<'),
            'latest_version' => $latestVersion,
            'current_version' => $currentVersion,
            'min_supported_version' => $minSupportedVersion,
            'force_update' => $currentVersion !== '' && version_compare($currentVersion, $minSupportedVersion, '<'),
            'download_url' => (string) config('desktop-update.download_url'),
            'release_notes_url' => (string) config('desktop-update.release_notes_url'),
            'sha512' => (string) config('desktop-update.sha512'),
        ];
    }

    public function outdated(?string $currentVersion): bool
    {
        $normalized = $this->normalizeVersion((string) $currentVersion);
        if ($normalized === '') {
            return false;
        }

        return version_compare($normalized, (string) config('desktop-update.min_version'), '<');
    }

    public function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
