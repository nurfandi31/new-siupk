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

        return $this->pdf->stream(
            $meta['view'],
            $payload,
            $meta['key'].'-pinjaman-'.$loan->id.'.pdf',
            $meta['orientation'],
        );
    }
}
