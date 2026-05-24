<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

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
