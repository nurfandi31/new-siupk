<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending\DTO;

final readonly class NormalizedGroupLoan
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public int $sequenceNumber,
        public int $groupLegacyId,
        public int $productRowId,
        public string $productCode,
        public string $principal,
        public string $interestRate,
        public int $termMonths,
        public string $installmentMethod,
        public string $principalFrequency,
        public string $interestFrequency,
        public int $principalGraceMonths,
        public int $interestGraceMonths,
        public string $status,
        public ?string $proposedAt,
        public ?string $verifiedAt,
        public ?string $approvedAt,
        public ?string $fundedAt,
        public ?string $disbursedAt,
        public ?string $completedAt,
        public ?string $loanNumber,
        public ?string $verificationNotes,
        public ?string $guidanceNotes,
        public ?string $verificationTime,
        public ?string $disbursementScheduleText,
        public array $snapshot,
    ) {}
}
