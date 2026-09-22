<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Services\Reports\LoanDocumentService;
use App\Models\User;
use App\Support\ReportPdf;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LoanDocumentController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly LoanDocumentService $service,
        private readonly ReportPdf $pdf,
    ) {}

    public function document(
        Request $request,
        Loan $loan,
        string $type,
    ): Response|StreamedResponse {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'loans.view');

        try {
            $meta = $this->service->resolve($type);
            $payload = $this->service->payload($loan, $type);
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        // Dokumen berkstage 'individual_*' hanya untuk pinjaman perorangan.
        // Dokumen dengan stage kelompok (proposal/verification/disbursement)
        // ditolak untuk pinjaman perorangan (lihat LoanDocumentService::availableDocuments).
        $isIndividualLoan = (string) $loan->legacy_source === 'member_loan';
        if (str_starts_with((string) $meta['stage'], 'individual_') && ! $isIndividualLoan) {
            abort(422, 'Dokumen '.strtoupper($meta['label']).' hanya tersedia untuk pinjaman perorangan.');
        }
        if (! str_starts_with((string) $meta['stage'], 'individual_') && $isIndividualLoan) {
            abort(422, 'Dokumen '.strtoupper($meta['label']).' tidak tersedia untuk pinjaman perorangan.');
        }

        return $this->pdf->stream(
            $meta['view'],
            $payload,
            $meta['key'].'-pinjaman-'.$loan->id.'.pdf',
            $meta['orientation'],
        );
    }
}
