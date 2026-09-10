<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership\DTO;

final readonly class NormalizedGroupBundle
{
    /**
     * @param  list<int>  $memberLegacyIds
     * @param  array<string, int|string|null>  $officers  position => member legacy id or name
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public string $code,
        public string $name,
        public ?string $address,
        public ?string $phone,
        public ?string $establishedAt,
        public string $status,
        public ?int $organizationUnitRowId,
        public ?int $businessTypeRowId,
        public ?int $activityTypeRowId,
        public ?int $groupLevelRowId,
        public ?int $groupFunctionRowId,
        public array $memberLegacyIds,
        public array $officers,
        public array $snapshot,
    ) {}
}
