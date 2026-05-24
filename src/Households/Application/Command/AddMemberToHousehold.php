<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Primitive-only command DTO to add a member to an existing household.
 *
 * memberCode is optional; when null the handler allocates one via the
 * {@see \App\Households\Domain\MemberCodeAllocator} port.
 *
 * isPrimary marks the new member as the household's primary contact. The
 * skeleton aggregate does not currently enforce "at most one primary" —
 * the controller layer (LRA-40 dialog flow) is responsible for clearing
 * the previous primary before submitting this command if needed. A
 * follow-up ticket may move that invariant into the aggregate.
 */
final readonly class AddMemberToHousehold
{
    public function __construct(
        public string $householdId,
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
        public bool $isPrimary,
    ) {
    }
}
