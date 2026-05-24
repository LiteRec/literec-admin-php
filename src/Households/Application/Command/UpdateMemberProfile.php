<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Primitive-only command DTO for updating a member's profile (name, DOB,
 * gender).
 */
final readonly class UpdateMemberProfile
{
    public function __construct(
        public string $householdId,
        public string $memberId,
        public string $firstName,
        public string $lastName,
        public ?string $middleName,
        public ?string $suffix,
        public string $dobIso,
        public string $genderCode,
    ) {
    }
}
