<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Controllers\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Services\Reports\InvoiceService;
use App\Models\User;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invoice / Kuitansi cetak — formatter PDF untuk invoice/tagihan.
 *
 * Routes (defined in routes/web.php):
 *   GET /accounting/invoice/{id}/pdf                  → PDF (default source=installment)
 *   GET /accounting/invoice/{id}/pdf?source=journal   → PDF dari jurnal entry
 *   GET /accounting/invoice/{id}/pdf?source=installment → PDF dari angsuran
 *
 * Permission: `reports.view`.
 */
final class InvoiceController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly InvoiceService $service,
        private readonly ReportPdf $pdf,
    ) {}

    public function pdf(Request $request, int $id): Response|StreamedResponse
    {
        $this->authorize($request);

        $source = (string) $request->query('source', 'installment');

        try {
            $data = $this->service->build($id, $source);
        } catch (\DomainException $e) {
            abort(404, $e->getMessage());
        }

        $filename = sprintf(
            'invoice-%s-%s.pdf',
            $data['invoice']['source_type'] ?? 'invoice',
            preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($data['invoice']['number'] ?? "id-{$id}")),
        );

        return $this->pdf->stream('reports.pdf.invoice', $data, $filename);
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'reports.view');
    }
}
