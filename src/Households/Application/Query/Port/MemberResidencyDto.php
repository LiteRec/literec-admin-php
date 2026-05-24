<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Primitive-only projection of a member's current residency status, plus
 * the effective-from date of the most recent residency change (LRA-44).
 *
 * `effectiveFromIso` is `null` for members who have never had a residency
 * change recorded — e.g. freshly-registered households whose primary was
 * created with the initial status but has no row in
 * `household_residency_history` yet. Once any `MemberResidencyChanged`
 * event has been recorded for the member, the field reflects the
 * effective-from date of the latest entry.
 */
final readonly class MemberResidencyDto
{
    public function __construct(
        public string $status,
        public ?string $effectiveFromIso = null,
    ) {
    }
}
