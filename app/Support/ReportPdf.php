<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Documents\Services\ReportSignatureService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ReportPdf
{
    public function __construct(
        private readonly ReportSignatureService $signatures,
    ) {}

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
        $pdf = $this->makePdf($view, $data, $orientation, $paper);

        if (request()->boolean('download') || request()->query('download') === '1') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function download(string $view, array $data, string $filename, string $orientation = 'portrait', string|array $paper = 'a4'): Response|StreamedResponse
    {
        return $this->makePdf($view, $data, $orientation, $paper)->download($filename);
    }

    /**
     * Bangun PDF dengan kop/logo lembaga dan blok tanda tangan yang
     * dikonfigurasi di Pengaturan > Tanda Tangan.
     *
     * @param  array<string, mixed>  $data
     * @param  string|array{0: float, 1: float, 2: float, 3: float}  $paper
     */
    private function makePdf(string $view, array $data, string $orientation, string|array $paper): \Barryvdh\DomPDF\PDF
    {
        $data = $this->withLogo($data);
        $signature = $this->signatureHtml($view);
        $data['signature_html'] = $signature;

        // Bila tidak ada template tanda tangan, pertahankan jalur lama agar
        // dokumen yang tidak memakai tanda tangan tidak berubah.
        if ($signature === '') {
            return Pdf::loadView($view, $data)->setPaper($paper, $orientation);
        }

        return Pdf::loadHTML($this->renderHtml($view, $data, $signature))->setPaper($paper, $orientation);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderHtml(string $view, array $data, string $signature): string
    {
        $html = view($view, $data)->render();

        // Beberapa laporan sudah menaruh blok tanda tangan sendiri dan
        // menandainya dengan data-report-signature; jangan tampilkan dua kali.
        if (str_contains($html, 'data-report-signature')) {
            return $html;
        }

        return $this->appendSignature($html, $signature);
    }

    /**
     * Pilih template tanda tangan berdasarkan jenis laporan. Dokumen
     * transaksi (kwitansi, kartu pinjaman, dokumen pinjaman) tidak
     * ditambahkan karena sudah memiliki blok tanda tangan sendiri.
     */
    private function signatureHtml(string $view): string
    {
        $reportKey = $this->signatureKeyForView($view);
        if ($reportKey === null) {
            return '';
        }

        try {
            return $this->signatures->render($reportKey);
        } catch (Throwable) {
            // Cetak laporan tetap berjalan meskipun tanda tangan gagal dimuat.
            return '';
        }
    }

    private function signatureKeyForView(string $view): ?string
    {
        $documentViews = [
            'reports.pdf.annual.cover',
            'reports.pdf.invoice',
            'reports.pdf.installment_receipt',
            'reports.pdf.loan_card',
            'reports.pdf.loan_card_member',
            'reports.pdf.loan_card_reprint',
            'reports.pdf.loan_settlement_kelompok',
            'reports.pdf.loan_settlement_member',
            'reports.pdf.ojk.cover',
        ];

        if (in_array($view, $documentViews, true)
            || str_starts_with($view, 'reports.pdf.loan_documents.')
            || str_starts_with($view, 'reports.pdf.cash_evidence.')) {
            return null;
        }

        $loanReportViews = [
            'reports.pdf.loan_list',
            'reports.pdf.loan_portfolio',
            'reports.pdf.loan_schedule_vs_actual',
            'reports.pdf.loan_simulation',
            'reports.pdf.ojk.active_loans',
            'reports.pdf.ojk.loans_received',
            'reports.pdf.ojk.paid_group',
            'reports.pdf.ojk.paid_individu',
        ];

        if (in_array($view, $loanReportViews, true)
            || str_starts_with($view, 'reports.pdf.lending.')) {
            return 'rekap_pinjaman';
        }

        return 'laporan_keuangan';
    }

    private function appendSignature(string $html, string $signature): string
    {
        $block = '<div data-report-signature style="width:100%;margin-top:24px;page-break-inside:avoid;font-size:11px;">'
            .$signature
            .'</div>';

        $position = strripos($html, '</body>');

        if ($position === false) {
            return $html.$block;
        }

        return substr_replace($html, $block, $position, 0);
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
