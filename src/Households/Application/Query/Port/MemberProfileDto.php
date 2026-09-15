<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Primitive-only projection of a member's profile information for the
 * member detail page (LRA-41).
 */
final readonly class MemberProfileDto
{
    public function __construct(
        public string $memberId,
        public string $memberCode,
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public ?string $suffix,
        public string $fullName,
        public ?string $dobIso,
        public string $genderCode,
        public ?string $email,
        public ?string $phone,
        public ?string $nickname,
        public ?string $salutationCode,
        public ?int $heightInches,
        public ?int $weightPounds,
        public bool $isPrimary,
        public bool $isActive,
        public ?string $deactivatedReason,
        public ?string $deactivatedAtIso,
        public ?string $photoVersion,
        public ?string $photoMimeType,
        public ?string $mergedIntoMemberId,
        public ?string $mergedIntoHouseholdId,
        public ?string $mergedAtIso,
        public ?string $anonymizedAtIso,
    ) {
    }
}
