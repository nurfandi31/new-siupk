<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending\DTO;

final readonly class NormalizedMemberInstallment
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public int $memberLoanLegacyId,
        public int $installmentNumber,
        public string $dueDate,
        public string $principalDue,
        public string $interestDue,
        public array $snapshot,
    ) {}
}
