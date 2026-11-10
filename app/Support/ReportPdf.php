<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ReportPdf
{
    /**
     * Teks kop yang boleh dilengkapi otomatis dari identitas lembaga.
     *
     * @var list<string>
     */
    private const LETTERHEAD_IDENTITY_FIELDS = [
        'legal_name',
        'short_name',
        'registration_number',
        'tax_number',
        'address',
        'district_name',
        'regency_name',
        'phone',
        'email',
        'website',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  string|array{0: float, 1: float, 2: float, 3: float}  $paper  Paper size name (e.g. 'a4') or [x, y, w, h] in points.
     */
    public function stream(string $view, array $data, string $filename, string $orientation = 'portrait', string|array $paper = 'a4'): Response|StreamedResponse
    {
        $pdf = Pdf::loadView($view, $this->withLogo($data))->setPaper($paper, $orientation);

        if (request()->boolean('download') || request()->query('download') === '1') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function download(string $view, array $data, string $filename, string $orientation = 'portrait', string|array $paper = 'a4'): Response|StreamedResponse
    {
        $pdf = Pdf::loadView($view, $this->withLogo($data))->setPaper($paper, $orientation);

        return $pdf->download($filename);
    }

    /**
     * Pastikan semua PDF yang dibuat lewat ReportPdf memakai identitas
     * lembaga yang sama dengan halaman SOP/Pengaturan, termasuk teks kop
     * dan logo, meskipun service pelaporan belum menyertakan field tersebut
     * di payload identity-nya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withLogo(array $data): array
    {
        $identity = is_array($data['identity'] ?? null) ? $data['identity'] : [];
        $logoUrl = $identity['logo_url'] ?? $data['logo_url'] ?? null;

        try {
            $context = app(TenantContext::class);
            if ($context->isInitialized()) {
                $profile = OrganizationProfile::query()->first();

                if ($profile !== null) {
                    $identity = $this->mergeLetterheadIdentity($identity, $profile);
                    $path = (string) ($profile->logo_path ?? '');

                    // Gunakan data URI agar DomPDF tidak perlu mengakses URL
                    // storage (enable_remote=false) saat mencetak laporan.
                    $logoUrl = $this->fileDataUri($path) ?: $logoUrl ?: $profile->logo_url;
                }
            }
        } catch (Throwable) {
            // Jangan gagalkan cetak laporan hanya karena identitas/logo tidak tersedia.
        }

        if ($identity !== []) {
            $data['identity'] = $identity;
        }

        if (empty($logoUrl)) {
            return $data;
        }

        $data['identity']['logo_url'] = $logoUrl;
        $data['logo_url'] ??= $logoUrl;

        return $data;
    }

    /**
     * Lengkapi teks kop yang belum diisi service laporan dengan data
     * `organization_profiles`. Nilai yang sudah dikirim service tetap
     * dipertahankan agar laporan khusus tidak tertimpa.
     *
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function mergeLetterheadIdentity(array $identity, OrganizationProfile $profile): array
    {
        foreach (self::LETTERHEAD_IDENTITY_FIELDS as $field) {
            $value = $profile->getAttribute($field);

            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            $current = $identity[$field] ?? null;
            if ($current === null || (is_scalar($current) && trim((string) $current) === '')) {
                $identity[$field] = (string) $value;
            }
        }

        return $identity;
    }

    private function fileDataUri(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        try {
            $storage = Storage::disk('public');
            if (! $storage->exists($path)) {
                return null;
            }

            $contents = $storage->get($path);
            if ($contents === '') {
                return null;
            }

            $mime = $storage->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        } catch (Throwable) {
            return null;
        }
    }
}
