<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Merender blok tanda tangan laporan dari pengaturan
 * Pengaturan > Tanda Tangan agar template yang sama dipakai pada
 * seluruh pelaporan PDF, bukan hanya dokumen pinjaman.
 */
final class ReportSignatureService
{
    public function __construct(
        private readonly SignatureTemplateService $templates,
        private readonly SignatureImageService $images,
    ) {}

    /**
     * @return string HTML siap cetak, atau string kosong bila tenant belum
     *                mengatur template tanda tangan.
     */
    public function render(string $reportKey): string
    {
        $html = $this->templates->get($reportKey);
        if ($html === '') {
            return '';
        }

        $html = strtr($html, $this->tokens());
        $html = $this->normalizeTableWidth($html);

        return $this->injectImage($html, $reportKey);
    }

    /**
     * Template dari editor bisa hanya punya `min-width`, sehingga di PDF
     * tabel tanda tangan tampak lebih sempit daripada saat diatur di SOP.
     * Paksa semua tabel memenuhi lebar isi laporan dan bagi kolom rata,
     * sama seperti tampilan tabel di editor SOP.
     */
    private function normalizeTableWidth(string $html): string
    {
        $normalized = preg_replace_callback(
            '/<table\b([^>]*)>/i',
            function (array $matches): string {
                $attributes = $matches[1];
                $style = 'width:100%;table-layout:fixed;';

                if (preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/is', $attributes, $styleMatch)) {
                    $existing = rtrim($styleMatch[2], '; ');
                    $merged = $existing !== '' ? $existing.';'.$style : $style;
                    $attributes = preg_replace(
                        '/\bstyle\s*=\s*(["\'])(.*?)\1/is',
                        'style="'.$merged.'"',
                        $attributes,
                        1
                    );
                } else {
                    $attributes .= ' style="'.$style.'"';
                }

                return '<table'.$attributes.'>';
            },
            $html
        );

        return $normalized ?? $html;
    }

    /**
     * @return array<string, string>
     */
    private function tokens(): array
    {
        $profile = $this->profile();
        $now = CarbonImmutable::now();

        $managerTitle = (string) ($profile?->manager_title ?? '');
        $secretaryTitle = (string) ($profile?->secretary_title ?? '');
        $treasurerTitle = (string) ($profile?->treasurer_title ?? '');
        $verifierTitle = (string) ($profile?->verifier_title ?? '');

        return [
            '{nama_lembaga}' => (string) ($profile?->legal_name ?: config('app.name')),
            '{nama_singkat}' => (string) ($profile?->short_name ?? ''),
            '{brand_short}' => (string) ($profile?->brand_short ?: $profile?->short_name ?? ''),
            '{alamat_lembaga}' => (string) ($profile?->address ?? ''),
            '{telepon_lembaga}' => (string) ($profile?->phone ?? ''),
            '{email_lembaga}' => (string) ($profile?->email ?? ''),
            '{nama_kabupaten}' => (string) ($profile?->regency_name ?? ''),
            '{nama_kecamatan}' => (string) ($profile?->district_name ?? ''),

            '{kepala_lembaga}' => (string) ($profile?->manager_name ?? ''),
            '{jabatan_kepala}' => $managerTitle !== '' ? $managerTitle : 'Ketua',
            '{kepala_lembaga_nik}' => (string) ($profile?->getAttribute('manager_nik') ?? ''),
            '{sekretaris_lembaga}' => (string) ($profile?->secretary_name ?? ''),
            '{jabatan_sekretaris}' => $secretaryTitle !== '' ? $secretaryTitle : 'Sekretaris',
            '{bendahara_lembaga}' => (string) ($profile?->treasurer_name ?? ''),
            '{jabatan_bendahara}' => $treasurerTitle !== '' ? $treasurerTitle : 'Bendahara',
            '{verifikator}' => (string) ($profile?->verifier_name ?? ''),
            '{jabatan_verifikator}' => $verifierTitle !== '' ? $verifierTitle : 'Verifikator',

            '{tgl_kondisi}' => $now->locale('id')->translatedFormat('d F Y'),
            '{tgl_romawi}' => $now->format('d').' '.$this->romanMonth((int) $now->format('n')).' '.$now->format('Y'),
        ];
    }

    private function romanMonth(int $month): string
    {
        return [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ][$month] ?? (string) $month;
    }

    private function profile(): ?OrganizationProfile
    {
        try {
            return OrganizationProfile::query()->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function injectImage(string $html, string $reportKey): string
    {
        // Gambar khusus jenis laporan; bila belum diunggah, pakai gambar
        // default agar tanda tangan tetap tampil di semua laporan.
        $uri = $this->images->dataUri($reportKey) ?? $this->images->dataUri('default');
        if ($uri === null) {
            return $html;
        }

        $img = '<img src="'.$uri.'" style="height:50px;max-width:180px;object-fit:contain" alt="Tanda Tangan" />';

        if (str_contains($html, '{ttd_image}')) {
            return str_replace('{ttd_image}', $img, $html);
        }

        return preg_replace('/(<p>(<br\s*\/?>\s*)+<\/p>)/i', $img.'$1', $html, 1) ?? $html;
    }
}
