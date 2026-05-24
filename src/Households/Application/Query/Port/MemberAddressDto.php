<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Primitive-only projection of a member's household address. Because the
 * Households context models address at the household level, every member
 * in a household shares this DTO's contents.
 */
final readonly class MemberAddressDto
{
    public function __construct(
        public string $street,
        public ?string $unit,
        public string $city,
        public string $state,
        public string $postalCode,
        public string $country,
    ) {
    }
}
