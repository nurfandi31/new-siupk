<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership\DTO;

final readonly class NormalizedMemberBundle
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $legacyId,
        public string $fullName,
        public ?string $nik,
        public string $memberNumber,
        public ?string $gender,
        public ?string $birthPlace,
        public ?string $birthDate,
        public ?string $phone,
        public ?string $familyCardNumber,
        public ?string $address,
        public ?int $organizationUnitRowId,
        public string $registeredAt,
        public string $status,
        public ?string $businessName,
        public ?string $businessDescription,
        public ?string $guarantorName,
        public ?string $guarantorNik,
        public ?string $guarantorRelationship,
        public array $snapshot,
    ) {}
}
