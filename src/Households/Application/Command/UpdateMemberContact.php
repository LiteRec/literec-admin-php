<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Primitive-only command DTO for updating a member's contact channels
 * (email, phone). A null value means "clear this contact channel" —
 * {@see \App\Households\Domain\MemberInHousehold::updateContact()} treats
 * null as an explicit removal, not "leave unchanged".
 */
final readonly class UpdateMemberContact
{
    public function __construct(
        public string $householdId,
        public string $memberId,
        public ?string $email,
        public ?string $phone,
    ) {
    }
}
