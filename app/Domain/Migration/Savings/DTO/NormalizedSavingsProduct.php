<?php

declare(strict_types=1);

namespace App\Domain\Migration\Savings\DTO;

final readonly class NormalizedSavingsProduct
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public string $code,
        public string $name,
        public string $category,
        public int $formType,
        public string $minimumBalance,
        public int $sortOrder,
        public array $snapshot,
    ) {}
}
