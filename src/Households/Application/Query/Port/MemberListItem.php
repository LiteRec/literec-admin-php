<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Primitive-only projection of a single member row for the members list page
 * (LRA-39, restyled as the Users list in LRA-192) and the lookup dialog
 * (LRA-46). The read model is deliberately flat — strings instead of value
 * objects — so views and JSON encoders can consume it without going through
 * domain construction.
 */
final readonly class MemberListItem
{
    public function __construct(
        public string $memberId,
        public string $householdId,
        public string $householdName,
        public string $memberCode,
        public string $fullName,
        public ?string $email,
        public ?string $dobIso,
        public ?string $phone,
        public string $addressShort,
        public string $residencyStatus,
        public bool $isPrimary,
        public bool $isActive,
        public ?string $photoVersion,
        public bool $isMerged,
        public bool $isShared,
    ) {
    }
}
