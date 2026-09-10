<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting\DTO;

final readonly class NormalizedJournal
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $idt,
        public string $transactionDate,
        public int $sequenceNumber,
        public string $description,
        public int $legacyTransactionTypeId,
        public int $legacyLoanId,
        public int $legacyLoanItemId,
        public ?string $legacyRelation,
        public string $debitCode,
        public string $creditCode,
        public int $debitAccountRowId,
        public int $creditAccountRowId,
        public string $amount,
        public string $amountRaw,
        public array $snapshot,
    ) {}
}
