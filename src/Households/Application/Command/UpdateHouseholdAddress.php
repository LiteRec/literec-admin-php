<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Primitive-only command DTO for updating a household's mailing address.
 * The address lives on the household aggregate — all members share it.
 */
final readonly class UpdateHouseholdAddress
{
    public function __construct(
        public string $householdId,
        public string $street,
        public ?string $unit,
        public string $city,
        public string $state,
        public string $postalCode,
        public string $country,
    ) {
    }
}
