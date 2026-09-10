<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending\DTO;

final readonly class NormalizedBeneficiary
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public int $groupLoanLegacyId,
        public int $memberLegacyId,
        public string $allocated,
        public string $proposed,
        public string $verified,
        public array $snapshot,
    ) {}
}
