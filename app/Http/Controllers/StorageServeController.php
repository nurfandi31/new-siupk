<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class StorageServeController
{
    public function __invoke(string $path): BinaryFileResponse
    {
        // Sanitize path to prevent directory traversal attacks
        $cleanPath = ltrim(str_replace(['../', '..\\', "\0"], '', $path), '/\\');

        if ($cleanPath === '' || str_starts_with($cleanPath, '.')) {
            abort(404, 'Berkas tidak ditemukan.');
        }

        $fullPath = storage_path('app/public/'.$cleanPath);

        if (! file_exists($fullPath) || is_dir($fullPath)) {
            abort(404, 'Berkas tidak ditemukan.');
        }

        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400, must-revalidate',
        ]);
    }
}
