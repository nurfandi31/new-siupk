<?php

declare(strict_types=1);

namespace App\Domain\Migration\Savings\DTO;

final readonly class NormalizedSavingsTransaction
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public int $accountLegacyId,
        public string $transactionDate,
        public string $type,
        public int $code,
        public string $direction,
        public string $amount,
        public ?string $balanceAfter,
        public ?string $description,
        public ?string $reference,
        public array $snapshot,
    ) {}
}
