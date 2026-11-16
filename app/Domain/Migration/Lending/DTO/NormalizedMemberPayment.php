<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending\DTO;

final readonly class NormalizedMemberPayment
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public int $memberLoanLegacyId,
        public string $paidAt,
        public string $principal,
        public string $interest,
        public string $amount,
        public array $snapshot,
    ) {}
}
