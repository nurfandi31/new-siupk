<?php

declare(strict_types=1);

namespace App\Domain\Migration\Savings\DTO;

final readonly class NormalizedSavingsAccount
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public int $productLegacyId,
        public int $productRowId,
        public int $memberLegacyId,
        public string $accountNumber,
        public string $openingAmount,
        public string $interestRate,
        public string $taxRate,
        public string $adminFee,
        public string $openingFee,
        public string $openedAt,
        public ?string $minimumClosingDate,
        public ?string $closedAt,
        public string $status,
        public int $authorityType,
        public ?string $institutionName,
        public ?string $institutionPosition,
        public ?string $guardianName,
        public ?string $relationship,
        public ?string $notes,
        public array $snapshot,
    ) {}
}
