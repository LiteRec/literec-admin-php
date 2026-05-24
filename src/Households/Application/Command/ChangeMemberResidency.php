<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Primitive-only command DTO for changing a member's residency status.
 *
 * `effectiveFromIso` is an ISO 8601 date or datetime string; the handler
 * parses it and translates parse failures into an
 * {@see \App\Households\Domain\Exception\InvariantViolation}.
 *
 * `reason` is an optional free-text audit note recorded on the
 * `MemberResidencyChanged` event and persisted to the
 * `household_residency_history` table (LRA-37 migration).
 */
final readonly class ChangeMemberResidency
{
    public function __construct(
        public string $householdId,
        public string $memberId,
        public string $residencyStatusCode,
        public string $effectiveFromIso,
        public ?string $reason = null,
    ) {
    }
}
