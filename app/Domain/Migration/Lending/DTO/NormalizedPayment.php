<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending\DTO;

final readonly class NormalizedPayment
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public int $groupLoanLegacyId,
        public string $paidAt,
        public string $principal,
        public string $interest,
        public string $amount,
        public array $snapshot,
    ) {}
}
