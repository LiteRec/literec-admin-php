<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Primitive-only command DTO to split one or more transaction references
 * off the source member onto a newly created member in the same
 * household (LRA-209).
 *
 * memberCode is optional; when null the handler allocates one via the
 * {@see \App\Households\Domain\MemberCodeAllocator} port, mirroring
 * {@see AddMemberToHousehold}. email/phone are optional: the two people
 * are distinct, so contact info is entered fresh rather than copied from
 * the source.
 */
final readonly class SplitMember
{
    /**
     * @param list<string> $transactionIds
     */
    public function __construct(
        public string $householdId,
        public string $sourceMemberId,
        public string $firstName,
        public string $lastName,
        public ?string $middleName,
        public ?string $suffix,
        public ?string $email,
        public ?string $phone,
        public array $transactionIds,
        public ?string $reason,
        public ?string $memberCode,
    ) {
    }
}
