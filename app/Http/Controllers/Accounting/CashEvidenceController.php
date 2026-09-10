<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\Reports\CashEvidenceService;
use App\Domain\Accounting\Services\Reports\DocumentKindClassifier;
use App\Models\User;
use App\Support\ReportPdf;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cetak Bukti Kas (BKM/BKK/BM) dari sebuah `JournalEntry` modern.
 *
 * - GET /accounting/journals/{entry}/cash-evidence/{kind} -> PDF bukti sesuai kind (BKM/BKK/BM)
 *   - kind opsional; bila kosong, kind ditentukan otomatis dari heuristic debit/kredit
 * - GET /accounting/journals/{entry}/cash-evidence-kind -> JSON { kind } (untuk UI)
 */
final class CashEvidenceController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly CashEvidenceService $service,
        private readonly DocumentKindClassifier $classifier,
        private readonly ReportPdf $pdf,
    ) {}

    public function kind(JournalEntry $entry): JsonResponse
    {
        $this->denyAccess();

        $entry->loadMissing(['lines.account']);

        $debitCode = (string) ($entry->lines->firstWhere('debit', '>', 0)?->account?->code ?? '');
        $creditCode = (string) ($entry->lines->firstWhere('credit', '>', 0)?->account?->code ?? '');

        return response()->json([
            'kind' => $this->classifier->classify($debitCode, $creditCode),
            'row_id' => (int) $entry->row_id,
            'id' => (int) $entry->id,
        ]);
    }

    public function document(
        Request $request,
        JournalEntry $entry,
        ?string $kind = null,
    ): Response|StreamedResponse {
        $this->denyAccess();

        if ($kind !== null) {
            $kind = strtoupper((string) $kind);
            if (! in_array($kind, [DocumentKindClassifier::KIND_BKM, DocumentKindClassifier::KIND_BKK, DocumentKindClassifier::KIND_BM], true)) {
                abort(422, 'Tipe bukti kas tidak dikenal: '.$kind);
            }
        }

        try {
            $payload = $this->service->build($entry);
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        // Override kind jika dipaksa via URL (?kind=BKK atau /cash-evidence/BKK)
        if ($kind !== null) {
            $payload['kind'] = $kind;
            $payload['document'] = [
                'key' => strtolower($kind),
                'label' => match ($kind) {
                    DocumentKindClassifier::KIND_BKM => 'Bukti Kas Masuk',
                    DocumentKindClassifier::KIND_BKK => 'Bukti Kas Keluar',
                    default => 'Bukti Memorial',
                },
                'kind' => $kind,
            ];
        }

        // 14cm x 9cm in points (1cm = 28.3465pt)
        $paper = [0, 0, 396.85, 255.12];

        return $this->pdf->stream(
            'reports.pdf.cash_evidence.'.strtolower($payload['kind']),
            $payload,
            strtolower($payload['kind']).'-jurnal-'.$payload['entry']['id'].'.pdf',
            'landscape',
            $paper,
        );
    }

    private function denyAccess(): void
    {
        /** @var User|null $user */
        $user = request()->user();
        $this->permissions->denyUnless($user, 'journals.view');
    }
}
