<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending\DTO;

final readonly class NormalizedInstallment
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public int $groupLoanLegacyId,
        public int $installmentNumber,
        public string $dueDate,
        public string $principalDue,
        public string $interestDue,
        public array $snapshot,
    ) {}
}
