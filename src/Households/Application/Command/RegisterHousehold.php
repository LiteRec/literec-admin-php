<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Primitive-only command DTO for the RegisterHousehold use case.
 *
 * Creates a household with its primary member in one call. Value-object
 * construction happens inside the handler so invalid input surfaces as a
 * named domain exception rather than a constructor TypeError.
 *
 * The memberCode is optional; when null the handler allocates one via the
 * {@see \App\Households\Domain\MemberCodeAllocator} port.
 */
final readonly class RegisterHousehold
{
    public function __construct(
        public string $householdName,
        public string $firstName,
        public string $lastName,
        public ?string $middleName,
        public ?string $suffix,
        public string $dobIso,
        public string $genderCode,
        public string $email,
        public string $phone,
        public string $residencyStatusCode,
        public ?string $memberCode,
        public string $street,
        public ?string $unit,
        public string $city,
        public string $state,
        public string $postalCode,
        public string $country,
    ) {
    }
}
